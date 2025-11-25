<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;


class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Definir los roles a crear
        //borre el rol Evaluador Producción (Brayan Cuellar)
        $roles = ['Administrador', 'Aspirante', 'Docente', 'Talento Humano', 'Apoyo Profesoral',];

        // Crear los roles si no existen
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
