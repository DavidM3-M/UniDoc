<?php

namespace App\Console\Commands;

use App\Jobs\EnviarNotificacionJob;
use App\Models\MovimientoExpediente;
use App\Models\PeriodoAscenso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResumenDiarioExpediente extends Command
{
    protected $signature = 'expediente:resumen-diario {--simular : Muestra los lotes sin guardar ni encolar}';
    protected $description = 'Envía los movimientos pendientes del expediente en lotes recuperables';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $usuarios = MovimientoExpediente::sinNotificar()->distinct()->pluck('user_id');
        $periodo = PeriodoAscenso::vigente();
        $fallos = 0;

        foreach ($usuarios as $userId) {
            // Cada movimiento queda asignado a un lote estable. Si encolar falla, la siguiente
            // ejecución recupera ese mismo lote; las novedades posteriores reciben otro.
            $movimientos = DB::transaction(function () use ($userId, $simular) {
                $filas = MovimientoExpediente::sinNotificar()->where('user_id', $userId)
                    ->orderBy('id_movimiento')->lockForUpdate()->get();
                $nuevos = $filas->whereNull('lote_notificacion');
                if ($nuevos->isNotEmpty()) {
                    $clave = 'expediente.resumen:' . Str::uuid();
                    if (!$simular) {
                        MovimientoExpediente::whereIn('id_movimiento', $nuevos->pluck('id_movimiento'))
                            ->update(['lote_notificacion' => $clave]);
                    }
                    $nuevos->each(fn ($m) => $m->lote_notificacion = $clave);
                }
                return $filas;
            });

            foreach ($movimientos->groupBy('lote_notificacion') as $clave => $lote) {
                $this->line("Docente {$userId}: {$lote->count()} movimientos" . ($simular ? ' (simulación)' : ''));
                if ($simular) {
                    continue;
                }
                $resumir = fn ($m) => ['categoria' => $m->categoria, 'descripcion' => $m->descripcion, 'motivo' => $m->motivo];
                try {
                    EnviarNotificacionJob::dispatch(
                        $clave, 'expediente.resumen', 'resumenDiarioExpediente',
                        [
                            $lote->where('accion', MovimientoExpediente::APROBADO)->map($resumir)->values()->all(),
                            $lote->where('accion', MovimientoExpediente::RECHAZADO)->map($resumir)->values()->all(),
                            $periodo?->nombre, $periodo?->fecha_cierre->format('d/m/Y'),
                            $periodo ? (int) now()->startOfDay()->diffInDays($periodo->fecha_cierre, false) : null,
                            $lote->where('accion', MovimientoExpediente::ACTUALIZADO)->map($resumir)->values()->all(),
                        ],
                        $userId, $lote->pluck('id_movimiento')->all()
                    )->afterCommit();
                } catch (\Throwable $e) {
                    $fallos++;
                    Log::error("No se pudo encolar el lote {$clave}: " . $e->getMessage());
                }
            }
        }
        return $fallos ? self::FAILURE : self::SUCCESS;
    }
}
