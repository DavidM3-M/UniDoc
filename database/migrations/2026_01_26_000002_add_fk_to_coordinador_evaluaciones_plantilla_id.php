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
            $table->foreign('plantilla_id')
                ->references('id')
                ->on('coordinador_plantillas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coordinador_evaluaciones', function (Blueprint $table) {
            $table->dropForeign(['plantilla_id']);
        });
    }
};
