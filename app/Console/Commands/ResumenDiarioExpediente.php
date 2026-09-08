<?php

namespace App\Console\Commands;

use App\Jobs\EnviarNotificacionJob;
use App\Models\MovimientoExpediente;
use App\Models\PeriodoAscenso;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Manda a cada docente un solo correo con todo lo que se movió hoy en su expediente.
 *
 * Sustituye el correo por documento. Antes, revisar el expediente completo de un docente en una
 * misma sesión le producía cuatro mensajes idénticos —«uno de tus documentos ha sido rechazado»,
 * sin decir cuál— y ninguna noticia de lo que sí quedó aprobado.
 *
 * Corre a diario y no hace nada los días sin movimiento, que en una plataforma de ciclo anual son
 * la mayoría. Cuando el periodo está abierto, el correo recuerda además la fecha de cierre: es lo
 * que convierte un aviso informativo en uno accionable.
 */
class ResumenDiarioExpediente extends Command
{
    protected $signature = 'expediente:resumen-diario
                            {--simular : Muestra lo que se enviaría sin encolar ni marcar nada}';

    protected $description = 'Envía a cada docente el resumen de los movimientos de su expediente';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        $this->info('Resumen diario de expedientes — ' . Carbon::today()->toDateString() . ($simular ? ' (simulación)' : ''));

        $pendientes = MovimientoExpediente::sinNotificar()
            ->with('docente:id,primer_nombre,email')
            ->orderBy('user_id')
            ->get()
            ->groupBy('user_id');

        if ($pendientes->isEmpty()) {
            $this->line('  Sin movimientos pendientes. Nada que enviar.');

            return self::SUCCESS;
        }

        // El periodo se resuelve una vez, no por docente: es el mismo para todos.
        $periodo = PeriodoAscenso::vigente();
        $dias = $periodo ? (int) Carbon::today()->diffInDays($periodo->fecha_cierre, false) : null;

        $enviados = 0;

        foreach ($pendientes as $userId => $movimientos) {
            $docente = $movimientos->first()->docente;

            if (!$docente) {
                // El usuario ya no existe. Se marcan como notificados para que no queden
                // reapareciendo en cada ejecución.
                $this->marcar($movimientos, $simular);
                continue;
            }

            $aprobados = $movimientos->where('accion', MovimientoExpediente::APROBADO)
                ->map(fn ($m) => ['categoria' => $m->categoria, 'descripcion' => $m->descripcion])
                ->values()->all();

            $rechazados = $movimientos->where('accion', MovimientoExpediente::RECHAZADO)
                ->map(fn ($m) => [
                    'categoria'   => $m->categoria,
                    'descripcion' => $m->descripcion,
                    'motivo'      => $m->motivo,
                ])
                ->values()->all();

            $this->line("  Docente {$userId}: " . count($rechazados) . ' rechazados, ' . count($aprobados) . ' aprobados');

            if (!$simular) {
                EnviarNotificacionJob::dispatch(
                    // La fecha va en la clave: el resumen es de un día concreto, y el de mañana
                    // debe poder salir aunque el de hoy ya haya salido.
                    "expediente.resumen:user:{$userId}:" . Carbon::today()->toDateString(),
                    'expediente.resumen',
                    'resumenDiarioExpediente',
                    [
                        $aprobados,
                        $rechazados,
                        $periodo?->nombre,
                        $periodo?->fecha_cierre->format('d/m/Y'),
                        $dias,
                    ],
                    $userId
                );
            }

            $this->marcar($movimientos, $simular);
            $enviados++;
        }

        $this->info("  Total: {$enviados} resúmenes " . ($simular ? 'simulados.' : 'encolados.'));

        return self::SUCCESS;
    }

    /**
     * Marca los movimientos como ya incluidos en un resumen.
     *
     * Se marca aunque el envío falle después: el job reintenta por su cuenta y `NotificacionEnviada`
     * guarda el error. Si no se marcaran, el resumen del día siguiente repetiría estos movimientos
     * junto a los nuevos.
     */
    private function marcar($movimientos, bool $simular): void
    {
        if ($simular) {
            return;
        }

        MovimientoExpediente::whereIn('id_movimiento', $movimientos->pluck('id_movimiento'))
            ->update(['notificado_en' => now()]);
    }
}
