<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
    }
}
