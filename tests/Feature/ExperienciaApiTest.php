<?php

namespace Tests\Feature;

use App\Models\Aspirante\Experiencia;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de experiencias laborales del docente.
 *
 * Cubre creación, listado, consulta por ID, actualización y eliminación.
 * Todos los endpoints requieren autenticación con rol Docente.
 */
class ExperienciaApiTest extends TestCase
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
            'numero_identificacion' => '66' . substr($uid, -8),
            'primer_nombre'         => 'Testdoc',
            'primer_apellido'       => 'Experiencia',
            'fecha_nacimiento'      => '1978-07-20',
            'email'                 => 'docexp' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole('Docente');
        return $user;
    }

    /** Crea una experiencia en BD asociada al usuario dado. */
    private function crearExperiencia(User $user): Experiencia
    {
        return Experiencia::create([
            'user_id'                => $user->id,
            'tipo_experiencia'       => 'Docencia universitaria',
            'institucion_experiencia'=> 'Universidad Nacional',
            'cargo'                  => 'Profesor titular',
            'trabajo_actual'         => 'No',
            'fecha_inicio'           => '2015-01-15',
            'fecha_finalizacion'     => '2020-12-31',
        ]);
    }

    /** Datos válidos mínimos para crear una experiencia. */
    private function datosExperienciaValido(): array
    {
        return [
            'tipo_experiencia'       => 'Investigación',
            'institucion_experiencia'=> 'Centro de Investigaciones',
            'cargo'                  => 'Investigador auxiliar',
            'trabajo_actual'         => 'Si',
            'fecha_inicio'           => '2020-03-01',
        ];
    }

    // ---------------------------------------------------------------
    // Crear experiencia
    // ---------------------------------------------------------------

    /** POST /api/docente/crear-experiencia con datos y PDF válidos debe retornar 201. */
    public function test_crear_experiencia_valida_retorna_201(): void
    {
        Storage::fake('public');
        $user = $this->crearDocente();

        $datos  = $this->datosExperienciaValido();
        $datos['archivo'] = UploadedFile::fake()->create('certificado.pdf', 300, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', $datos);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message']);
    }

    /** POST /api/docente/crear-experiencia sin archivo (requerido) debe retornar 422. */
    public function test_crear_experiencia_sin_archivo_retorna_422(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', $this->datosExperienciaValido());

        $response->assertStatus(422)
                 ->assertJsonPath('errors.archivo', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-experiencia con tipo_experiencia fuera del enum debe retornar 422. */
    public function test_crear_experiencia_tipo_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosExperienciaValido();
        $datos['tipo_experiencia'] = 'Experiencia Desconocida';
        $datos['archivo']          = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.tipo_experiencia', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-experiencia con trabajo_actual inválido debe retornar 422. */
    public function test_crear_experiencia_trabajo_actual_invalido_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosExperienciaValido();
        $datos['trabajo_actual'] = 'Quizás';
        $datos['archivo']        = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.trabajo_actual', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-experiencia con fecha_finalizacion anterior a fecha_inicio debe retornar 422. */
    public function test_crear_experiencia_fecha_finalizacion_anterior_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosExperienciaValido();
        $datos['trabajo_actual']      = 'No';
        $datos['fecha_inicio']        = '2022-06-01';
        $datos['fecha_finalizacion']  = '2021-01-01'; // anterior a inicio
        $datos['archivo']             = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.fecha_finalizacion', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-experiencia con intensidad_horaria > 168 debe retornar 422. */
    public function test_crear_experiencia_intensidad_horaria_excesiva_retorna_422(): void
    {
        Storage::fake('public');
        $user   = $this->crearDocente();
        $datos  = $this->datosExperienciaValido();
        $datos['intensidad_horaria'] = 200;
        $datos['archivo']            = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', $datos);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.intensidad_horaria', fn($v) => !empty($v));
    }

    /** POST /api/docente/crear-experiencia sin campos obligatorios debe retornar 422. */
    public function test_crear_experiencia_sin_campos_obligatorios_retorna_422(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->postJson('/api/docente/crear-experiencia', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors']);
    }

    /** POST /api/docente/crear-experiencia sin autenticación debe retornar 401. */
    public function test_crear_experiencia_sin_autenticacion_retorna_401(): void
    {
        Storage::fake('public');
        $datos = $this->datosExperienciaValido();
        $datos['archivo'] = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/docente/crear-experiencia', $datos);

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Obtener experiencias (lista)
    // ---------------------------------------------------------------

    /** GET /api/docente/obtener-experiencias sin registros debe retornar 200. */
    public function test_obtener_experiencias_sin_registros_retorna_200(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-experiencias');

        $response->assertStatus(200);
    }

    /** GET /api/docente/obtener-experiencias con registros debe retornar 200 con estructura. */
    public function test_obtener_experiencias_con_registros_retorna_200(): void
    {
        $user = $this->crearDocente();
        $this->crearExperiencia($user);

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-experiencias');

        $response->assertStatus(200)
                 ->assertJsonStructure(['experiencias']);
    }

    /** GET /api/docente/obtener-experiencias sin autenticación debe retornar 401. */
    public function test_obtener_experiencias_sin_autenticacion_retorna_401(): void
    {
        $response = $this->getJson('/api/docente/obtener-experiencias');

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Obtener experiencia por ID
    // ---------------------------------------------------------------

    /** GET /api/docente/obtener-experiencia/{id} con ID propio debe retornar 200. */
    public function test_obtener_experiencia_por_id_propio_retorna_200(): void
    {
        $user        = $this->crearDocente();
        $experiencia = $this->crearExperiencia($user);

        $response = $this->actingAs($user, 'api')
                         ->getJson("/api/docente/obtener-experiencia/{$experiencia->id_experiencia}");

        $response->assertStatus(200);
    }

    /** GET /api/docente/obtener-experiencia/{id} con ID inexistente debe retornar 404. */
    public function test_obtener_experiencia_id_inexistente_retorna_404(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/docente/obtener-experiencia/999999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Actualizar experiencia
    // ---------------------------------------------------------------

    /** PUT /api/docente/actualizar-experiencia/{id} con datos válidos debe retornar 200. */
    public function test_actualizar_experiencia_valida_retorna_200(): void
    {
        Storage::fake('public');
        $user        = $this->crearDocente();
        $experiencia = $this->crearExperiencia($user);

        $response = $this->actingAs($user, 'api')
                         ->putJson("/api/docente/actualizar-experiencia/{$experiencia->id_experiencia}", [
                             'cargo' => 'Profesor de planta',
                         ]);

        $response->assertStatus(200);
    }

    /** PUT /api/docente/actualizar-experiencia/{id} con tipo inválido debe retornar 422. */
    public function test_actualizar_experiencia_tipo_invalido_retorna_422(): void
    {
        $user        = $this->crearDocente();
        $experiencia = $this->crearExperiencia($user);

        $response = $this->actingAs($user, 'api')
                         ->putJson("/api/docente/actualizar-experiencia/{$experiencia->id_experiencia}", [
                             'tipo_experiencia' => 'Tipo Inexistente',
                         ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.tipo_experiencia', fn($v) => !empty($v));
    }

    // ---------------------------------------------------------------
    // Eliminar experiencia
    // ---------------------------------------------------------------

    /** DELETE /api/docente/eliminar-experiencia/{id} con ID propio debe retornar 200. */
    public function test_eliminar_experiencia_propia_retorna_200(): void
    {
        $user        = $this->crearDocente();
        $experiencia = $this->crearExperiencia($user);

        $response = $this->actingAs($user, 'api')
                         ->deleteJson("/api/docente/eliminar-experiencia/{$experiencia->id_experiencia}");

        $response->assertStatus(200);
    }

    /** DELETE /api/docente/eliminar-experiencia/{id} con ID inexistente debe retornar 404. */
    public function test_eliminar_experiencia_inexistente_retorna_404(): void
    {
        $user = $this->crearDocente();

        $response = $this->actingAs($user, 'api')
                         ->deleteJson('/api/docente/eliminar-experiencia/999999');

        $response->assertStatus(404);
    }

    /** DELETE /api/docente/eliminar-experiencia/{id} sin autenticación debe retornar 401. */
    public function test_eliminar_experiencia_sin_autenticacion_retorna_401(): void
    {
        $user        = $this->crearDocente();
        $experiencia = $this->crearExperiencia($user);

        $response = $this->deleteJson("/api/docente/eliminar-experiencia/{$experiencia->id_experiencia}");

        $response->assertStatus(401);
    }
}
