<?php

namespace Tests\Feature;

use App\Models\Aspirante\Estudio;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de estudios académicos del docente.
 *
 * Cubre creación, listado, consulta por ID, actualización y eliminación.
 * Todos los endpoints requieren autenticación con rol Docente.
 */
class EstudioApiTest extends TestCase
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
            'numero_identificacion' => '55' . substr($uid, -8),
            'primer_nombre'         => 'Testdoc',
            'primer_apellido'       => 'Estudio',
            'fecha_nacimiento'      => '1975-11-05',
            'email'                 => 'docest' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole('Docente');
        return $user;
    }

    /** Crea un estudio en BD asociado al usuario dado. */
    private function crearEstudio(User $user): Estudio
    {
        return Estudio::create([
            'user_id'           => $user->id,
            'tipo_estudio'      => 'Pregrado',
            'graduado'          => 'Si',
            'institucion'       => 'Universidad Nacional',
            'titulo_convalidado'=> 'No',
            'titulo_estudio'    => 'Ingeniería de Sistemas',
            'fecha_inicio'      => '2010-01-15',
            'fecha_graduacion'  => '2015-06-30',
        ]);
    }

    /** Datos válidos mínimos para crear un estudio. */
    private function datosEstudioValido(): array
    {
        return [
            'tipo_estudio'      => 'Maestría',
            'graduado'          => 'Si',
            'institucion'       => 'Universidad de Los Andes',
            'titulo_convalidado'=> 'No',
            'titulo_estudio'    => 'Maestría en Ciencias de la Computación',
            'fecha_inicio'      => '2016-02-01',
            'fecha_graduacion'  => '2018-11-30',
        ];
    }

    // ---------------------------------------------------------------
    // Crear estudio
    // ---------------------------------------------------------------

    /** POST /api/docente/crear-estudio con datos + PDF válidos debe retornar 201. */
    public function test_crear_estudio_valido_retorna_201(): void
    {
        Storage::fake('public');
        $user = $this->crearDocente();

        $datos  = $this->datosEstudioValido();
        $datos['archivo'] = UploadedFile::fake()->create('diploma.pdf', 400, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message']);
    }

    /** POST /api/docente/crear-estudio sin archivo (requerido) debe retornar 422. */
    public function test_crear_estudio_sin_archivo_retorna_422(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $this->datosEstudioValido());

        $response->assertStatus(422)
                 ->assertJsonPath('errors.archivo', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-estudio con tipo_estudio fuera del enum debe retornar 422. */
    public function test_crear_estudio_tipo_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosEstudioValido();
        $datos['tipo_estudio'] = 'Doctorado Interplanetario';
        $datos['archivo']      = UploadedFile::fake()->create('diploma.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.tipo_estudio', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-estudio con graduado inválido debe retornar 422. */
    public function test_crear_estudio_graduado_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosEstudioValido();
        $datos['graduado'] = 'Tal vez';
        $datos['archivo']  = UploadedFile::fake()->create('diploma.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.graduado', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-estudio con titulo_convalidado inválido debe retornar 422. */
    public function test_crear_estudio_titulo_convalidado_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosEstudioValido();
        $datos['titulo_convalidado'] = 'Parcialmente';
        $datos['archivo']            = UploadedFile::fake()->create('diploma.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.titulo_convalidado', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-estudio con institucion demasiado corta debe retornar 422. */
    public function test_crear_estudio_institucion_muy_corta_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosEstudioValido();
        $datos['institucion'] = 'UNI'; // min 7 chars
        $datos['archivo']     = UploadedFile::fake()->create('diploma.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.institucion', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-estudio sin campos obligatorios debe retornar 422. */
    public function test_crear_estudio_sin_campos_obligatorios_retorna_422(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors']);
    }

    /** POST /api/docente/crear-estudio con archivo no-PDF debe retornar 422. */
    public function test_crear_estudio_archivo_no_pdf_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosEstudioValido();
        $datos['archivo'] = UploadedFile::fake()->create('diploma.png', 200, 'image/png');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.archivo', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-estudio sin autenticación debe retornar 401. */
    public function test_crear_estudio_sin_autenticacion_retorna_401(): void
    {
        Storage::fake('public');
        $datos = $this->datosEstudioValido();
        $datos['archivo'] = UploadedFile::fake()->create('diploma.pdf', 200, 'application/pdf');

        $response = $this->postJson('/api/docente/crear-estudio', $datos);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Obtener estudios (lista)
    // ---------------------------------------------------------------

    /** GET /api/docente/obtener-estudios sin registros debe retornar 200. */
    public function test_obtener_estudios_sin_registros_retorna_200(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-estudios');

        $response->assertStatus(200);
    }

    /** GET /api/docente/obtener-estudios con registros debe retornar 200 con estructura. */
    public function test_obtener_estudios_con_registros_retorna_200(): void
    {
        $user = $this->crearDocente();
        $this->crearEstudio($user);

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-estudios');

        $response->assertStatus(200)
                 ->assertJsonStructure(['estudios']);
    }

    /** GET /api/docente/obtener-estudios sin autenticación debe retornar 401. */
    public function test_obtener_estudios_sin_autenticacion_retorna_401(): void
    {
        $response = $this->getJson('/api/docente/obtener-estudios');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Obtener estudio por ID
    // ---------------------------------------------------------------

    /** GET /api/docente/obtener-estudio/{id} con ID propio debe retornar 200. */
    public function test_obtener_estudio_por_id_propio_retorna_200(): void
    {
        $user    = $this->crearDocente();
        $estudio = $this->crearEstudio($user);

        $response = $this->actingAs($user, 'api')
                         ->getJson("/api/docente/obtener-estudio/{$estudio->id_estudio}");

        $response->assertStatus(200);
    }

    /** GET /api/docente/obtener-estudio/{id} con ID inexistente debe retornar 404. */
    public function test_obtener_estudio_id_inexistente_retorna_404(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-estudio/999999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Actualizar estudio
    // ---------------------------------------------------------------

    /** PUT /api/docente/actualizar-estudio/{id} con campo válido debe retornar 200. */
    public function test_actualizar_estudio_valido_retorna_200(): void
    {
        Storage::fake('public');
        $user    = $this->crearDocente();
        $estudio = $this->crearEstudio($user);

        $response = $this->actingAs($user, 'api')
                         ->putJson("/api/docente/actualizar-estudio/{$estudio->id_estudio}", [
                             'titulo_estudio' => 'Ingeniería de Sistemas Actualizada',
                         ]);

        $response->assertStatus(200);
    }

    /** PUT /api/docente/actualizar-estudio/{id} con tipo inválido debe retornar 422. */
    public function test_actualizar_estudio_tipo_invalido_retorna_422(): void
    {
        $user    = $this->crearDocente();
        $estudio = $this->crearEstudio($user);

        $response = $this->actingAs($user, 'api')
                         ->putJson("/api/docente/actualizar-estudio/{$estudio->id_estudio}", [
                             'tipo_estudio' => 'Estudios Varios Inválidos',
                         ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.tipo_estudio', fn($v) => !empty($v));
    }

    // ---------------------------------------------------------------
    // Eliminar estudio
    // ---------------------------------------------------------------

    /** DELETE /api/docente/eliminar-estudio/{id} con ID propio debe retornar 200. */
    public function test_eliminar_estudio_propio_retorna_200(): void
    {
        $user    = $this->crearDocente();
        $estudio = $this->crearEstudio($user);

        $response = $this->actingAs($user, 'api')
                         ->deleteJson("/api/docente/eliminar-estudio/{$estudio->id_estudio}");

        $response->assertStatus(200);
    }

    /** DELETE /api/docente/eliminar-estudio/{id} con ID inexistente debe retornar 404. */
    public function test_eliminar_estudio_inexistente_retorna_404(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->deleteJson('/api/docente/eliminar-estudio/999999');

        $response->assertStatus(404);
    }

    /** DELETE /api/docente/eliminar-estudio/{id} sin autenticación debe retornar 401. */
    public function test_eliminar_estudio_sin_autenticacion_retorna_401(): void
    {
        $user    = $this->crearDocente();
        $estudio = $this->crearEstudio($user);

        $response = $this->deleteJson("/api/docente/eliminar-estudio/{$estudio->id_estudio}");

        $response->assertStatus(401);
    }
}
