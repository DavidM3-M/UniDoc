<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jerarquía de los niveles de formación, para que el escalafón pueda exigir "al menos este
 * nivel" en vez de una coincidencia exacta.
 *
 * Sin esto, `MotorEscalafonDocenteService::tieneFormacionAprobada()` comparaba por igualdad de
 * texto: un docente con Doctorado NO cumplía un requisito de Maestría, aunque tenga más
 * formación de la pedida. Es el mismo problema que los idiomas ya tenían resuelto con la escala
 * MCER (A1=1 … C2=6); a la formación le faltaba su equivalente.
 *
 * `orden` nulo significa "no participa en el escalafón": es formación que se registra en la
 * hoja de vida (Diplomado, Certificación, Curso) pero que no asciende a nadie. Así un mismo
 * catálogo sirve para los dos usos, sin separar tablas.
 *
 * Los valores son administrables desde Catálogos → Niveles de formación: aquí solo se siembra
 * un orden inicial sugerido, siguiendo la estructura del sistema educativo colombiano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveles_formacion_academica', function (Blueprint $table) {
            $table->unsignedSmallInteger('orden')->nullable()->after('nivel_formacion');
        });

        // Backfill de los niveles que ya existen en la instalación. Va aquí y no en el seeder
        // a propósito: el seeder usa `firstOrCreate`, que solo asigna valores a filas nuevas y
        // por diseño no pisa lo que el Administrador haya ajustado a mano. La migración corre
        // una sola vez, así que es el lugar correcto para darle un orden inicial a lo que ya
        // estaba. Los niveles que no aparecen aquí quedan en null (no participan del escalafón).
        $ordenes = [
            'Técnico Profesional' => 10,
            'Formación técnica profesional' => 10,
            'Técnico' => 10,
            'Tecnológico' => 20,
            'Especialización técnico profesional' => 25,
            'Especialización tecnológica' => 27,
            'Universitario' => 30,
            'Pregrado' => 30,
            'Especialización' => 40,
            'Especialización universitaria' => 40,
            'Especialización médico quirúrgica' => 40,
            'Maestría' => 50,
            'Doctorado' => 60,
            'Postdoctorado' => 70,
        ];

        foreach ($ordenes as $nivelFormacion => $orden) {
            DB::table('niveles_formacion_academica')
                ->where('nivel_formacion', $nivelFormacion)
                ->update(['orden' => $orden]);
        }
    }

    public function down(): void
    {
        Schema::table('niveles_formacion_academica', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
