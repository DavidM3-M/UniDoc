<?php

namespace Database\Seeders;

use App\Models\EscalonDocente;
use App\Models\ReglaExcepcionEscalon;
use Illuminate\Database\Seeder;

/**
 * Siembra la única excepción que ya regía hardcodeada en
 * `CalculoPuntajeDocenteService::evaluar()`: un docente con Doctorado aprobado queda como
 * mínimo en Asociado, aunque no cumpla el resto de sus requisitos.
 */
class ReglaExcepcionEscalonSeeder extends Seeder
{
    public function run(): void
    {
        $asociado = EscalonDocente::where('nombre', 'Asociado')->first();

        if (!$asociado) {
            return;
        }

        ReglaExcepcionEscalon::updateOrCreate(
            ['tipo_condicion' => 'formacion', 'valor_condicion' => 'Doctorado'],
            ['escalon_otorgado_id' => $asociado->id_escalon, 'activo' => true]
        );
    }
}
