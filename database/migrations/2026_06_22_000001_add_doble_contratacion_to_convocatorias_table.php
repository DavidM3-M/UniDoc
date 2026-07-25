<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega campos para controlar la doble contratación en convocatorias
     */
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            // Permite indicar si esta convocatoria permite doble contratación
            if (!Schema::hasColumn('convocatorias', 'permite_doble_contratacion')) {
                $table->boolean('permite_doble_contratacion')->default(false)->after('estado_convocatoria');
            }
            
            // Notas sobre la política de doble contratación
            if (!Schema::hasColumn('convocatorias', 'notas_doble_contratacion')) {
                $table->text('notas_doble_contratacion')->nullable()->after('permite_doble_contratacion');
            }
            
            // Índice para búsquedas por doble contratación
            $table->index('permite_doble_contratacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropIndex(['permite_doble_contratacion']);
            $table->dropColumn(['permite_doble_contratacion', 'notas_doble_contratacion']);
        });
    }
};
