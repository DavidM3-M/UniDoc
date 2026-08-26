<?php

namespace Database\Seeders;

use App\Models\Usuario\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EvaluadorProduccionSeeder extends Seeder
{

    public function run(): void
    {
         // Crear usuario administrador por defecto si no existe
         $evaluadorProduccion = User::firstOrCreate([
            'email' => 'evaluadorproduccion@universidad.com'
        ], [
            'municipio_id'           => 703, // Cambia este valor según el municipio en tu DB
            'tipo_identificacion'    => 'Cédula de ciudadanía', // Cambia según los valores en TipoIdentificacion::all()
            'numero_identificacion'  => '105896899', // Cambia según necesidad
            'genero'                 => 'Masculino', // Cambia según los valores en Genero::all()
            // Antes decía "Docente Profesor Laburo Uni", copiado del seeder de docentes: el
            // usuario del evaluador aparecía con nombre de docente en la columna «Revisó» de
            // todas las producciones que avalaba en desarrollo.
            'primer_nombre'          => 'Evaluador',
            'segundo_nombre'         => null,
            'primer_apellido'        => 'Produccion',
            'segundo_apellido'       => 'Academica',
            'fecha_nacimiento'       => '1950-06-25', // Ajusta según necesidad
            'estado_civil'           => 'Soltero', // Cambia según los valores en EstadoCivil::all()
            'email'                  => 'evaluadorproduccion@universidad.com',
            'password'               => Hash::make('evaluador123'), // Cambia la contraseña si lo deseas
        ]);

        // Asignar el rol de admin
        $evaluadorProduccion->assignRole('Evaluador Produccion');

        echo "✅ Evaluador de Producción creado con email: evaluadorproduccion@universidad.com y contraseña: evaluador123\n";
    }
}
