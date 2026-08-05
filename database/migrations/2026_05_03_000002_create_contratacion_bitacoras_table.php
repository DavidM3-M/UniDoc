<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de bitácora de contrataciones para cumplimiento legal.
     * Cada fila es inmutable: registra quién modificó el contrato, cuándo,
     * qué cambió (snapshot JSON antes/después) y el motivo declarado.
     */
    public function up(): void
    {
        Schema::create('contratacion_bitacoras', function (Blueprint $table) {
            $table->bigIncrements('id_bitacora');

            // Referencia al contrato afectado (CASCADE: si se borra el contrato, se borra su historial)
            $table->unsignedSmallInteger('contratacion_id')->nullable();
            $table->foreign('contratacion_id')
                ->references('id_contratacion')
                ->on('contratacions')
                ->onDelete('set null');

            // Usuario que realizó la acción (SET NULL si se elimina el usuario)
            $table->unsignedBigInteger('user_modifico_id')->nullable();
            $table->foreign('user_modifico_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Tipo de operación
            $table->string('tipo_modificacion', 20)
                ->comment('creacion | actualizacion | eliminacion');

            // Snapshots del contrato — null en creación (no hay datos anteriores)
            // null en eliminación (no hay datos nuevos)
            $table->jsonb('datos_anteriores')->nullable();
            $table->jsonb('datos_nuevos')->nullable();

            // Motivo legal declarado por el operador (requerido en actualización/eliminación)
            $table->text('motivo')->nullable();

            // Solo created_at — registro inmutable, no se actualiza
            $table->timestamp('created_at')->useCurrent();
        });

        // Índices para consultas frecuentes (por contrato, por fecha, por tipo)
        DB::statement('CREATE INDEX idx_bitacora_contratacion ON contratacion_bitacoras (contratacion_id)');
        DB::statement('CREATE INDEX idx_bitacora_fecha ON contratacion_bitacoras (created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacion_bitacoras');
    }
};
