<?php

namespace Tests\Feature;

use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de gestión de usuarios por el Administrador.
 *
 * Cubre listado, cambio de rol, edición y eliminación de usuarios.
 * Todos los endpoints requieren autenticación con rol Administrador.
 */
class AdminUserApiTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Crea y retorna un usuario con rol Administrador. */
    private function crearAdministrador(): User
    {
        $uid  = uniqid();
        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '11' . substr($uid, -8),
            'primer_nombre'         => 'Admin',
            'primer_apellido'       => 'Test',
            'fecha_nacimiento'      => '1980-01-01',
            'email'                 => 'admin' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole('Administrador');
        return $user;
    }

    /** Crea y retorna un usuario con rol Docente. */
    private function crearDocente(): User
    {
        $uid  = uniqid();
        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '22' . substr($uid, -8),
            'primer_nombre'         => 'Docente',
            'primer_apellido'       => 'Test',
            'fecha_nacimiento'      => '1985-05-20',
            'email'                 => 'docadm' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole('Docente');
        return $user;
    }

    /** Crea y retorna un usuario con rol Aspirante. */
    private function crearAspirante(): User
    {
        $uid  = uniqid();
        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '33' . substr($uid, -8),
            'primer_nombre'         => 'Aspirante',
            'primer_apellido'       => 'Test',
            'fecha_nacimiento'      => '1995-08-15',
            'email'                 => 'asp' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole('Aspirante');
        return $user;
    }

    // ---------------------------------------------------------------
    // Listar usuarios
    // ---------------------------------------------------------------

    /** GET /api/admin/listar-usuarios como admin debe retornar 200 con lista de usuarios. */
    public function test_listar_usuarios_como_admin_retorna_200(): void
    {
        $admin = $this->crearAdministrador();

        $response = $this->actingAs($admin, 'api')
                         ->getJson('/api/admin/listar-usuarios');

        $response->assertStatus(200)
                 ->assertJsonStructure(['usuarios']);
    }

    /** GET /api/admin/listar-usuarios como Docente (sin permiso) debe retornar 403. */
    public function test_listar_usuarios_como_docente_retorna_403(): void
    {
        $docente = $this->crearDocente();

        $response = $this->actingAs($docente, 'api')
                         ->getJson('/api/admin/listar-usuarios');

        $response->assertStatus(403);
    }

    /** GET /api/admin/listar-usuarios sin autenticación debe retornar 401. */
    public function test_listar_usuarios_sin_autenticacion_retorna_401(): void
    {
        $response = $this->getJson('/api/admin/listar-usuarios');

        $response->assertStatus(401);
    }

    /** GET /api/admin/listar-usuarios verifica que la respuesta incluye datos del usuario. */
    public function test_listar_usuarios_respuesta_contiene_campos_esperados(): void
    {
        $admin    = $this->crearAdministrador();
        $docente  = $this->crearDocente();

        $response = $this->actingAs($admin, 'api')
                         ->getJson('/api/admin/listar-usuarios');

        $response->assertStatus(200);
        // Al menos uno de los usuarios listados debe tener email del docente creado
        $emails = collect($response->json('usuarios'))->pluck('email')->toArray();
        $this->assertContains($docente->email, $emails);
    }

    // ---------------------------------------------------------------
    // Cambiar rol
    // ---------------------------------------------------------------

    /** PUT /api/admin/usuarios/{id}/cambiar-rol con rol válido debe retornar 200. */
    public function test_cambiar_rol_usuario_valido_retorna_200(): void
    {
        $admin    = $this->crearAdministrador();
        $aspirante = $this->crearAspirante();

        $response = $this->actingAs($admin, 'api')
                         ->putJson("/api/admin/usuarios/{$aspirante->id}/cambiar-rol", [
                             'rol' => 'Docente',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Rol actualizado con éxito');
    }

    /** PUT /api/admin/usuarios/{id}/cambiar-rol con rol inexistente debe retornar 422. */
    public function test_cambiar_rol_usuario_rol_invalido_retorna_422(): void
    {
        $admin    = $this->crearAdministrador();
        $aspirante = $this->crearAspirante();

        $response = $this->actingAs($admin, 'api')
                         ->putJson("/api/admin/usuarios/{$aspirante->id}/cambiar-rol", [
                             'rol' => 'RolQueNoExiste',
                         ]);

        $response->assertStatus(422);
    }

    /** PUT /api/admin/usuarios/{id}/cambiar-rol sin el campo rol debe retornar 422. */
    public function test_cambiar_rol_sin_campo_rol_retorna_422(): void
    {
        $admin    = $this->crearAdministrador();
        $aspirante = $this->crearAspirante();

        $response = $this->actingAs($admin, 'api')
                         ->putJson("/api/admin/usuarios/{$aspirante->id}/cambiar-rol", []);

        $response->assertStatus(422);
    }

    /** PUT /api/admin/usuarios/{id}/cambiar-rol como Docente debe retornar 403. */
    public function test_cambiar_rol_como_docente_retorna_403(): void
    {
        $docente  = $this->crearDocente();
        $aspirante = $this->crearAspirante();

        $response = $this->actingAs($docente, 'api')
                         ->putJson("/api/admin/usuarios/{$aspirante->id}/cambiar-rol", [
                             'rol' => 'Docente',
                         ]);

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Editar usuario
    // ---------------------------------------------------------------

    /** PUT /api/admin/editar-usuario/{id} con datos válidos debe retornar 200. */
    public function test_editar_usuario_valido_retorna_200(): void
    {
        $admin    = $this->crearAdministrador();
        $docente  = $this->crearDocente();

        $response = $this->actingAs($admin, 'api')
                         ->putJson("/api/admin/editar-usuario/{$docente->id}", [
                             'primer_nombre' => 'NombreActualizado',
                         ]);

        $response->assertStatus(200);
    }

    /** PUT /api/admin/editar-usuario/{id} con email duplicado debe retornar 422. */
    public function test_editar_usuario_email_duplicado_retorna_422(): void
    {
        $admin   = $this->crearAdministrador();
        $docente = $this->crearDocente();

        // Intentar cambiar el email al mismo del administrador
        $response = $this->actingAs($admin, 'api')
                         ->putJson("/api/admin/editar-usuario/{$docente->id}", [
                             'email' => $admin->email,
                         ]);

        $response->assertStatus(422);
    }

    /** PUT /api/admin/editar-usuario/{id} con ID inexistente debe retornar 404. */
    public function test_editar_usuario_id_inexistente_retorna_404(): void
    {
        $admin = $this->crearAdministrador();

        $response = $this->actingAs($admin, 'api')
                         ->putJson('/api/admin/editar-usuario/999999', [
                             'primer_nombre' => 'NombreTest',
                         ]);

        $response->assertStatus(404);
    }

    /** PUT /api/admin/editar-usuario/{id} con fecha_nacimiento futura debe retornar 422. */
    public function test_editar_usuario_fecha_nacimiento_futura_retorna_422(): void
    {
        $admin   = $this->crearAdministrador();
        $docente = $this->crearDocente();

        $response = $this->actingAs($admin, 'api')
                         ->putJson("/api/admin/editar-usuario/{$docente->id}", [
                             'fecha_nacimiento' => '2099-12-31',
                         ]);

        $response->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // Eliminar usuario
    // ---------------------------------------------------------------

    /** DELETE /api/admin/eliminar-usuario/{id} con usuario existente debe retornar 200. */
    public function test_eliminar_usuario_existente_retorna_200(): void
    {
        $admin    = $this->crearAdministrador();
        $aspirante = $this->crearAspirante();

        $response = $this->actingAs($admin, 'api')
                         ->deleteJson("/api/admin/eliminar-usuario/{$aspirante->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Usuario eliminado');
    }

    /** DELETE /api/admin/eliminar-usuario/{id} con ID inexistente debe retornar 404. */
    public function test_eliminar_usuario_inexistente_retorna_404(): void
    {
        $admin = $this->crearAdministrador();

        $response = $this->actingAs($admin, 'api')
                         ->deleteJson('/api/admin/eliminar-usuario/999999');

        $response->assertStatus(404);
    }

    /** DELETE /api/admin/eliminar-usuario/{id} como Docente debe retornar 403. */
    public function test_eliminar_usuario_como_docente_retorna_403(): void
    {
        $docente  = $this->crearDocente();
        $aspirante = $this->crearAspirante();

        $response = $this->actingAs($docente, 'api')
                         ->deleteJson("/api/admin/eliminar-usuario/{$aspirante->id}");

        $response->assertStatus(403);
    }

    /** DELETE /api/admin/eliminar-usuario/{id} sin autenticación debe retornar 401. */
    public function test_eliminar_usuario_sin_autenticacion_retorna_401(): void
    {
        $aspirante = $this->crearAspirante();

        $response = $this->deleteJson("/api/admin/eliminar-usuario/{$aspirante->id}");

        $response->assertStatus(401);
    }
}
