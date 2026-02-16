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
            // Campos para detalles de experiencia requerida
            if (!Schema::hasColumn('convocatorias', 'cantidad_experiencia')) {
                $table->integer('cantidad_experiencia')->nullable()->after('tipo_experiencia_requerida')->comment('Cantidad de experiencia requerida (ej: 2)');
            }

            if (!Schema::hasColumn('convocatorias', 'unidad_experiencia')) {
                $table->string('unidad_experiencia')->nullable()->after('cantidad_experiencia')->comment('Unidad de tiempo (Años, Meses, Semanas)');
            }

            if (!Schema::hasColumn('convocatorias', 'referencia_experiencia')) {
                $table->text('referencia_experiencia')->nullable()->after('unidad_experiencia')->comment('Referencia o contexto del cargo/función');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            if (Schema::hasColumn('convocatorias', 'cantidad_experiencia')) {
                $table->dropColumn('cantidad_experiencia');
            }
            if (Schema::hasColumn('convocatorias', 'unidad_experiencia')) {
                $table->dropColumn('unidad_experiencia');
            }
            if (Schema::hasColumn('convocatorias', 'referencia_experiencia')) {
                $table->dropColumn('referencia_experiencia');
            }
        });
    }
};
