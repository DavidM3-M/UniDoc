<?php

namespace App\Console\Commands;

use App\Models\PeriodoAscenso;
use App\Services\NotificacionesPeriodoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotificarCierresPeriodos extends Command
{
    protected $signature = 'escalafon:notificar-cierres {--simular : No encola correos}';
    protected $description = 'Notifica los periodos vencidos, incluyendo cierres ocurridos con el planificador detenido';

    public function handle(NotificacionesPeriodoService $avisos): int
    {
        $fallos = 0;
        foreach (PeriodoAscenso::whereDate('fecha_cierre', '<', today()->toDateString())->cursor() as $periodo) {
            if ($this->option('simular')) {
                $this->line("Periodo {$periodo->id_periodo_ascenso}: cierre pendiente de comprobar");
                continue;
            }
            try {
                $avisos->avisarCierre($periodo);
            } catch (\Throwable $e) {
                $fallos++;
                Log::error("No se pudo preparar el cierre {$periodo->id_periodo_ascenso}: " . $e->getMessage());
            }
        }
        return $fallos ? self::FAILURE : self::SUCCESS;
    }
}
