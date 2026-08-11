<?php

namespace Tests\Feature;

use App\Models\Aspirante\Experiencia;
use App\Models\TipoExperiencia;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de integración del CRUD del catálogo de tipos de experiencia.
 *
 * El catálogo se referencia por **nombre** (no hay FK con `experiencias`), así que además
 * del CRUD se prueban las dos consecuencias de esa decisión: el renombrado tiene que
 * propagarse a los registros históricos, y el borrado tiene que bloquearse si el tipo está en uso.
 */
class TipoExperienciaAdminApiTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Crea y retorna un usuario con el rol indicado. */
    private function crearUsuarioConRol(string $rol): User
    {
        $uid = uniqid();
        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '88' . substr($uid, -8),
            'primer_nombre'         => 'Testtipo',
            'primer_apellido'       => 'Experiencia',
            'fecha_nacimiento'      => '1982-03-15',
            'email'                 => 'tipoexp' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole($rol);
        return $user;
    }

    /** Crea un tipo de experiencia con nombre único. */
    private function crearTipo(bool $activo = true): TipoExperiencia
    {
        return TipoExperiencia::create([
            'nombre_tipo_experiencia' => 'Consultoria ' . uniqid(),
            'activo'                  => $activo,
        ]);
    }

    /** Datos válidos mínimos para crear una experiencia con el tipo dado. */
    private function datosExperiencia(string $tipo): array
    {
        return [
            'tipo_experiencia'        => $tipo,
            'institucion_experiencia' => 'Centro de Investigaciones',
            'cargo'                   => 'Investigador auxiliar',
            'trabajo_actual'          => 'Si',
            'fecha_inicio'            => '2020-03-01',
            'archivo'                 => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
        ];
    }

    // ---------------------------------------------------------------
    // Autorización
    // ---------------------------------------------------------------

    /** Un usuario sin rol Administrador no puede administrar el catálogo. */
    public function test_usuario_no_administrador_recibe_403(): void
    {
        $docente = $this->crearUsuarioConRol('Docente');

        $this->actingAs($docente, 'api')
             ->getJson('/api/admin/tipos-experiencia')
             ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    /** POST /api/admin/tipos-experiencia debe retornar 201 y aparecer en el catálogo público. */
    public function test_crear_tipo_experiencia_aparece_en_constantes(): void
    {
        $admin  = $this->crearUsuarioConRol('Administrador');
        $nombre = 'Consultoria ' . uniqid();

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/tipos-experiencia', ['nombre_tipo_experiencia' => $nombre])
             ->assertStatus(201)
             ->assertJsonPath('data.nombre_tipo_experiencia', $nombre);

        $this->assertDatabaseHas('tipo_experiencias', [
            'nombre_tipo_experiencia' => $nombre,
            'activo'                  => true,
        ]);

        // El endpoint de constantes conserva el mismo formato: array plano de nombres.
        $this->getJson('/api/constantes/tipos-experiencia')
             ->assertStatus(200)
             ->assertJsonPath('tipo_experiencia', fn($v) => in_array($nombre, $v, true));
    }

    /** Un nombre de tipo de experiencia duplicado debe retornar 422. */
    public function test_crear_tipo_duplicado_retorna_422(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');
        $tipo  = $this->crearTipo();

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/tipos-experiencia', [
                 'nombre_tipo_experiencia' => $tipo->nombre_tipo_experiencia,
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.nombre_tipo_experiencia', fn($v) => !empty($v));
    }

    /** Un tipo de experiencia inexistente debe retornar 404. */
    public function test_obtener_tipo_inexistente_retorna_404(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/tipos-experiencia/999999')
             ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Consumo desde el formulario de experiencia
    // ---------------------------------------------------------------

    /** Una experiencia creada con un tipo recién agregado debe aceptarse: la validación lee la tabla. */
    public function test_crear_experiencia_con_tipo_nuevo_retorna_201(): void
    {
        Storage::fake('public');
        $admin   = $this->crearUsuarioConRol('Administrador');
        $docente = $this->crearUsuarioConRol('Docente');
        $nombre  = 'Consultoria ' . uniqid();

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/tipos-experiencia', ['nombre_tipo_experiencia' => $nombre])
             ->assertStatus(201);

        $this->actingAs($docente, 'api')
             ->postJson('/api/docente/crear-experiencia', $this->datosExperiencia($nombre))
             ->assertStatus(201);
    }

    /** Un tipo marcado como inactivo ya no se puede usar en experiencias nuevas. */
    public function test_crear_experiencia_con_tipo_inactivo_retorna_422(): void
    {
        Storage::fake('public');
        $docente = $this->crearUsuarioConRol('Docente');
        $tipo    = $this->crearTipo(activo: false);

        $this->actingAs($docente, 'api')
             ->postJson('/api/docente/crear-experiencia', $this->datosExperiencia($tipo->nombre_tipo_experiencia))
             ->assertStatus(422)
             ->assertJsonPath('errors.tipo_experiencia', fn($v) => !empty($v));
    }

    /** Un tipo inactivo desaparece del desplegable de constantes. */
    public function test_tipo_inactivo_sale_del_catalogo_publico(): void
    {
        $tipoActivo   = $this->crearTipo();
        $tipoRetirado = $this->crearTipo(activo: false);

        $nombres = $this->getJson('/api/constantes/tipos-experiencia')
                        ->assertStatus(200)
                        ->json('tipo_experiencia');

        $this->assertContains($tipoActivo->nombre_tipo_experiencia, $nombres);
        $this->assertNotContains($tipoRetirado->nombre_tipo_experiencia, $nombres);
    }

    // ---------------------------------------------------------------
    // Renombrado: propagación al histórico
    // ---------------------------------------------------------------

    /**
     * Renombrar un tipo en uso debe arrastrar las experiencias ya registradas.
     *
     * Sin esta propagación los registros históricos quedarían apuntando a un nombre que ya
     * no existe en el catálogo, y fallarían la validación la próxima vez que se editaran.
     */
    public function test_renombrar_tipo_propaga_el_cambio_a_experiencias(): void
    {
        $admin       = $this->crearUsuarioConRol('Administrador');
        $docente     = $this->crearUsuarioConRol('Docente');
        $tipo        = $this->crearTipo();
        $nombreNuevo = 'Consultoria externa ' . uniqid();

        $experiencia = Experiencia::create([
            'user_id'                 => $docente->id,
            'tipo_experiencia'        => $tipo->nombre_tipo_experiencia,
            'institucion_experiencia' => 'Universidad Nacional',
            'cargo'                   => 'Profesor titular',
            'trabajo_actual'          => 'No',
            'fecha_inicio'            => '2015-01-15',
            'fecha_finalizacion'      => '2020-12-31',
        ]);

        $this->actingAs($admin, 'api')
             ->putJson('/api/admin/tipos-experiencia/' . $tipo->id_tipo_experiencia, [
                 'nombre_tipo_experiencia' => $nombreNuevo,
             ])
             ->assertStatus(200)
             ->assertJsonPath('registros_renombrados.experiencias', 1);

        $this->assertDatabaseHas('experiencias', [
            'id_experiencia'   => $experiencia->id_experiencia,
            'tipo_experiencia' => $nombreNuevo,
        ]);
    }

    /** Cambiar solo `activo` no debe contar como renombrado ni tocar el histórico. */
    public function test_desactivar_tipo_no_renombra_nada(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');
        $tipo  = $this->crearTipo();

        $this->actingAs($admin, 'api')
             ->putJson('/api/admin/tipos-experiencia/' . $tipo->id_tipo_experiencia, ['activo' => false])
             ->assertStatus(200)
             ->assertJsonPath('registros_renombrados.experiencias', 0)
             ->assertJsonPath('data.activo', false);
    }

    // ---------------------------------------------------------------
    // Borrado
    // ---------------------------------------------------------------

    /** Borrar un tipo de experiencia en uso debe retornar 409 y conservar el registro. */
    public function test_eliminar_tipo_en_uso_retorna_409(): void
    {
        $admin   = $this->crearUsuarioConRol('Administrador');
        $docente = $this->crearUsuarioConRol('Docente');
        $tipo    = $this->crearTipo();

        Experiencia::create([
            'user_id'                 => $docente->id,
            'tipo_experiencia'        => $tipo->nombre_tipo_experiencia,
            'institucion_experiencia' => 'Universidad Nacional',
            'cargo'                   => 'Profesor titular',
            'trabajo_actual'          => 'No',
            'fecha_inicio'            => '2015-01-15',
            'fecha_finalizacion'      => '2020-12-31',
        ]);

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/tipos-experiencia/' . $tipo->id_tipo_experiencia)
             ->assertStatus(409)
             ->assertJsonPath('experiencias_asociadas', 1);

        $this->assertDatabaseHas('tipo_experiencias', [
            'id_tipo_experiencia' => $tipo->id_tipo_experiencia,
        ]);
    }

    /** Borrar un tipo de experiencia sin uso debe retornar 200. */
    public function test_eliminar_tipo_sin_uso_retorna_200(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');
        $tipo  = $this->crearTipo();

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/tipos-experiencia/' . $tipo->id_tipo_experiencia)
             ->assertStatus(200);

        $this->assertDatabaseMissing('tipo_experiencias', [
            'id_tipo_experiencia' => $tipo->id_tipo_experiencia,
        ]);
    }
}
