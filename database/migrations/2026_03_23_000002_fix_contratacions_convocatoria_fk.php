<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Corregir tipo de convocatoria_id a SMALLINT (PG no soporta UNSIGNED)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contratacions ALTER COLUMN convocatoria_id TYPE SMALLINT USING convocatoria_id::smallint');
        } else {
            DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id SMALLINT UNSIGNED NULL COMMENT \'Convocatoria asociada a esta contratación/ascenso/cambio de cargo\'');
        }

        // 2. Agregar FK si no existe
        $fks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.table_constraints
             WHERE constraint_type = 'FOREIGN KEY'
               AND table_name = 'contratacions'
               AND constraint_name = 'contrataciones_convocatoria_fk'"
        ));

        if ($fks->isEmpty()) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contrataciones_convocatoria_fk FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id_convocatoria) ON DELETE SET NULL');
        }

        // 3. Agregar unique si no existe
        if (DB::connection()->getDriverName() === 'pgsql') {
            $uniqueExists = collect(DB::select(
                "SELECT 1 FROM pg_constraint WHERE conname = 'contratacion_user_convocatoria_unique'"
            ));
        } else {
            $uniqueExists = collect(DB::select(
                "SELECT 1 FROM information_schema.table_constraints
                 WHERE constraint_type = 'UNIQUE'
                   AND table_name = 'contratacions'
                   AND constraint_name = 'contratacion_user_convocatoria_unique'"
            ));
        }

        if ($uniqueExists->isEmpty()) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contratacion_user_convocatoria_unique UNIQUE (user_id, convocatoria_id)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contratacions DROP CONSTRAINT IF EXISTS contrataciones_convocatoria_fk');
            DB::statement('DROP INDEX IF EXISTS contratacion_user_convocatoria_unique');
            DB::statement('ALTER TABLE contratacions ALTER COLUMN convocatoria_id TYPE BIGINT USING convocatoria_id::bigint');
        } else {
            $fks = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.table_constraints
                 WHERE constraint_type = 'FOREIGN KEY'
                   AND table_name = 'contratacions'
                   AND constraint_name = 'contrataciones_convocatoria_fk'"
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
    }
};

