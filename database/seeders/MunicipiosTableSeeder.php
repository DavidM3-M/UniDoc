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
                    // El id se fija en vez de dejarlo al autoincremento: el CSV viene ordenado por
                    // codigo DIVIPOLA y asi la numeracion es reproducible entre entornos, que es lo
                    // que permite que otros seeders referencien un municipio concreto.
                    'id_municipio'    => $data[0],
                    'departamento_id' => $data[1],
                    'nombre'          => $data[2],
                    'codigo_divipola' => $data[3] !== '' ? $data[3] : null,
                    'tipo'            => $data[4] !== '' ? $data[4] : null,
                ]);
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
