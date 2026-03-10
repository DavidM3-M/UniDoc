<?php

namespace Tests\Feature;

use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de autenticación.
 *
 * Cubre registro, inicio de sesión, cierre de sesión,
 * obtención y actualización del usuario autenticado.
 */
class AuthApiTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Datos de usuario válidos reutilizables en los tests
    // ---------------------------------------------------------------

    /** Genera datos válidos para registrar un nuevo usuario. */
    private function datosRegistroValido(array $sobreescribir = []): array
    {
        $uid = uniqid();
        return array_merge([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '99' . substr($uid, -8),
            'genero'                => 'Masculino',
            'primer_nombre'         => 'Carlos',
            'segundo_nombre'        => 'Andrés',
            'primer_apellido'       => 'Ramírez',
            'segundo_apellido'      => 'López',
            'fecha_nacimiento'      => '1990-05-15',
            'estado_civil'          => 'Soltero',
            'email'                 => 'test' . $uid . '@example.com',
            'password'              => 'Password1',
        ], $sobreescribir);
    }

    /** Crea un usuario con rol Docente listo para autenticarse. */
    private function crearDocente(array $extra = []): User
    {
        $uid = uniqid();
        $user = User::create(array_merge([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '88' . substr($uid, -8),
            'genero'                => 'Masculino',
            'primer_nombre'         => 'Test',
            'primer_apellido'       => 'Docente',
            'fecha_nacimiento'      => '1985-01-01',
            'email'                 => 'docente' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ], $extra));
        $user->assignRole('Docente');
        return $user;
    }

    // ---------------------------------------------------------------
    // Registro de usuario
    // ---------------------------------------------------------------

    /** POST /api/auth/registrar-usuario con datos válidos debe retornar 201 y token. */
    public function test_registro_usuario_valido_retorna_201_y_token(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/auth/registrar-usuario', $this->datosRegistroValido());

        $response->assertStatus(201)
                 ->assertJsonStructure(['token']);
    }

    /** POST /api/auth/registrar-usuario sin campos obligatorios debe retornar 422. */
    public function test_registro_usuario_sin_campos_obligatorios_retorna_422(): void
    {
        $response = $this->postJson('/api/auth/registrar-usuario', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors']);
    }

    /** POST /api/auth/registrar-usuario con email duplicado debe retornar 422. */
    public function test_registro_usuario_email_duplicado_retorna_422(): void
    {
        Storage::fake('public');
        $datos = $this->datosRegistroValido();

        // Primera creación exitosa
        $this->postJson('/api/auth/registrar-usuario', $datos)->assertStatus(201);

        // Segunda con mismo email → conflicto de validación
        $response = $this->postJson('/api/auth/registrar-usuario', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.email', fn($v) => !empty($v));
    }

    /** POST /api/auth/registrar-usuario con tipo_identificacion inválido debe retornar 422. */
    public function test_registro_usuario_tipo_identificacion_invalido_retorna_422(): void
    {
        $datos = $this->datosRegistroValido(['tipo_identificacion' => 'Pasaporte Galáctico']);

        $response = $this->postJson('/api/auth/registrar-usuario', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.tipo_identificacion', fn($v) => !empty($v));
    }

    /** POST /api/auth/registrar-usuario con contraseña sin mezcla de caracteres debe retornar 422. */
    public function test_registro_usuario_password_debil_retorna_422(): void
    {
        $datos = $this->datosRegistroValido(['password' => 'password']);

        $response = $this->postJson('/api/auth/registrar-usuario', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.password', fn($v) => !empty($v));
    }

    /** POST /api/auth/registrar-usuario con fecha_nacimiento en el futuro debe retornar 422. */
    public function test_registro_usuario_fecha_nacimiento_futura_retorna_422(): void
    {
        $datos = $this->datosRegistroValido(['fecha_nacimiento' => '2099-01-01']);

        $response = $this->postJson('/api/auth/registrar-usuario', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.fecha_nacimiento', fn($v) => !empty($v));
    }

    /** POST /api/auth/registrar-usuario con archivo PDF válido debe retornar 201. */
    public function test_registro_usuario_con_archivo_pdf_valido_retorna_201(): void
    {
        Storage::fake('public');
        $datos = $this->datosRegistroValido();
        $datos['archivo'] = UploadedFile::fake()->create('cedula.pdf', 512, 'application/pdf');

        $response = $this->postJson('/api/auth/registrar-usuario', $datos);

        $response->assertStatus(201);
    }

    /** POST /api/auth/registrar-usuario con archivo no-PDF debe retornar 422. */
    public function test_registro_usuario_archivo_no_pdf_retorna_422(): void
    {
        Storage::fake('public');
        $datos = $this->datosRegistroValido();
        $datos['archivo'] = UploadedFile::fake()->create('cedula.docx', 200, 'application/msword');

        $response = $this->postJson('/api/auth/registrar-usuario', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.archivo', fn($v) => !empty($v));
    }

    // ---------------------------------------------------------------
    // Inicio de sesión
    // ---------------------------------------------------------------

    /** POST /api/auth/iniciar-sesion con credenciales válidas debe retornar 200 y token. */
    public function test_inicio_sesion_valido_retorna_200_y_token(): void
    {
        $user = $this->crearDocente(['password' => Hash::make('Docente1')]);

        $response = $this->postJson('/api/auth/iniciar-sesion', [
            'email'    => $user->email,
            'password' => 'Docente1',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'token']);
    }

    /** POST /api/auth/iniciar-sesion con contraseña incorrecta debe retornar 401. */
    public function test_inicio_sesion_password_incorrecto_retorna_401(): void
    {
        $user = $this->crearDocente();

        $response = $this->postJson('/api/auth/iniciar-sesion', [
            'email'    => $user->email,
            'password' => 'ContraseñaIncorrecta99',
        ]);

        $response->assertStatus(401);
    }

    /** POST /api/auth/iniciar-sesion sin email debe retornar 400. */
    public function test_inicio_sesion_sin_email_retorna_400(): void
    {
        $response = $this->postJson('/api/auth/iniciar-sesion', [
            'password' => 'Password1',
        ]);

        $response->assertStatus(400);
    }

    /** POST /api/auth/iniciar-sesion sin password debe retornar 400. */
    public function test_inicio_sesion_sin_password_retorna_400(): void
    {
        $response = $this->postJson('/api/auth/iniciar-sesion', [
            'email' => 'alguien@test.com',
        ]);

        $response->assertStatus(400);
    }

    /** POST /api/auth/iniciar-sesion con email con formato inválido debe retornar 400. */
    public function test_inicio_sesion_email_invalido_retorna_400(): void
    {
        $response = $this->postJson('/api/auth/iniciar-sesion', [
            'email'    => 'no-es-un-email',
            'password' => 'Password1',
        ]);

        $response->assertStatus(400);
    }

    // ---------------------------------------------------------------
    // Obtener usuario autenticado
    // ---------------------------------------------------------------

    /** GET /api/auth/obtener-usuario-autenticado autenticado debe retornar 200 con datos del user. */
    public function test_obtener_usuario_autenticado_retorna_200(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/auth/obtener-usuario-autenticado');

        $response->assertStatus(200);
    }

    /** GET /api/auth/obtener-usuario-autenticado sin token debe retornar 401. */
    public function test_obtener_usuario_autenticado_sin_token_retorna_401(): void
    {
        $response = $this->getJson('/api/auth/obtener-usuario-autenticado');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Actualizar usuario autenticado
    // ---------------------------------------------------------------

    /** POST /api/auth/actualizar-usuario con dato válido debe retornar 200. */
    public function test_actualizar_usuario_autenticado_retorna_200(): void
    {
        Storage::fake('public');
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/auth/actualizar-usuario', [
                             'primer_nombre'   => 'Actualizado',
                             'primer_apellido' => 'Apellido',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'user']);
    }

    /** POST /api/auth/actualizar-usuario con genero inválido debe retornar 422. */
    public function test_actualizar_usuario_genero_invalido_retorna_422(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/auth/actualizar-usuario', [
                             'genero' => 'GeneroInexistente',
                         ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.genero', fn($v) => !empty($v));
    }

    /** POST /api/auth/actualizar-usuario sin autenticación debe retornar 401. */
    public function test_actualizar_usuario_sin_autenticacion_retorna_401(): void
    {
        $response = $this->postJson('/api/auth/actualizar-usuario', [
            'primer_nombre' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Cerrar sesión
    // ---------------------------------------------------------------

    /** POST /api/auth/cerrar-sesion autenticado debe retornar 200. */
    public function test_cerrar_sesion_autenticado_retorna_200(): void
    {
        $user  = $this->crearDocente();
        $token = JWTAuth::fromUser($user); // Genera un token JWT real necesario para invalidarlo

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->postJson('/api/auth/cerrar-sesion');

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Sesión cerrada exitosamente');
    }

    /** POST /api/auth/cerrar-sesion sin token debe retornar 401. */
    public function test_cerrar_sesion_sin_autenticacion_retorna_401(): void
    {
        $response = $this->postJson('/api/auth/cerrar-sesion');

        $response->assertStatus(401);
    }
}
