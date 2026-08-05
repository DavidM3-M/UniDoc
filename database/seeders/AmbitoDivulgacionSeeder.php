<?php

namespace Database\Seeders;

use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmbitoDivulgacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = fopen(base_path("database/data/ambito_divulgacion.csv"), "r");

        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ";")) !== FALSE) {
            if (!$firstline) {
                AmbitoDivulgacion::create([
                    "producto_academico_id" => "$data[1]",
                    "nombre_ambito_divulgacion" => "$data[2]"
                ]);
            }
            $firstline = false;
        }

        fclose($csvFile);
        
    }
}
