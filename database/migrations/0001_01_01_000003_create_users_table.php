<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Constants\ConstUsuario\TipoIdentificacion;
use App\Constants\ConstUsuario\Genero;
use App\Constants\ConstUsuario\EstadoCivil;




return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // tabla para gestionar los usuarios
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('municipio_id');
            $table->string('tipo_identificacion');
            $table->string('numero_identificacion')->unique();
            $table->string('genero')->nullable();
            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido')->nullable();
            $table->date('fecha_nacimiento');
            $table->string('estado_civil')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken(); //para mantener la sesión abierta
            $table->timestamps();
            
            $table->foreign('municipio_id')
                ->references('id_municipio')
                ->on('municipios');

            // Campos de avales
            $table->boolean('aval_rectoria')->default(false);
            $table->unsignedSmallInteger('aval_rectoria_by')->nullable();
            $table->timestamp('aval_rectoria_at')->nullable();

            $table->boolean('aval_vicerrectoria')->default(false);
            $table->unsignedSmallInteger('aval_vicerrectoria_by')->nullable();
            $table->timestamp('aval_vicerrectoria_at')->nullable();

            $table->boolean('aval_talento_humano')->default(false);
            $table->unsignedSmallInteger('aval_talento_humano_by')->nullable();
            $table->timestamp('aval_talento_humano_at')->nullable();

        });
        

        // tabla para restablecer la contraseña

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });


        //Gestiona la sesion activa del usuario

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
