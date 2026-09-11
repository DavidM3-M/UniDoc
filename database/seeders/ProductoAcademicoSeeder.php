<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\TiposProductoAcademico\ProductoAcademico;

class ProductoAcademicoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = fopen(base_path("database/data/tipo_producto_academico.csv"), "r");

        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ";")) !== FALSE) {
            if (!$firstline) {
                ProductoAcademico::create([
                    "id_producto_academico" => "$data[0]",
                    "nombre_producto_academico" => "$data[1]"
                ]);
            }
            $firstline = false;
        }

        fclose($csvFile);

        $this->resincronizarSecuencia();
    }

    /**
     * Pone la secuencia de IDs justo después del máximo sembrado.
     *
     * Las filas de arriba se insertan con `id_producto_academico` explícito y PostgreSQL no
     * avanza la secuencia en ese caso. Sin este ajuste, el primer producto académico que el
     * Administrador cree por API tomaría un ID ya ocupado y fallaría con clave duplicada.
     * MySQL no lo necesita: su AUTO_INCREMENT sí avanza con IDs explícitos.
     */
    private function resincronizarSecuencia(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('producto_academicos', 'id_producto_academico'),
                (SELECT COALESCE(MAX(id_producto_academico), 0) + 1 FROM producto_academicos),
                false
            )
        ");
    }
}
