<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuario de prueba para el rol Rectoria.
 *
 * `RoleSeeder` crea los diez roles, pero solo siete tenían usuario sembrado. Sin este seeder la
 * pantalla de avales de Rectoría no se podía abrir en un entorno de pruebas: el último tramo de la cadena de avales nunca se había recorrido completo.
 */
class RectoriaSeeder extends Seeder
{
    public function run(): void
    {
        $rectoria = User::firstOrCreate([
            'email' => 'rectoria@universidad.com'
        ], [
            'municipio_id'           => 703,
            'tipo_identificacion'    => 'Cédula de ciudadanía',
            'numero_identificacion'  => '700000001',
            'genero'                 => 'Femenino',
            'primer_nombre'          => 'Rectoria',
            'segundo_nombre'         => 'General',
            'primer_apellido'        => 'Uni',
            'segundo_apellido'       => 'Doc',
            'fecha_nacimiento'       => '1980-01-01',
            'estado_civil'           => 'Soltero',
            'email'                  => 'rectoria@universidad.com',
            'password'               => Hash::make('rectoria123'),
        ]);

        $rectoria->assignRole('Rectoria');

        echo "Rectoria creado con email: rectoria@universidad.com y contraseña: rectoria123\n";
    }
}
