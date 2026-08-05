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
        if (Schema::hasColumn('convocatorias', 'perfil_profesional')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->dropColumn('perfil_profesional');
            });
        }

        if (Schema::hasColumn('convocatorias', 'experiencia_requerida')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->dropColumn('experiencia_requerida');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversing; these were cleanup columns
    }
};
