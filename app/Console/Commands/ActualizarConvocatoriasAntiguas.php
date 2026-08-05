<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TalentoHumano\Convocatoria;
use Carbon\Carbon;

class ActualizarConvocatoriasAntiguas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convocatorias:actualizar-antiguas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza las convocatorias antiguas con los nuevos campos requeridos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando actualización de convocatorias antiguas...');

        try {
            // Obtener convocatorias que no tienen los nuevos campos
            $convocatorias = Convocatoria::whereNull('numero_convocatoria')->get();

            if ($convocatorias->isEmpty()) {
                $this->info('No hay convocatorias antiguas para actualizar.');
                return 0;
            }

            $this->info("Se encontraron {$convocatorias->count()} convocatorias para actualizar.");

            $bar = $this->output->createProgressBar($convocatorias->count());
            $bar->start();

            foreach ($convocatorias as $convocatoria) {
                $convocatoria->update([
                    'numero_convocatoria' => 'CONV-' . $convocatoria->id_convocatoria,
                    'periodo_academico' => '2024-1',
                    'cargo_solicitado' => 'Por definir',
                    'facultad' => 'Por definir',
                    'cursos' => 'Por definir',
                    'tipo_vinculacion' => 'Por definir',
                    'personas_requeridas' => 1,
                    'fecha_inicio_contrato' => Carbon::parse($convocatoria->fecha_cierre)->addDays(7),
                    'perfil_profesional' => 'Por definir',
                    'experiencia_requerida' => 'Por definir',
                    'solicitante' => 'Talento Humano',
                    'aprobaciones' => 'Pendiente',
                ]);

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('✅ Convocatorias actualizadas exitosamente!');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error al actualizar convocatorias: ' . $e->getMessage());
            return 1;
        }
    }
}
