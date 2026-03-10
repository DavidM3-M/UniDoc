<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalizar valores existentes a boolean
        DB::statement("UPDATE users SET aval_rectoria = '1' WHERE aval_rectoria = 'Aprobado'");
        DB::statement("UPDATE users SET aval_vicerrectoria = '1' WHERE aval_vicerrectoria = 'Aprobado'");
        DB::statement("UPDATE users SET aval_rectoria = '0' WHERE aval_rectoria IS NULL OR aval_rectoria = 'Rechazado'");
        DB::statement("UPDATE users SET aval_vicerrectoria = '0' WHERE aval_vicerrectoria IS NULL OR aval_vicerrectoria = 'Rechazado'");

        // Convertir columnas a boolean (sintaxis según driver)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN aval_rectoria TYPE BOOLEAN USING (aval_rectoria::integer::boolean), ALTER COLUMN aval_rectoria SET NOT NULL, ALTER COLUMN aval_rectoria SET DEFAULT false");
            DB::statement("ALTER TABLE users ALTER COLUMN aval_vicerrectoria TYPE BOOLEAN USING (aval_vicerrectoria::integer::boolean), ALTER COLUMN aval_vicerrectoria SET NOT NULL, ALTER COLUMN aval_vicerrectoria SET DEFAULT false");
        } else {
            DB::statement("ALTER TABLE users MODIFY aval_rectoria TINYINT(1) NOT NULL DEFAULT 0");
            DB::statement("ALTER TABLE users MODIFY aval_vicerrectoria TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a string nullable
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN aval_rectoria TYPE VARCHAR(255) USING aval_rectoria::text, ALTER COLUMN aval_rectoria DROP NOT NULL, ALTER COLUMN aval_rectoria DROP DEFAULT");
            DB::statement("ALTER TABLE users ALTER COLUMN aval_vicerrectoria TYPE VARCHAR(255) USING aval_vicerrectoria::text, ALTER COLUMN aval_vicerrectoria DROP NOT NULL, ALTER COLUMN aval_vicerrectoria DROP DEFAULT");
        } else {
            DB::statement("ALTER TABLE users MODIFY aval_rectoria VARCHAR(255) NULL");
            DB::statement("ALTER TABLE users MODIFY aval_vicerrectoria VARCHAR(255) NULL");
        }
    }
};
