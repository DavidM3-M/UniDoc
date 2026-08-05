<?php

namespace Database\Seeders;

use App\Models\Ubicacion\Municipio;
use Illuminate\Database\Seeder;

class MunicipiosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = fopen(base_path("database/data/municipios.csv"), "r");

        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ";")) !== FALSE) {
            if (!$firstline) {
                Municipio::create([
                    'departamento_id' => $data[1],
                    'nombre'   => $data[2],
                ]);
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
