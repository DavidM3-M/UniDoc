<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('convocatorias', 'numero_convocatoria')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('numero_convocatoria')->nullable()->after('estado_convocatoria');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'periodo_academico')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('periodo_academico')->nullable()->after('numero_convocatoria');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'tipo_cargo_id')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->unsignedInteger('tipo_cargo_id')->nullable()->after('periodo_academico');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'tipo_cargo_otro')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('tipo_cargo_otro')->nullable()->after('tipo_cargo_id');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'facultad_id')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->unsignedInteger('facultad_id')->nullable()->after('tipo_cargo_otro');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'facultad_otro')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('facultad_otro')->nullable()->after('facultad_id');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'cursos')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->text('cursos')->nullable()->after('facultad_otro');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'tipo_vinculacion')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('tipo_vinculacion')->nullable()->after('cursos');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'personas_requeridas')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->integer('personas_requeridas')->default(1)->after('tipo_vinculacion');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'fecha_inicio_contrato')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->date('fecha_inicio_contrato')->nullable()->after('personas_requeridas');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'perfil_profesional_id')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->unsignedInteger('perfil_profesional_id')->nullable()->after('fecha_inicio_contrato');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'perfil_profesional_otro')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('perfil_profesional_otro')->nullable()->after('perfil_profesional_id');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'experiencia_requerida_fecha')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->date('experiencia_requerida_fecha')->nullable()->after('perfil_profesional_otro');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'experiencia_requerida_id')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->unsignedInteger('experiencia_requerida_id')->nullable()->after('experiencia_requerida_fecha');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'solicitante')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('solicitante')->nullable()->after('experiencia_requerida_id');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'requisitos_experiencia')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->json('requisitos_experiencia')->nullable()->after('solicitante');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'requisitos_idiomas')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->json('requisitos_idiomas')->nullable()->after('requisitos_experiencia');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'requisitos_adicionales')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->json('requisitos_adicionales')->nullable()->after('requisitos_idiomas');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'anos_experiencia_requerida')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->integer('anos_experiencia_requerida')->nullable()->after('requisitos_adicionales');
            });
        }

        if (!Schema::hasColumn('convocatorias', 'tipo_experiencia_requerida')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('tipo_experiencia_requerida')->nullable()->after('anos_experiencia_requerida');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn([
                'numero_convocatoria', 'periodo_academico', 'tipo_cargo_id', 'tipo_cargo_otro', 'facultad_id', 'facultad_otro',
                'cursos', 'tipo_vinculacion', 'personas_requeridas', 'fecha_inicio_contrato', 'perfil_profesional_id', 'perfil_profesional_otro',
                'experiencia_requerida_fecha', 'experiencia_requerida_id', 'solicitante', 'requisitos_experiencia', 'requisitos_idiomas',
                'requisitos_adicionales', 'anos_experiencia_requerida', 'tipo_experiencia_requerida'
            ]);
        });
    }
};
