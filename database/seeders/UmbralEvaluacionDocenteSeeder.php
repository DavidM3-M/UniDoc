<?php

namespace Database\Seeders;

use App\Models\Docente\UmbralEvaluacionDocente;
use Illuminate\Database\Seeder;

/**
 * Siembra el umbral inicial de evaluación docente.
 *
 * Se registra 4.0, el valor que estuvo hardcodeado en CalculoPuntajeDocenteService,
 * para que volver el umbral configurable no cambie el comportamiento del sistema.
 */
class UmbralEvaluacionDocenteSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: si ya hay un umbral vigente no se toca, para no pisar
        // un valor que el Administrador haya configurado.
        if (UmbralEvaluacionDocente::vigente()->exists()) {
            return;
        }

        UmbralEvaluacionDocente::create([
            'valor_minimo'   => 4.0,
            'vigencia_desde' => now()->toDateString(),
            'vigencia_hasta' => null,
            'creado_por'     => null, // Valor de sistema, no lo registró ningún administrador.
            'observaciones'  => 'Umbral inicial, equivalente al valor que estaba fijo en el código.',
        ]);
    }
}
