<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EliminarDocumentos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:eliminar-documentos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina todos los archivos dentro de las subcarpetas de storage/app/public/documentos sin borrar las carpetas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ruta = 'app/public/documentos';

        $storagePath = storage_path($ruta);

        if (!is_dir($storagePath)) {
            $this->warn("La ruta {$storagePath} no existe.");
            return Command::FAILURE;
        }

        // Obtener subcarpetas dentro de documentos
        $carpetas = array_filter(glob($storagePath . '/*'), 'is_dir');

        foreach ($carpetas as $carpeta) {
            $archivos = glob($carpeta . '/*');

            foreach ($archivos as $archivo) {
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }

            $this->info("Archivos eliminados de: {$carpeta}");
        }

        $this->info("Limpieza de documentos completada.");
        return Command::SUCCESS;
    }
}
