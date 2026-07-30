<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run()
    {
        // Crear usuario administrador por defecto si no existe
        $admin = User::firstOrCreate([
            'email' => 'adminunidoc@universidad.com'
        ], [
            'municipio_id'           => 703, // Cambia este valor según el municipio en tu DB
            'tipo_identificacion'    => 'Cédula de ciudadanía', // Cambia según los valores en TipoIdentificacion::all()
            'numero_identificacion'  => '123456789', // Cambia según necesidad
            'genero'                 => 'Masculino', // Cambia según los valores en Genero::all()
            'primer_nombre'          => 'Admin',
            'segundo_nombre'         => 'Sistema',
            'primer_apellido'        => 'Universidad',
            'segundo_apellido'       => 'Gestión',
            'fecha_nacimiento'       => '1990-01-01', // Ajusta según necesidad
            'estado_civil'           => 'Soltero', // Cambia según los valores en EstadoCivil::all()
            'email'                  => 'adminunidoc@universidad.com',
            'password'               => Hash::make('Adm!n_9*Qw3z8L#'), // Cambia la contraseña si lo deseas
        ]);

        // Asignar el rol de admin
        $admin->assignRole('Administrador');
    }
}
