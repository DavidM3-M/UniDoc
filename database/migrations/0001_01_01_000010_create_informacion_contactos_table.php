<?php

use App\Constants\ConstInformacionContacto\CategoriaLibretaMilitar;
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
        Schema::create('informacion_contactos', function (Blueprint $table) {
            $table->smallIncrements('id_informacion_contacto');
            $table->unsignedBigInteger('user_id');//relacion de muchos a uno con la tabla users
            $table->unsignedSmallInteger('municipio_id');//relacion de muchos a uno con la tabla municipios
            $table->string('categoria_libreta_militar')->nullable();
            $table->string('numero_libreta_militar')->nullable();
            $table->string('numero_distrito_militar')->nullable();
            $table->string('direccion_residencia')->nullable();
            $table->string('barrio')->nullable();
            $table->string('telefono_movil');
            $table->string('celular_alternativo')->nullable();
            $table->string('correo_alterno')->nullable();
            $table->timestamps();

            //llave foranea de la tabla municipios
            $table->foreign('municipio_id')
                ->references('id_municipio')
                ->on('municipios');
            //llave foranea de la tabla users
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar informacion_contactos si se elimina el usuario
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informacion_contactos');
    }
};
