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
        Schema::table('coordinador_evaluaciones', function (Blueprint $table) {
            $table->boolean('aprobado')->default(false)->after('clase_organizada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coordinador_evaluaciones', function (Blueprint $table) {
            $table->dropColumn('aprobado');
        });
    }
};
