<?php

namespace Tests\Feature;

use App\Models\Aspirante\Idioma;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de idiomas del docente.
 *
 * Cubre creación, listado, consulta por ID, actualización y eliminación.
 * Todos los endpoints requieren autenticación con rol Docente.
 */
class IdiomaApiTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Crea y retorna un usuario con rol Docente. */
    private function crearDocente(): User
    {
        $uid = uniqid();
        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '77' . substr($uid, -8),
            'primer_nombre'         => 'Testdoc',
            'primer_apellido'       => 'Idioma',
            'fecha_nacimiento'      => '1980-03-10',
            'email'                 => 'docidioma' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole('Docente');
        return $user;
    }

    /** Crea un idioma en BD asociado al usuario dado. */
    private function crearIdioma(User $user): Idioma
    {
        return Idioma::create([
            'user_id'            => $user->id,
            'idioma'             => 'Inglés',
            'institucion_idioma' => 'British Council',
            'fecha_certificado'  => '2022-06-01',
            'nivel'              => 'B2',
        ]);
    }

    /** Datos válidos para crear un idioma (sin archivo — se agrega aparte). */
    private function datosIdiomaValido(): array
    {
        return [
            'idioma'             => 'Francés',
            'institucion_idioma' => 'Institut Français',
            'fecha_certificado'  => '2023-01-15',
            'nivel'              => 'B1',
        ];
    }

    // ---------------------------------------------------------------
    // Crear idioma
    // ---------------------------------------------------------------

    /** POST /api/docente/crear-idioma con datos y PDF válidos debe retornar 201. */
    public function test_crear_idioma_valido_retorna_201(): void
    {
        Storage::fake('public');
        $user = $this->crearDocente();

        $datos = $this->datosIdiomaValido();
        $datos['archivo'] = UploadedFile::fake()->create('certificado.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-idioma', $datos);

        $response->assertStatus(201)
                 ->assertJsonStructure(['mensaje']);
    }

    /** POST /api/docente/crear-idioma sin archivo (requerido) debe retornar 422. */
    public function test_crear_idioma_sin_archivo_retorna_422(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-idioma', $this->datosIdiomaValido());

        $response->assertStatus(422)
                 ->assertJsonPath('errors.archivo', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-idioma con nivel fuera del enum debe retornar 422. */
    public function test_crear_idioma_nivel_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user = $this->crearDocente();

        $datos = $this->datosIdiomaValido();
        $datos['nivel']   = 'Z9';
        $datos['archivo'] = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-idioma', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.nivel', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-idioma sin campo idioma debe retornar 422. */
    public function test_crear_idioma_sin_campo_idioma_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosIdiomaValido();
        unset($datos['idioma']);
        $datos['archivo'] = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-idioma', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.idioma', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-idioma con archivo no-PDF debe retornar 422. */
    public function test_crear_idioma_archivo_no_pdf_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosIdiomaValido();
        $datos['archivo'] = UploadedFile::fake()->create('cert.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-idioma', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.archivo', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-idioma sin autenticación debe retornar 401. */
    public function test_crear_idioma_sin_autenticacion_retorna_401(): void
    {
        Storage::fake('public');
        $datos = $this->datosIdiomaValido();
        $datos['archivo'] = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/docente/crear-idioma', $datos);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Obtener idiomas (lista)
    // ---------------------------------------------------------------

    /** GET /api/docente/obtener-idiomas sin registros debe retornar 200 con idiomas null. */
    public function test_obtener_idiomas_sin_registros_retorna_200(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-idiomas');

        $response->assertStatus(200);
    }

    /** GET /api/docente/obtener-idiomas con registros existentes debe retornar 200 con array. */
    public function test_obtener_idiomas_con_registros_retorna_200(): void
    {
        $user = $this->crearDocente();
        $this->crearIdioma($user);

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-idiomas');

        $response->assertStatus(200)
                 ->assertJsonStructure(['idiomas']);
    }

    /** GET /api/docente/obtener-idiomas sin autenticación debe retornar 401. */
    public function test_obtener_idiomas_sin_autenticacion_retorna_401(): void
    {
        $response = $this->getJson('/api/docente/obtener-idiomas');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Obtener idioma por ID
    // ---------------------------------------------------------------

    /** GET /api/docente/obtener-idioma/{id} con ID existente y propio debe retornar 200. */
    public function test_obtener_idioma_por_id_propio_retorna_200(): void
    {
        $user   = $this->crearDocente();
        $idioma = $this->crearIdioma($user);

        $response = $this->actingAs($user, 'api')
                         ->getJson("/api/docente/obtener-idioma/{$idioma->id_idioma}");

        $response->assertStatus(200);
    }

    /** GET /api/docente/obtener-idioma/{id} con ID inexistente debe retornar 404. */
    public function test_obtener_idioma_id_inexistente_retorna_404(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-idioma/999999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Actualizar idioma
    // ---------------------------------------------------------------

    /** PUT /api/docente/actualizar-idioma/{id} con datos válidos debe retornar 200. */
    public function test_actualizar_idioma_valido_retorna_200(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $idioma = $this->crearIdioma($user);

        $response = $this->actingAs($user, 'api')
                         ->putJson("/api/docente/actualizar-idioma/{$idioma->id_idioma}", [
                             'nivel' => 'C1',
                         ]);

        $response->assertStatus(200);
    }

    /** PUT /api/docente/actualizar-idioma/{id} con nivel inválido debe retornar 422. */
    public function test_actualizar_idioma_nivel_invalido_retorna_422(): void
    {
        $user   = $this->crearDocente();
        $idioma = $this->crearIdioma($user);

        $response = $this->actingAs($user, 'api')
                         ->putJson("/api/docente/actualizar-idioma/{$idioma->id_idioma}", [
                             'nivel' => 'XYZ',
                         ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.nivel', fn($v) => !empty($v));
    }

    // ---------------------------------------------------------------
    // Eliminar idioma
    // ---------------------------------------------------------------

    /** DELETE /api/docente/eliminar-idioma/{id} con ID propio debe retornar 200. */
    public function test_eliminar_idioma_propio_retorna_200(): void
    {
        $user   = $this->crearDocente();
        $idioma = $this->crearIdioma($user);

        $response = $this->actingAs($user, 'api')
                         ->deleteJson("/api/docente/eliminar-idioma/{$idioma->id_idioma}");

        $response->assertStatus(200);
    }

    /** DELETE /api/docente/eliminar-idioma/{id} con ID inexistente debe retornar 404. */
    public function test_eliminar_idioma_inexistente_retorna_404(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->deleteJson('/api/docente/eliminar-idioma/999999');

        $response->assertStatus(404);
    }

    /** DELETE /api/docente/eliminar-idioma/{id} sin autenticación debe retornar 401. */
    public function test_eliminar_idioma_sin_autenticacion_retorna_401(): void
    {
        $user   = $this->crearDocente();
        $idioma = $this->crearIdioma($user);

        $response = $this->deleteJson("/api/docente/eliminar-idioma/{$idioma->id_idioma}");

        $response->assertStatus(401);
    }
}
