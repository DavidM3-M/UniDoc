<?php

namespace Tests\Feature;

use App\Models\Aspirante\Experiencia;
use App\Models\ExamenIdioma;
use App\Models\Idioma as IdiomaCatalogo;
use App\Models\NivelFormacionAcademica;
use App\Models\TipoExperiencia;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Integridad entre los catálogos que administra el Administrador y los datos que ya
 * dependen de ellos.
 *
 * Cubre los tres hallazgos críticos de la auditoría, que compartían una misma causa: los
 * catálogos se referencian por nombre y no había nada que impidiera borrarlos, renombrarlos
 * ni desactivarlos por debajo de los registros que los usaban.
 */
class CatalogoIntegridadApiTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function crearUsuarioConRol(string $rol): User
    {
        $uid = uniqid();
        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '77' . substr($uid, -8),
            'primer_nombre'         => 'Testintegridad',
            'primer_apellido'       => 'Catalogo',
            'fecha_nacimiento'      => '1982-03-15',
            'email'                 => 'integridad' . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);
        $user->assignRole($rol);
        return $user;
    }

    private function crearNivel(): NivelFormacionAcademica
    {
        return NivelFormacionAcademica::create([
            'nivel_academico'  => 'Posgrado',
            'nivel_formacion'  => 'Maestria ' . uniqid(),
            'orden'            => 50,
            'activo'           => true,
        ]);
    }

    private function crearExamen(): ExamenIdioma
    {
        $idioma = IdiomaCatalogo::create([
            'nombre_idioma' => 'Neerlandes ' . uniqid(),
            'activo'        => true,
        ]);

        return ExamenIdioma::create([
            'idioma_catalogo_id' => $idioma->id_idioma_catalogo,
            'nombre_examen'      => 'NT2 ' . uniqid(),
            'vigencia_meses'     => 24,
            'activo'             => true,
        ]);
    }

    // ---------------------------------------------------------------
    // C-1 · El examen de idioma sostiene la vigencia de los certificados
    // ---------------------------------------------------------------

    /**
     * Borrar un examen con certificados asociados debe responder 409.
     *
     * Sin esta guarda, `idiomas.examen_idioma_id` quedaba en SET NULL y con él desaparecía
     * `vigencia_meses`; `calcularVigencia()` interpreta la ausencia como "no vence", así que
     * los certificados caducados volvían a mostrarse como vigentes.
     */
    public function test_eliminar_examen_con_certificados_retorna_409(): void
    {
        $admin   = $this->crearUsuarioConRol('Administrador');
        $docente = $this->crearUsuarioConRol('Docente');
        $examen  = $this->crearExamen();

        DB::table('idiomas')->insert([
            'user_id'            => $docente->id,
            'idioma'             => 'Neerlandes',
            'institucion_idioma' => 'NT2',
            'fecha_certificado'  => '2015-01-01',
            'nivel'              => 'B2',
            'examen_idioma_id'   => $examen->id_examen_idioma,
            'puntaje_obtenido'   => 80,
        ]);

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/examenes-idioma/' . $examen->id_examen_idioma)
             ->assertStatus(409)
             ->assertJsonPath('certificados_asociados', 1);

        $this->assertDatabaseHas('examenes_idioma', [
            'id_examen_idioma' => $examen->id_examen_idioma,
        ]);
    }

    /** Un examen sin nada colgando sí se puede borrar. */
    public function test_eliminar_examen_sin_dependencias_retorna_200(): void
    {
        $admin  = $this->crearUsuarioConRol('Administrador');
        $examen = $this->crearExamen();

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/examenes-idioma/' . $examen->id_examen_idioma)
             ->assertStatus(200);

        $this->assertDatabaseMissing('examenes_idioma', [
            'id_examen_idioma' => $examen->id_examen_idioma,
        ]);
    }

    // ---------------------------------------------------------------
    // C-2 · El nivel de formación sostiene el escalafón, y por nombre
    // ---------------------------------------------------------------

    /** Borrar un nivel del que cuelga un escalón debe responder 409, no un 500 de la base. */
    public function test_eliminar_nivel_usado_por_un_escalon_retorna_409(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');
        $nivel = $this->crearNivel();

        DB::table('escalones_docente')->insert([
            'nombre'           => 'Escalon ' . uniqid(),
            'orden'            => 90,
            'formacion_minima' => $nivel->nivel_formacion,
            'activo'           => true,
        ]);

        $this->actingAs($admin, 'api')
             ->deleteJson('/api/admin/niveles-formacion-academica/' . $nivel->id_nivel_formacion_academica)
             ->assertStatus(409)
             ->assertJsonPath('escalones_asociados', 1);
    }

    /**
     * Renombrar un nivel debe arrastrar el nuevo nombre a todo lo que lo referenciaba.
     *
     * Es el caso de la tilde: el motor del escalafón compara `formacion_minima` literalmente
     * contra `nivel_formacion`, así que sin propagación la regla dejaba de cumplirla nadie
     * y no se producía ningún error visible.
     */
    public function test_renombrar_nivel_propaga_a_escalones_reglas_y_estudios(): void
    {
        $admin   = $this->crearUsuarioConRol('Administrador');
        $docente = $this->crearUsuarioConRol('Docente');
        $nivel   = $this->crearNivel();
        $viejo   = $nivel->nivel_formacion;
        $nuevo   = $viejo . ' corregido';

        $idEscalon = DB::table('escalones_docente')->insertGetId([
            'nombre'           => 'Escalon ' . uniqid(),
            'orden'            => 91,
            'formacion_minima' => $viejo,
            'activo'           => true,
        ], 'id_escalon');

        DB::table('reglas_excepcion_escalon')->insert([
            'tipo_condicion'      => 'formacion',
            'valor_condicion'     => $viejo,
            'escalon_otorgado_id' => $idEscalon,
            'activo'              => true,
        ]);

        DB::table('estudios')->insert([
            'user_id'            => $docente->id,
            'tipo_estudio'       => $viejo,
            'graduado'           => 'Si',
            'institucion'        => 'Universidad de prueba',
            'titulo_estudio'     => 'Titulo de prueba',
            'titulo_convalidado' => 'No',
            'fecha_inicio'       => '2015-01-01',
        ]);

        $this->actingAs($admin, 'api')
             ->putJson('/api/admin/niveles-formacion-academica/' . $nivel->id_nivel_formacion_academica,
                       ['nivel_formacion' => $nuevo])
             ->assertStatus(200)
             ->assertJsonPath('nombre_propagado', true);

        $this->assertDatabaseHas('escalones_docente', ['formacion_minima' => $nuevo]);
        $this->assertDatabaseHas('reglas_excepcion_escalon', ['valor_condicion' => $nuevo]);
        $this->assertDatabaseHas('estudios', ['tipo_estudio' => $nuevo]);
        $this->assertDatabaseMissing('escalones_docente', ['formacion_minima' => $viejo]);
    }

    /** Un escalón no puede exigir una formación que no existe en el catálogo. */
    public function test_crear_escalon_con_formacion_inventada_retorna_422(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');

        $this->actingAs($admin, 'api')
             ->postJson('/api/admin/escalones-docente', [
                 'nombre'           => 'Escalon ' . uniqid(),
                 'orden'            => 92,
                 'formacion_minima' => 'Nivel que no existe ' . uniqid(),
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.formacion_minima', fn($v) => !empty($v));
    }

    // ---------------------------------------------------------------
    // C-3 · Desactivar un catálogo no debe atrapar los registros previos
    // ---------------------------------------------------------------

    /**
     * Con el tipo ya desactivado, el docente debe poder seguir guardando SU registro.
     *
     * Antes esto devolvía 422 y lo dejaba sin salida: el select ya no ofrecía la opción, así
     * que tampoco podía cambiarla sin falsear su propio historial.
     */
    public function test_actualizar_experiencia_con_tipo_desactivado_despues_retorna_200(): void
    {
        $docente = $this->crearUsuarioConRol('Docente');
        $tipo    = TipoExperiencia::create([
            'nombre_tipo_experiencia' => 'Catedra ' . uniqid(),
            'activo'                  => true,
        ]);

        $crear = $this->actingAs($docente, 'api')->postJson('/api/docente/crear-experiencia', [
            'tipo_experiencia'        => $tipo->nombre_tipo_experiencia,
            'institucion_experiencia' => 'Centro de Investigaciones',
            'cargo'                   => 'Investigador auxiliar',
            'trabajo_actual'          => 'Si',
            'fecha_inicio'            => '2020-03-01',
            'archivo'                 => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
        ]);
        $crear->assertStatus(201);

        $experiencia = Experiencia::where('user_id', $docente->id)->firstOrFail();

        // El Administrador retira el tipo del catálogo.
        $tipo->update(['activo' => false]);

        // El docente corrige el cargo de su propio registro: debe poder.
        $this->actingAs($docente, 'api')
             ->putJson('/api/docente/actualizar-experiencia/' . $experiencia->id_experiencia, [
                 'tipo_experiencia' => $tipo->nombre_tipo_experiencia,
                 'cargo'            => 'Investigador principal',
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas('experiencias', [
            'id_experiencia' => $experiencia->id_experiencia,
            'cargo'          => 'Investigador principal',
        ]);
    }

    /** Pero estrenar un tipo inactivo distinto del guardado sigue prohibido. */
    public function test_actualizar_experiencia_estrenando_tipo_inactivo_retorna_422(): void
    {
        $docente = $this->crearUsuarioConRol('Docente');
        $activo  = TipoExperiencia::create([
            'nombre_tipo_experiencia' => 'Vigente ' . uniqid(),
            'activo'                  => true,
        ]);
        $otroInactivo = TipoExperiencia::create([
            'nombre_tipo_experiencia' => 'Retirado ' . uniqid(),
            'activo'                  => false,
        ]);

        $this->actingAs($docente, 'api')->postJson('/api/docente/crear-experiencia', [
            'tipo_experiencia'        => $activo->nombre_tipo_experiencia,
            'institucion_experiencia' => 'Centro de Investigaciones',
            'cargo'                   => 'Investigador auxiliar',
            'trabajo_actual'          => 'Si',
            'fecha_inicio'            => '2020-03-01',
            'archivo'                 => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
        ])->assertStatus(201);

        $experiencia = Experiencia::where('user_id', $docente->id)->firstOrFail();

        $this->actingAs($docente, 'api')
             ->putJson('/api/docente/actualizar-experiencia/' . $experiencia->id_experiencia, [
                 'tipo_experiencia' => $otroInactivo->nombre_tipo_experiencia,
             ])
             ->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // A-1 · Los nombres reales llevan puntos, comas y paréntesis
    // ---------------------------------------------------------------

    /** Una institución con puntos —"I.E. Normal Superior"— tiene que poder registrarse. */
    public function test_crear_experiencia_con_puntos_en_la_institucion_retorna_201(): void
    {
        $docente = $this->crearUsuarioConRol('Docente');
        $tipo    = TipoExperiencia::create([
            'nombre_tipo_experiencia' => 'Docencia ' . uniqid(),
            'activo'                  => true,
        ]);

        $this->actingAs($docente, 'api')
             ->postJson('/api/docente/crear-experiencia', [
                 'tipo_experiencia'        => $tipo->nombre_tipo_experiencia,
                 'institucion_experiencia' => 'I.E. Normal Superior (sede B)',
                 'cargo'                   => 'Docente de Matematicas, area basica',
                 'trabajo_actual'          => 'Si',
                 'fecha_inicio'            => '2020-03-01',
                 'archivo'                 => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
             ])
             ->assertStatus(201);

        $this->assertDatabaseHas('experiencias', [
            'institucion_experiencia' => 'I.E. Normal Superior (sede B)',
        ]);
    }

    /** Los emojis, que es lo que la regla decía bloquear, siguen bloqueados. */
    public function test_crear_experiencia_con_emoji_retorna_422(): void
    {
        $docente = $this->crearUsuarioConRol('Docente');
        $tipo    = TipoExperiencia::create([
            'nombre_tipo_experiencia' => 'Docencia ' . uniqid(),
            'activo'                  => true,
        ]);

        $this->actingAs($docente, 'api')
             ->postJson('/api/docente/crear-experiencia', [
                 'tipo_experiencia'        => $tipo->nombre_tipo_experiencia,
                 'institucion_experiencia' => 'Universidad 🎓 del Cauca',
                 'cargo'                   => 'Docente de planta',
                 'trabajo_actual'          => 'Si',
                 'fecha_inicio'            => '2020-03-01',
                 'archivo'                 => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
             ])
             ->assertStatus(422)
             ->assertJsonPath('errors.institucion_experiencia', fn($v) => !empty($v));
    }
}
