<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reemplazada por la relación muchos-a-muchos rut_responsabilidad_tributaria
     * (un RUT puede tener varias responsabilidades tributarias).
     */
    public function up(): void
    {
        Schema::table('ruts', function (Blueprint $table) {
            $table->dropColumn('responsabilidades_tributarias');
        });
    }

    public function down(): void
    {
        Schema::table('ruts', function (Blueprint $table) {
            $table->string('responsabilidades_tributarias')->nullable();
        });
    }
};
