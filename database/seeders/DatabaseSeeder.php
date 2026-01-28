<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call(PaisesTableSeeder::class);
        $this->call(DepartamentosTableSeeder::class);
        $this->call(MunicipiosTableSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(ApoyoProfesoralSeeder::class);
        $this->call(DocenteSeeder::class);
        $this->call(TalentoHumanoSeeder::class);
        $this->call(EvaluadorProduccionSeeder::class);
        $this->call(ProductoAcademicoSeeder::class);
        $this->call(AmbitoDivulgacionSeeder::class);
        $this->call(AspiranteSeeder::class);
        $this->call(CoordinadorSeeder::class);


    }
}
