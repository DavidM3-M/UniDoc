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
        Schema::table('convocatorias', function (Blueprint $table) {
            // Nuevos campos
            $table->string('numero_convocatoria')->nullable()->after('id_convocatoria');
            $table->string('periodo_academico')->nullable()->after('tipo');
            $table->string('cargo_solicitado')->nullable()->after('periodo_academico');
            $table->string('facultad')->nullable()->after('cargo_solicitado');
            $table->text('cursos')->nullable()->after('facultad');
            $table->string('tipo_vinculacion')->nullable()->after('cursos');
            $table->unsignedSmallInteger('personas_requeridas')->default(1)->after('tipo_vinculacion');
            $table->date('fecha_inicio_contrato')->nullable()->after('fecha_cierre');
            $table->text('perfil_profesional')->nullable()->after('descripcion');
            $table->text('experiencia_requerida')->nullable()->after('perfil_profesional');
            $table->string('solicitante')->nullable()->after('experiencia_requerida');
            $table->text('aprobaciones')->nullable()->after('solicitante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn([
                'numero_convocatoria',
                'periodo_academico',
                'cargo_solicitado',
                'facultad',
                'cursos',
                'tipo_vinculacion',
                'personas_requeridas',
                'fecha_inicio_contrato',
                'perfil_profesional',
                'experiencia_requerida',
                'solicitante',
                'aprobaciones',
            ]);
        });
    }
};
