<?php
// Importa la clase ClosureCommand para definir comandos personalizados en la consola
use Illuminate\Foundation\Console\ClosureCommand;
// Importa la clase Inspiring para obtener citas inspiradoras
use Illuminate\Foundation\Inspiring;
// Importa la clase Artisan para registrar comandos de consola
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
// Define un comando de consola llamado 'inspire'
Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    // Muestra una cita inspiradora en la consola
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Establece la descripción del propósito del comando

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Esto es una declaración, no una ejecución: dice qué correr y cuándo, pero no corre nada por sí
| solo. Quien la consulta es `php artisan schedule:work`, el proceso que `docker-entrypoint.sh`
| mantiene vivo junto a Apache y al worker de colas.
|
| Los avisos del ciclo de ascenso no los provoca ninguna acción de usuario: el día que toca
| enviarlos puede que nadie entre al sistema, y PHP solo vive mientras atiende una petición. Sin un
| proceso permanente mirando la hora, esos correos no saldrían nunca.
|
| A las 07:00 y no a medianoche para que el correo llegue cuando alguien lo va a leer, y porque a
| esa hora ya cerró el día anterior: los documentos revisados ayer cuentan en el conteo de hoy.
*/
Schedule::command('escalafon:avisos-ciclo')
    ->dailyAt('07:00')
    // Si una ejecución se alarga, la siguiente no se solapa con ella: dos procesos calculando los
    // mismos avisos a la vez es justo lo que `NotificacionEnviada` tendría que atajar después.
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/escalafon-avisos.log'));

/*
| El resumen del expediente sale a las 18:00, no por la mañana: agrupa lo revisado durante la
| jornada, así que enviarlo al final del día es lo que hace que un docente reciba un correo en vez
| de cuatro. A las 07:00 solo alcanzaría lo del día anterior y llegaría tarde.
*/
Schedule::command('expediente:resumen-diario')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/expediente-resumen.log'));

Schedule::command('escalafon:notificar-cierres')
    ->dailyAt('07:05')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/escalafon-cierres.log'));
