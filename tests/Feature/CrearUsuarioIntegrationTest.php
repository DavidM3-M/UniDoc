<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrearUsuarioIntegrationTest extends TestCase
{
    // El registro usa un email y una identificación fijos, que son únicos en `users`. Sin
    // revertir la escritura, el test pasaría una sola vez y fallaría por duplicado en cada
    // corrida siguiente, además de dejar el usuario en la base. Mismo criterio que el resto
    // de las pruebas de Feature.
    use DatabaseTransactions;

    public function test_creacion_de_usuario_valido(): void
    {
        // Simular el sistema de archivos para evitar guardar archivos reales
        Storage::fake('public');

        // Crear archivo PDF falso
        $archivo = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        // Ejecutar la solicitud POST con los datos
        $response = $this->postJson('/api/auth/registrar-usuario', [
            'municipio_id' => 707, // asegurarse de que este ID exista
            'tipo_identificacion' => 'Cédula de ciudadanía',
            'numero_identificacion' => '10589621034',
            'genero' => 'Masculino',
            'primer_nombre' => 'Carlos',
            'segundo_nombre' => 'Andrés',
            'primer_apellido' => 'Ramírez',
            'segundo_apellido' => 'López',
            'fecha_nacimiento' => '1990-01-01',
            'estado_civil' => 'Soltero',
            'email' => 'carlos.ramirez@example.com',
            // `CrearAuthRequest` exige Password::min(8)->mixedCase()->numbers();
            // el valor anterior ('password123') no tenía mayúsculas.
            'password' => 'Password123',
            'archivo' => $archivo,
        ]);

        // Verificar que la respuesta fue exitosa
        $response->assertStatus(201);

        // Verificar que el usuario fue creado en la base de datos
        $this->assertDatabaseHas('users', [
            'email' => 'carlos.ramirez@example.com',
            'primer_nombre' => 'Carlos',
            'municipio_id' => 707,
        ]);
    }
}