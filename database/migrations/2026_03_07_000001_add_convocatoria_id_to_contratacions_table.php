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
        // Solo crear la migración si la tabla y columna no existen
        if (Schema::hasTable('contratacions')) {
            if (!Schema::hasColumn('contratacions', 'convocatoria_id')) {
                Schema::table('contratacions', function (Blueprint $table) {
                    $table->unsignedSmallInteger('convocatoria_id')->nullable()->after('user_id');
                    
                    $table->foreign('convocatoria_id')
                        ->references('id_convocatoria')
                        ->on('convocatorias')
                        ->onDelete('set null');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratacions', function (Blueprint $table) {
            if (Schema::hasColumn('contratacions', 'convocatoria_id')) {
                $table->dropForeign(['convocatoria_id']);
                $table->dropColumn('convocatoria_id');
            }
        });
    }
};

