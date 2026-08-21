<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puntaje de cada ámbito de divulgación.
 *
 * La migración `2026_08_16_000001_add_puntaje_to_ambito_divulgacions_table` ya hacía este
 * relleno, pero en la práctica nunca surte efecto: `docker-entrypoint.sh` corre
 * `php artisan migrate` **antes** de `db:seed`, así que el `UPDATE` de la migración se ejecuta
 * sobre una tabla vacía y después `AmbitoDivulgacionSeeder` inserta los 82 ámbitos desde el CSV
 * sin puntaje, quedando todos en 0.
 *
 * Con todos los ámbitos en 0, `MotorEscalafonDocenteService::calcularPuntaje()` devuelve 0 para
 * cualquier docente y ningún escalón con `puntaje_minimo` se puede alcanzar jamás.
 *
 * Este seeder corre después del CSV y aplica los mismos tres niveles que definía la migración.
 * Solo toca los ámbitos que siguen en 0: si el Administrador ya ajustó un puntaje desde
 * Catálogos → Producción académica, se respeta.
 */
class AmbitoDivulgacionPuntajeSeeder extends Seeder
{
    /** Mismos IDs y valores que la migración: revista/libro de mayor impacto. */
    private const PUNTAJE_TOP = [1, 20, 21, 25, 27, 29, 34, 46, 50, 54, 62, 65];
    private const PUNTAJE_A = [2, 12, 15, 26, 28, 30, 35, 38, 47, 51, 55, 63, 66, 73, 45];
    private const PUNTAJE_B = [3, 13, 16, 19, 31, 36, 39, 48, 52, 56, 64, 41, 42];

    public function run(): void
    {
        $niveles = [
            10 => self::PUNTAJE_TOP,
            6 => self::PUNTAJE_A,
            3 => self::PUNTAJE_B,
        ];

        $actualizados = 0;

        foreach ($niveles as $puntaje => $ids) {
            $actualizados += DB::table('ambito_divulgacions')
                ->whereIn('id_ambito_divulgacion', $ids)
                ->where('puntaje', 0)
                ->update(['puntaje' => $puntaje]);
        }

        $enCero = DB::table('ambito_divulgacions')->where('puntaje', 0)->count();

        $this->command?->info("Puntajes de ámbito asignados: {$actualizados}. Siguen en 0: {$enCero} (el Administrador les puede dar valor desde Catálogos → Producción académica).");
    }
}
