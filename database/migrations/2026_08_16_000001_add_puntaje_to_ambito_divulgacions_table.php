<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puntaje que otorga cada ámbito de divulgación a la producción académica del docente.
 *
 * Reemplaza `CalculoPuntajeDocenteService::clasificacionPorAmbito()`, que hasta ahora
 * hardcodeaba un `match` de IDs de ámbito en tres niveles fijos (10/6/3 puntos) y dejaba en 0 a
 * cualquier ámbito creado desde el catálogo de Producción académica.
 *
 * Los IDs existentes se rellenan aquí mismo con el puntaje que ya tenían bajo el `match`
 * hardcodeado: sin este `update`, el puntaje de cada docente caería a 0 apenas se despliegue,
 * porque la columna nueva nace en 0 por defecto.
 */
return new class extends Migration
{
    private const PUNTAJE_TOP = [1, 20, 21, 25, 27, 29, 34, 46, 50, 54, 62, 65];
    private const PUNTAJE_A = [2, 12, 15, 26, 28, 30, 35, 38, 47, 51, 55, 63, 66, 73, 45];
    private const PUNTAJE_B = [3, 13, 16, 19, 31, 36, 39, 48, 52, 56, 64, 41, 42];

    public function up(): void
    {
        Schema::table('ambito_divulgacions', function (Blueprint $table) {
            $table->smallInteger('puntaje')->default(0)->after('activo');
        });

        DB::table('ambito_divulgacions')->whereIn('id_ambito_divulgacion', self::PUNTAJE_TOP)->update(['puntaje' => 10]);
        DB::table('ambito_divulgacions')->whereIn('id_ambito_divulgacion', self::PUNTAJE_A)->update(['puntaje' => 6]);
        DB::table('ambito_divulgacions')->whereIn('id_ambito_divulgacion', self::PUNTAJE_B)->update(['puntaje' => 3]);
    }

    public function down(): void
    {
        Schema::table('ambito_divulgacions', function (Blueprint $table) {
            $table->dropColumn('puntaje');
        });
    }
};
