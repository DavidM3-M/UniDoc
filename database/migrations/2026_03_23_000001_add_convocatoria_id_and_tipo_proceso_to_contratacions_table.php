<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega convocatoria_id y tipo_proceso a la tabla contratacions.
     *
     * - convocatoria_id: vincula cada contratación a un proceso específico,
     *   permitiendo que un usuario tenga múltiples contrataciones (una por convocatoria).
     * - tipo_proceso: distingue entre contratación nueva, ascenso y cambio de cargo.
     */
    public function up(): void
    {
        Schema::table('contratacions', function (Blueprint $table) {
            if (! Schema::hasColumn('contratacions', 'convocatoria_id')) {
                // smallIncrements en convocatorias → unsignedSmallInteger aquí para que el FK coincida
                $table->unsignedSmallInteger('convocatoria_id')
                    ->nullable()
                    ->after('user_id')
                    ->comment('Convocatoria asociada a esta contratación/ascenso/cambio de cargo');
            }

            if (! Schema::hasColumn('contratacions', 'tipo_proceso')) {
                $table->string('tipo_proceso')
                    ->default('Contratacion')
                    ->after('convocatoria_id')
                    ->comment('Contratacion | Ascenso | CambioCargo');
            }
        });

        // FK y unique se agregan solo si no existen (sintaxis compatible con PostgreSQL)
        $hasFk = DB::select("
            SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_type = 'FOREIGN KEY'
              AND table_name = 'contratacions'
              AND constraint_name = 'contrataciones_convocatoria_fk'
        ");
        if (empty($hasFk)) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contrataciones_convocatoria_fk FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id_convocatoria) ON DELETE SET NULL');
        }

        $hasUnique = DB::select("
            SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_type = 'UNIQUE'
              AND table_name = 'contratacions'
              AND constraint_name = 'contratacion_user_convocatoria_unique'
        ");
        if (empty($hasUnique)) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contratacion_user_convocatoria_unique UNIQUE (user_id, convocatoria_id)');
        }
    }

    public function down(): void
    {
        Schema::table('contratacions', function (Blueprint $table) {
            $table->dropUnique('contratacion_user_convocatoria_unique');
            $table->dropForeign(['convocatoria_id']);
            $table->dropColumn(['convocatoria_id', 'tipo_proceso']);
        });
    }
};
