<?php

namespace App\Console\Commands;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\PeriodoAscenso;
use App\Models\Usuario\User;
use App\Jobs\EnviarNotificacionJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Avisos del ciclo de ascenso que dependen del calendario, no de una acción.
 *
 * El resto de notificaciones de UniDoc las provoca alguien: se pulsa «Revertir» y sale el correo.
 * Estas no tienen a nadie detrás. El aviso de «faltan 30 días para el cierre» debe salir un día
 * concreto en el que probablemente nadie entre al sistema, y PHP solo vive mientras atiende una
 * petición: sin un proceso permanente que mire la hora, ese correo no saldría jamás.
 *
 * De ahí que este comando lo dispare el planificador (`schedule:work`, declarado en
 * `routes/console.php` y arrancado por `docker-entrypoint.sh`) una vez al día.
 *
 * **Ninguna fecha está escrita en el código.** El comando pregunta cada día si algún periodo
 * abierto cae a la distancia de uno de los hitos, calculando contra la `fecha_cierre` que el
 * periodo tenga en ese momento. Si alguien mueve esa fecha, los avisos se mueven con ella sin
 * tocar nada.
 */
class AvisosCicloEscalafon extends Command
{
    protected $signature = 'escalafon:avisos-ciclo
                            {--simular : Muestra lo que se enviaría sin encolar nada}';

    protected $description = 'Envía los avisos del ciclo de ascenso que dependen de la fecha de cierre';

    /**
     * Días antes del cierre en que se avisa, y a quién.
     *
     * 30 días es el margen para completar un expediente; 15 es el punto en que la cola del
     * Evaluador ya no da para más; 7 es el último aviso útil.
     */
    private const HITOS = [
        30 => ['docente', 'apoyo'],
        15 => ['evaluador'],
        7  => ['docente', 'apoyo'],
    ];

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $hoy = Carbon::today();

        $this->info("Avisos del ciclo de escalafón — {$hoy->toDateString()}" . ($simular ? ' (simulación)' : ''));

        $periodos = PeriodoAscenso::whereNull('cerrado_en')
            ->whereDate('fecha_cierre', '>=', $hoy->toDateString())
            ->get();

        if ($periodos->isEmpty()) {
            $this->line('  No hay periodos abiertos. Nada que avisar.');

            return self::SUCCESS;
        }

        $enviados = 0;

        foreach ($periodos as $periodo) {
            // `diffInDays` con la fecha de hoy: 30 significa exactamente 30, no «30 o menos».
            // Si fuera «o menos», el aviso saldría todos los días desde el día 30 hasta el cierre.
            $faltan = (int) $hoy->diffInDays($periodo->fecha_cierre, false);

            if (!array_key_exists($faltan, self::HITOS)) {
                $this->line("  «{$periodo->nombre}» cierra en {$faltan} días — no es un hito.");
                continue;
            }

            $this->line("  «{$periodo->nombre}» cierra en {$faltan} días — hito alcanzado.");

            foreach (self::HITOS[$faltan] as $destinatario) {
                $enviados += match ($destinatario) {
                    'apoyo'     => $this->avisarApoyoProfesoral($periodo, $faltan, $simular),
                    'evaluador' => $this->avisarEvaluador($periodo, $faltan, $simular),
                    'docente'   => $this->avisarDocentes($periodo, $faltan, $simular),
                };
            }
        }

        $this->info("  Total: {$enviados} avisos " . ($simular ? 'simulados.' : 'encolados.'));

