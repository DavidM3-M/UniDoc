<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cambiar tipo de convocatoria_id a smallint (compatible PostgreSQL)
        DB::statement('ALTER TABLE contratacions ALTER COLUMN convocatoria_id TYPE SMALLINT USING convocatoria_id::SMALLINT');

        // 2. FK solo si no existe
        $hasFk = DB::select("
            SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_type = 'FOREIGN KEY'
              AND table_name = 'contratacions'
              AND constraint_name = 'contrataciones_convocatoria_fk'
        ");
        if (empty($hasFk)) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contrataciones_convocatoria_fk FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id_convocatoria) ON DELETE SET NULL');
        }

        // 3. Unique solo si no existe
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
        DB::statement('ALTER TABLE contratacions DROP CONSTRAINT IF EXISTS contratacion_user_convocatoria_unique');
        DB::statement('ALTER TABLE contratacions DROP CONSTRAINT IF EXISTS contrataciones_convocatoria_fk');
    }
};

/**
 * Reparación del estado parcial dejado por la primera ejecución fallida.
 *
 * Estado actual de la BD:
 *  - convocatoria_id existe como bigint(20) unsigned → debe ser smallint unsigned
 *  - tipo_proceso ya existe correctamente
 *  - Sin FK ni unique constraint
 *
 * Esta migración corrige el tipo y agrega FK + unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Corregir tipo de convocatoria_id (bigint → smallint unsigned para coincidir con convocatorias.id_convocatoria)
        DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id SMALLINT UNSIGNED NULL COMMENT \'Convocatoria asociada a esta contratación/ascenso/cambio de cargo\'');

        // 2. Agregar FK (solo si no existe)
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

        // 3. Agregar unique constraint (solo si no existe)
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

        // Revertir tipo (opcional, bigint era el error original)
        DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id BIGINT UNSIGNED NULL');
    }
};
