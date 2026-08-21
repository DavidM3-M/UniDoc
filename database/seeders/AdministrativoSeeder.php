<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuario de prueba para el rol Administrativo.
 *
 * `RoleSeeder` crea los diez roles, pero solo siete tenían usuario sembrado. Sin este seeder la
 * pantalla de los formularios del personal administrativo no se podía abrir en un entorno de pruebas: ese rol no tenía forma de entrar al sistema en pruebas.
 */
class AdministrativoSeeder extends Seeder
{
    public function run(): void
    {
        $administrativo = User::firstOrCreate([
            'email' => 'administrativo@universidad.com'
        ], [
            'municipio_id'           => 703,
            'tipo_identificacion'    => 'Cédula de ciudadanía',
            'numero_identificacion'  => '700000003',
            'genero'                 => 'Femenino',
            'primer_nombre'          => 'Personal',
            'segundo_nombre'         => 'Administrativo',
            'primer_apellido'        => 'Uni',
            'segundo_apellido'       => 'Doc',
            'fecha_nacimiento'       => '1980-01-01',
            'estado_civil'           => 'Soltero',
            'email'                  => 'administrativo@universidad.com',
            'password'               => Hash::make('administrativo123'),
        ]);

        $administrativo->assignRole('Administrativo');

        echo "Administrativo creado con email: administrativo@universidad.com y contraseña: administrativo123\n";
    }
}