        return self::SUCCESS;
    }

    /**
     * C14 y C15 — la cola de revisión de Apoyo Profesoral ante el cierre.
     *
     * Es el aviso que faltaba en el diseño original: al Evaluador se le decía que su cola bloquea
     * ascensos y a Apoyo Profesoral no, aun revisando siete categorías de documentos frente a una.
     * Si no alcanza a revisar antes del cierre, el docente pierde el ciclo por causa administrativa.
     */
    private function avisarApoyoProfesoral(PeriodoAscenso $periodo, int $faltan, bool $simular): int
    {
        $pendientes = Documento::where('estado', 'pendiente')
            ->where('documentable_type', 'not like', '%ProduccionAcademica%')
            ->count();

        if ($pendientes === 0) {
            $this->line('    Apoyo Profesoral: sin documentos pendientes, no se avisa.');

            return 0;
        }

        $docentesAfectados = Documento::where('estado', 'pendiente')
            ->where('documentable_type', 'not like', '%ProduccionAcademica%')
            ->distinct('documentable_id')
            ->count('documentable_id');

        $detalles = [
            'Pendientes'  => "{$pendientes} documentos, de {$docentesAfectados} registros",
            'Cierre'      => $periodo->fecha_cierre->format('d/m/Y'),
            'Faltan'      => "{$faltan} días",
        ];

        return $this->encolar(
            destinatarios: User::role('Apoyo Profesoral')->get(),
            clave: "escalafon.cola-apoyo:periodo:{$periodo->id_periodo_ascenso}:dias:{$faltan}",
            tipo: 'escalafon.cola-apoyo',
            metodo: 'colaPendienteAnteCierre',
            args: [$periodo->nombre, $periodo->fecha_cierre->format('d/m/Y'), $faltan, $pendientes, $detalles],
            etiqueta: "Apoyo Profesoral: {$pendientes} documentos pendientes",
            simular: $simular,
        );
    }

    /**
     * C12 — la cola del Evaluador de Producción.
     *
     * El puntaje de producción solo cuenta si el aval está registrado antes del cierre, así que
     * una producción sin avalar el día del corte es un ascenso que no ocurre.
     */
    private function avisarEvaluador(PeriodoAscenso $periodo, int $faltan, bool $simular): int
    {
        $pendientes = ProduccionAcademica::whereHas(
            'documentosProduccionAcademica',
            fn ($q) => $q->where('estado', 'pendiente')
        )->count();

        if ($pendientes === 0) {
            $this->line('    Evaluador: sin producción pendiente, no se avisa.');

            return 0;
        }

        $docentesAfectados = ProduccionAcademica::whereHas(
            'documentosProduccionAcademica',
            fn ($q) => $q->where('estado', 'pendiente')
        )->distinct('user_id')->count('user_id');

        $detalles = [
            'Pendientes' => "{$pendientes} producciones, de {$docentesAfectados} docentes",
            'Cierre'     => $periodo->fecha_cierre->format('d/m/Y'),
            'Faltan'     => "{$faltan} días",
        ];

        return $this->encolar(
            destinatarios: User::role('Evaluador Produccion')->get(),
            clave: "escalafon.cola-evaluador:periodo:{$periodo->id_periodo_ascenso}:dias:{$faltan}",
            tipo: 'escalafon.cola-evaluador',
            metodo: 'colaPendienteAnteCierre',
            args: [$periodo->nombre, $periodo->fecha_cierre->format('d/m/Y'), $faltan, $pendientes, $detalles],
            etiqueta: "Evaluador: {$pendientes} producciones pendientes",
            simular: $simular,
        );
    }

    /**
     * C4 y C5 — el recordatorio al docente.
     *
     * Segmentado a propósito: solo a quien está dentro del escalafón. A quien no ha ingresado, el
     * aviso no le dice nada que pueda hacer.
     */
    private function avisarDocentes(PeriodoAscenso $periodo, int $faltan, bool $simular): int
    {
        $docentes = User::role('Docente')
            ->whereHas('historialEscalonUsuario', fn ($q) => $q->whereNull('hasta')->whereNull('revertido_en'))
            ->get();

        if ($docentes->isEmpty()) {
            $this->line('    Docentes: ninguno dentro del escalafón, no se avisa.');

            return 0;
        }

        $enviados = 0;

        foreach ($docentes as $docente) {
            $enviados += $this->encolar(
                destinatarios: collect([$docente]),
                // La clave lleva la fecha de cierre: si alguien mueve el cierre, cambia la clave y
                // el aviso puede volver a salir, porque lo que dijimos antes quedó obsoleto.
                clave: "escalafon.recordatorio:periodo:{$periodo->id_periodo_ascenso}:{$periodo->fecha_cierre->toDateString()}:dias:{$faltan}:user:{$docente->id}",
                tipo: 'escalafon.recordatorio',
                metodo: 'cierrePeriodoProximo',
                args: [$periodo->nombre, $periodo->fecha_cierre->format('d/m/Y'), $faltan],
                etiqueta: null,
                simular: $simular,
            );
        }

        $this->line("    Docentes: {$enviados} recordatorios.");

        return $enviados;
    }

    /**
     * Encola un aviso por destinatario.
     *
     * El job resuelve el usuario y antepone el modelo, así que aquí solo viaja su identificador.
     * La idempotencia la garantiza `NotificacionEnviada`: si este comando se ejecutara dos veces
     * el mismo día —un reintento, dos réplicas del contenedor— la segunda no envía nada.
     */
    private function encolar(
        $destinatarios,
        string $clave,
        string $tipo,
        string $metodo,
        array $args,
        ?string $etiqueta,
        bool $simular
    ): int {
        $n = 0;

        foreach ($destinatarios as $destinatario) {
            $claveUnica = str_contains($clave, ':user:') ? $clave : "{$clave}:user:{$destinatario->id}";

            if ($simular) {
                $this->line("    [simulado] {$claveUnica}");
                $n++;
                continue;
            }

            EnviarNotificacionJob::dispatch($claveUnica, $tipo, $metodo, $args, $destinatario->id);
            $n++;
        }

        if ($etiqueta && !$simular) {
            $this->line("    {$etiqueta} → {$n} destinatario(s)");
        }

        return $n;
    }
}
