<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_ciiu', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->text('descripcion');
            $table->string('grupo', 10)->nullable();
            $table->string('division', 10)->nullable();
            $table->string('seccion', 5)->nullable();
            $table->string('seccion_titulo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_ciiu');
    }
};
