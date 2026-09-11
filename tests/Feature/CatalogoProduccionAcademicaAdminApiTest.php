<?php

namespace Tests\Feature;

use App\Models\Aspirante\ProduccionAcademica;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\TiposProductoAcademico\ProductoAcademico;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas de integración del CRUD de catálogos de producción académica.
 *
 * Cubre tipos de producto académico y ámbitos de divulgación: creación, listado,
 * actualización, el bloqueo 409 al borrar catálogos en uso y el efecto de `activo`
 * sobre los endpoints públicos que alimentan los desplegables.
 *
 * Todos los endpoints de administración requieren rol Administrador.
 */
class CatalogoProduccionAcademicaAdminApiTest extends TestCase
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
            'numero_identificacion' => '77' . substr($uid, -8),
            'primer_nombre'         => 'Testcat',
            'primer_apellido'       => 'Produccion',
            'fecha_nacimiento'      => '1980-05-10',
            'email'                 => 'catprod' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole($rol);
        return $user;
    }

    /** Crea un producto académico con nombre único. */
    private function crearProducto(bool $activo = true): ProductoAcademico
    {
        return ProductoAcademico::create([
            'nombre_producto_academico' => 'Producto ' . uniqid(),
            'activo'                    => $activo,
        ]);
    }

    /** Crea un ámbito de divulgación bajo el producto dado. */
    private function crearAmbito(ProductoAcademico $producto, bool $activo = true): AmbitoDivulgacion
    {
        return AmbitoDivulgacion::create([
            'producto_academico_id'     => $producto->id_producto_academico,
            'nombre_ambito_divulgacion' => 'Ambito ' . uniqid(),
            'activo'                    => $activo,
        ]);
    }

    // ---------------------------------------------------------------
    // Autorización
    // ---------------------------------------------------------------

    /** Un usuario sin rol Administrador no puede listar el catálogo. */
    public function test_usuario_no_administrador_recibe_403(): void
    {
        $docente = $this->crearUsuarioConRol('Docente');

        $this->actingAs($docente, 'api')
             ->getJson('/api/admin/productos-academicos')
             ->assertStatus(403);

        $this->actingAs($docente, 'api')
             ->getJson('/api/admin/ambitos-divulgacion')
             ->assertStatus(403);
    }

    /** Sin autenticar, los endpoints de administración responden 401. */
    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson('/api/admin/productos-academicos')
             ->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Productos académicos
    // ---------------------------------------------------------------

    /** POST /api/admin/productos-academicos con datos válidos debe retornar 201 y persistir. */
    public function test_crear_producto_academico_retorna_201(): void
    {
        $admin  = $this->crearUsuarioConRol('Administrador');
        $nombre = 'Podcast académico ' . uniqid();

        $response = $this->actingAs($admin, 'api')
                         ->postJson('/api/admin/productos-academicos', [
                             'nombre_producto_academico' => $nombre,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.nombre_producto_academico', $nombre);

        $this->assertDatabaseHas('producto_academicos', [
            'nombre_producto_academico' => $nombre,
            'activo'                    => true,
        ]);
    }

    /** Un nombre de producto académico duplicado debe retornar 422. */
    public function test_crear_producto_academico_duplicado_retorna_422(): void
    {
        $admin    = $this->crearUsuarioConRol('Administrador');
        $producto = $this->crearProducto();

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/productos-academicos', [
                 'nombre_producto_academico' => $producto->nombre_producto_academico,
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.nombre_producto_academico', fn($v) => !empty($v));
    }

    /** PUT /api/admin/productos-academicos/{id} debe actualizar el nombre. */
    public function test_actualizar_producto_academico_retorna_200(): void
    {
        $admin       = $this->crearUsuarioConRol('Administrador');
        $producto    = $this->crearProducto();
        $nombreNuevo = 'Renombrado ' . uniqid();

        $this->actingAs($admin, 'api')
             ->putJson('/api/admin/productos-academicos/' . $producto->id_producto_academico, [
                 'nombre_producto_academico' => $nombreNuevo,
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas('producto_academicos', [
            'id_producto_academico'     => $producto->id_producto_academico,
            'nombre_producto_academico' => $nombreNuevo,
        ]);
    }

    /** Borrar un producto académico sin ámbitos asociados debe retornar 200. */
    public function test_eliminar_producto_academico_sin_ambitos_retorna_200(): void
    {
        $admin    = $this->crearUsuarioConRol('Administrador');
        $producto = $this->crearProducto();

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/productos-academicos/' . $producto->id_producto_academico)
             ->assertStatus(200);

        $this->assertDatabaseMissing('producto_academicos', [
            'id_producto_academico' => $producto->id_producto_academico,
        ]);
    }

    /** Borrar un producto académico con ámbitos asociados debe retornar 409 y no borrar nada. */
    public function test_eliminar_producto_academico_con_ambitos_retorna_409(): void
    {
        $admin    = $this->crearUsuarioConRol('Administrador');
        $producto = $this->crearProducto();
        $this->crearAmbito($producto);

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/productos-academicos/' . $producto->id_producto_academico)
             ->assertStatus(409)
             ->assertJsonPath('ambitos_asociados', 1);

        $this->assertDatabaseHas('producto_academicos', [
            'id_producto_academico' => $producto->id_producto_academico,
        ]);
    }

    /** Un producto académico inexistente debe retornar 404. */
    public function test_obtener_producto_academico_inexistente_retorna_404(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/productos-academicos/999999')
             ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Ámbitos de divulgación
    // ---------------------------------------------------------------

    /** POST /api/admin/ambitos-divulgacion con datos válidos debe retornar 201. */
    public function test_crear_ambito_divulgacion_retorna_201(): void
    {
        $admin    = $this->crearUsuarioConRol('Administrador');
        $producto = $this->crearProducto();
        $nombre   = 'Difusión piloto ' . uniqid();

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/ambitos-divulgacion', [
                 'producto_academico_id'     => $producto->id_producto_academico,
                 'nombre_ambito_divulgacion' => $nombre,
             ])
             ->assertStatus(201)
             ->assertJsonPath('data.nombre_ambito_divulgacion', $nombre);

        $this->assertDatabaseHas('ambito_divulgacions', [
            'nombre_ambito_divulgacion' => $nombre,
            'producto_academico_id'     => $producto->id_producto_academico,
        ]);
    }

    /** Crear un ámbito con un producto académico inexistente debe retornar 422. */
    public function test_crear_ambito_con_producto_inexistente_retorna_422(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/ambitos-divulgacion', [
                 'producto_academico_id'     => 999999,
                 'nombre_ambito_divulgacion' => 'Ambito huérfano',
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.producto_academico_id', fn($v) => !empty($v));
    }

    /** El mismo nombre de ámbito puede repetirse en productos distintos, pero no dentro del mismo. */
    public function test_nombre_de_ambito_es_unico_solo_dentro_del_producto(): void
    {
        $admin     = $this->crearUsuarioConRol('Administrador');
        $productoA = $this->crearProducto();
        $productoB = $this->crearProducto();
        $nombre    = 'Difusión internacional ' . uniqid();

        // Mismo nombre bajo otro producto: permitido.
        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/ambitos-divulgacion', [
                 'producto_academico_id'     => $productoA->id_producto_academico,
                 'nombre_ambito_divulgacion' => $nombre,
             ])
             ->assertStatus(201);

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/ambitos-divulgacion', [
                 'producto_academico_id'     => $productoB->id_producto_academico,
                 'nombre_ambito_divulgacion' => $nombre,
             ])
             ->assertStatus(201);

        // Repetirlo dentro del mismo producto: rechazado.
        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/ambitos-divulgacion', [
                 'producto_academico_id'     => $productoA->id_producto_academico,
                 'nombre_ambito_divulgacion' => $nombre,
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.nombre_ambito_divulgacion', fn($v) => !empty($v));
    }

    /** Borrar un ámbito usado por una producción académica debe retornar 409. */
    public function test_eliminar_ambito_con_producciones_retorna_409(): void
    {
        $admin    = $this->crearUsuarioConRol('Administrador');
        $docente  = $this->crearUsuarioConRol('Docente');
        $producto = $this->crearProducto();
        $ambito   = $this->crearAmbito($producto);

        ProduccionAcademica::create([
            'user_id'               => $docente->id,
            'ambito_divulgacion_id' => $ambito->id_ambito_divulgacion,
            'titulo'                => 'Artículo de prueba',
            'numero_autores'        => 2,
            'medio_divulgacion'     => 'Revista de prueba',
            'fecha_divulgacion'     => '2024-06-01',
        ]);

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/ambitos-divulgacion/' . $ambito->id_ambito_divulgacion)
             ->assertStatus(409)
             ->assertJsonPath('producciones_asociadas', 1);

        $this->assertDatabaseHas('ambito_divulgacions', [
            'id_ambito_divulgacion' => $ambito->id_ambito_divulgacion,
        ]);
    }

    /** Borrar un ámbito sin producciones asociadas debe retornar 200. */
    public function test_eliminar_ambito_sin_producciones_retorna_200(): void
    {
        $admin    = $this->crearUsuarioConRol('Administrador');
        $producto = $this->crearProducto();
        $ambito   = $this->crearAmbito($producto);

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/ambitos-divulgacion/' . $ambito->id_ambito_divulgacion)
             ->assertStatus(200);

        $this->assertDatabaseMissing('ambito_divulgacions', [
            'id_ambito_divulgacion' => $ambito->id_ambito_divulgacion,
        ]);
    }

    // ---------------------------------------------------------------
    // Efecto de `activo` sobre los desplegables públicos
    // ---------------------------------------------------------------

    /** Un producto inactivo desaparece del catálogo público pero sigue en el listado del admin. */
    public function test_producto_inactivo_sale_del_catalogo_publico_pero_no_del_admin(): void
    {
        $admin           = $this->crearUsuarioConRol('Administrador');
        $productoActivo  = $this->crearProducto();
        $productoRetirado = $this->crearProducto(activo: false);

        $publico = $this->getJson('/api/tiposProduccionAcademica/productos-academicos')
                        ->assertStatus(200)
                        ->json();

        $nombresPublicos = array_column($publico, 'nombre_producto_academico');
        $this->assertContains($productoActivo->nombre_producto_academico, $nombresPublicos);
        $this->assertNotContains($productoRetirado->nombre_producto_academico, $nombresPublicos);

        // El admin sí lo sigue viendo, para poder reactivarlo.
        $this->actingAs($admin, 'api')
             ->getJson('/api/admin/productos-academicos')
             ->assertStatus(200)
             ->assertJsonFragment(['nombre_producto_academico' => $productoRetirado->nombre_producto_academico]);
    }

    /** Un ámbito inactivo desaparece del catálogo público del producto al que pertenece. */
    public function test_ambito_inactivo_sale_del_catalogo_publico(): void
    {
        $producto        = $this->crearProducto();
        $ambitoActivo    = $this->crearAmbito($producto);
        $ambitoRetirado  = $this->crearAmbito($producto, activo: false);

        $ambitos = $this->getJson('/api/tiposProduccionAcademica/ambitos_divulgacion/' . $producto->id_producto_academico)
                        ->assertStatus(200)
                        ->json();

        $nombres = array_column($ambitos, 'nombre_ambito_divulgacion');
        $this->assertContains($ambitoActivo->nombre_ambito_divulgacion, $nombres);
        $this->assertNotContains($ambitoRetirado->nombre_ambito_divulgacion, $nombres);
    }

    /** No se puede registrar una producción académica con un ámbito retirado del catálogo. */
    public function test_no_se_puede_usar_un_ambito_inactivo_en_una_produccion(): void
    {
        $docente  = $this->crearUsuarioConRol('Docente');
        $producto = $this->crearProducto();
        $ambito   = $this->crearAmbito($producto, activo: false);

        $this->actingAs($docente, 'api')
             ->postJson('/api/docente/crear-produccion', [
                 'ambito_divulgacion_id' => $ambito->id_ambito_divulgacion,
                 'titulo'                => 'Artículo con ámbito retirado',
                 'numero_autores'        => 1,
                 'medio_divulgacion'     => 'Revista de prueba',
                 'fecha_divulgacion'     => '2024-06-01',
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.ambito_divulgacion_id', fn($v) => !empty($v));
    }
}
