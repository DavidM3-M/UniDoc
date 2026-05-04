<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Corregir tipo de convocatoria_id (bigint → smallint unsigned)
        DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id SMALLINT UNSIGNED NULL');

        // 2. FK solo si no existe
        $fkExists = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'contratacions'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'contrataciones_convocatoria_fk'"
        ))->isNotEmpty();

        if (!$fkExists) {
            DB::statement('ALTER TABLE contratacions ADD CONSTRAINT contrataciones_convocatoria_fk FOREIGN KEY (convocatoria_id) REFERENCES convocatorias (id_convocatoria) ON DELETE SET NULL');
        }

        // 3. Unique solo si no existe
        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM contratacions WHERE Key_name = 'contratacion_user_convocatoria_unique'"
        ))->isNotEmpty();

        if (!$uniqueExists) {
            DB::statement('ALTER TABLE contratacions ADD UNIQUE KEY contratacion_user_convocatoria_unique (user_id, convocatoria_id)');
        }
    }

    public function down(): void
    {
        // Eliminar unique si existe
        $uniqueExists = collect(DB::select(
            "SHOW INDEX FROM contratacions WHERE Key_name = 'contratacion_user_convocatoria_unique'"
        ))->isNotEmpty();

        if ($uniqueExists) {
            // Primero eliminar FK que depende del índice
            $fkExists = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'contratacions'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                   AND CONSTRAINT_NAME = 'contrataciones_convocatoria_fk'"
            ))->isNotEmpty();

            if ($fkExists) {
                DB::statement('ALTER TABLE contratacions DROP FOREIGN KEY contrataciones_convocatoria_fk');
            }

            DB::statement('ALTER TABLE contratacions DROP INDEX contratacion_user_convocatoria_unique');
        }

        // Revertir FK
        $fkExists = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'contratacions'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME = 'contrataciones_convocatoria_fk'"
        ))->isNotEmpty();

        if ($fkExists) {
            DB::statement('ALTER TABLE contratacions DROP FOREIGN KEY contrataciones_convocatoria_fk');
        }

        // Revertir tipo
        DB::statement('ALTER TABLE contratacions MODIFY convocatoria_id BIGINT UNSIGNED NULL');
    }
};