<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DocenteSeeder extends Seeder
{
   
    public function run(): void
    {
         // Crear usuario administrador por defecto si no existe
         $docente = User::firstOrCreate([
            'email' => 'docente@universidad.com'
        ], [
            'municipio_id'           => 703, // Cambia este valor según el municipio en tu DB
            'tipo_identificacion'    => 'Cédula de ciudadanía', // Cambia según los valores en TipoIdentificacion::all()
            'numero_identificacion'  => '978645312', // Cambia según necesidad
            'genero'                 => 'Masculino', // Cambia según los valores en Genero::all()
            'primer_nombre'          => 'Docente',
            'segundo_nombre'         => 'Profesor',
            'primer_apellido'        => 'Laburo',
            'segundo_apellido'       => 'Uni',
            'fecha_nacimiento'       => '1950-06-25', // Ajusta según necesidad
            'estado_civil'           => 'Soltero', // Cambia según los valores en EstadoCivil::all()
            'email'                  => 'docente@universidad.com',
            'password'               => Hash::make('docente123'), // Cambia la contraseña si lo deseas
        ]);

        // Asignar el rol de admin
        $docente->assignRole('Docente');

        echo "✅ Docente creado con email: docente@universidad.com y contraseña: docente123\n";
    }
}
