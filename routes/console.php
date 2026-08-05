<?php
// Importa la clase ClosureCommand para definir comandos personalizados en la consola
use Illuminate\Foundation\Console\ClosureCommand;
// Importa la clase Inspiring para obtener citas inspiradoras
use Illuminate\Foundation\Inspiring;
// Importa la clase Artisan para registrar comandos de consola
use Illuminate\Support\Facades\Artisan;
// Define un comando de consola llamado 'inspire'
Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    // Muestra una cita inspiradora en la consola
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Establece la descripción del propósito del comando
