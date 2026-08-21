<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuario de prueba para el rol Vicerrectoria.
 *
 * `RoleSeeder` crea los diez roles, pero solo siete tenían usuario sembrado. Sin este seeder la
 * pantalla de avales de Vicerrectoría no se podía abrir en un entorno de pruebas: el paso intermedio de la cadena de avales nunca se había podido probar.
 */
class VicerrectoriaSeeder extends Seeder
{
    public function run(): void
    {
        $vicerrectoria = User::firstOrCreate([
            'email' => 'vicerrectoria@universidad.com'
        ], [
            'municipio_id'           => 703,
            'tipo_identificacion'    => 'Cédula de ciudadanía',
            'numero_identificacion'  => '700000002',
            'genero'                 => 'Masculino',
            'primer_nombre'          => 'Vicerrectoria',
            'segundo_nombre'         => 'Academica',
            'primer_apellido'        => 'Uni',
            'segundo_apellido'       => 'Doc',
            'fecha_nacimiento'       => '1980-01-01',
            'estado_civil'           => 'Soltero',
            'email'                  => 'vicerrectoria@universidad.com',
            'password'               => Hash::make('vicerrectoria123'),
        ]);

        $vicerrectoria->assignRole('Vicerrectoria');

        echo "Vicerrectoria creado con email: vicerrectoria@universidad.com y contraseña: vicerrectoria123\n";
    }
}
