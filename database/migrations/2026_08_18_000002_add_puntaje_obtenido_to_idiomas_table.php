<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puntaje bruto del certificado de idioma (IELTS 7.5, DELF 82, ITLS 250...).
 *
 * Es la pieza que faltaba para que el catálogo de rangos (`examenes_idioma_rangos`, que ya
 * administra el rol Administrador) sirva de algo: hasta ahora el docente autodeclaraba su nivel
 * MCER de una lista, sin puntaje ni evidencia, y la tabla de equivalencias no se usaba en
 * ninguna parte. Con el puntaje, `idiomas.nivel` pasa a calcularse en el servidor a partir del
 * rango que corresponda, y Apoyo Profesoral puede contrastarlo contra el documento adjunto.
 *
 * Nullable a propósito: los idiomas ya registrados conservan su nivel autodeclarado sin
 * puntaje, y los exámenes que no están en el catálogo (texto libre, sin rangos) siguen
 * permitiendo elegir el nivel a mano.
 *
 * decimal(6,2) cubre tanto escalas pequeñas (IELTS 0–9, con medios puntos) como grandes
 * (ITLS 234–260, TOEFL iBT 0–120).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idiomas', function (Blueprint $table) {
            $table->decimal('puntaje_obtenido', 6, 2)->nullable()->after('examen_idioma_id');
        });
    }

    public function down(): void
    {
        Schema::table('idiomas', function (Blueprint $table) {
            $table->dropColumn('puntaje_obtenido');
        });
    }
};
