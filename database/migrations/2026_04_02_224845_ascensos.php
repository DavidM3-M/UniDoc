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
        Schema::create('ascensos' , function (Blueprint $table){

            $table -> id('id_ascenso');
            $table -> unsignedBigInteger('user_id');
            $table -> unsignedBigInteger('id_convocatoria');
            $table -> string('nuevo_cargo');
            $table -> string('area');
            $table -> date('fecha_ascenso');
            $table -> text('observaciones') -> nullable();
            $table -> timestamps();
            
            $table -> foreign('user_id') -> references('id') -> on('users') -> onDelete('cascade');
            $table -> foreign('id_convocatoria') -> references('id_convocatoria') -> on('convocatorias') -> onDelete('cascade');

        });
     
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ascensos');
    }
};
