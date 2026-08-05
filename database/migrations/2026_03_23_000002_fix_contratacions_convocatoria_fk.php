<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Corregir tipo de convocatoria_id a SMALLINT UNSIGNED
        DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id SMALLINT UNSIGNED NULL COMMENT \'Convocatoria asociada a esta contratación/ascenso/cambio de cargo\'');

        // 2. Agregar FK si no existe
        $fks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'contratacions'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'contrataciones_convocatoria_fk'"
        ));

        if ($fks->isEmpty()) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contrataciones_convocatoria_fk FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id_convocatoria) ON DELETE SET NULL');
        }

        // 3. Agregar unique si no existe
        $indexes = collect(DB::select('SHOW INDEX FROM contratacions'))->pluck('Key_name');
        if (! $indexes->contains('contratacion_user_convocatoria_unique')) {
            DB::statement('ALTER TABLE contratacions ADD UNIQUE KEY contratacion_user_convocatoria_unique (user_id, convocatoria_id)');
        }
    }

    public function down(): void
    {
        $fks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'contratacions'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'contrataciones_convocatoria_fk'"
        ));
        if ($fks->isNotEmpty()) {
            DB::statement('ALTER TABLE contratacions DROP FOREIGN KEY contrataciones_convocatoria_fk');
        }

        $indexes = collect(DB::select('SHOW INDEX FROM contratacions'))->pluck('Key_name');
        if ($indexes->contains('contratacion_user_convocatoria_unique')) {
            DB::statement('ALTER TABLE contratacions DROP INDEX contratacion_user_convocatoria_unique');
        }

        DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id BIGINT UNSIGNED NULL');
    }
};

