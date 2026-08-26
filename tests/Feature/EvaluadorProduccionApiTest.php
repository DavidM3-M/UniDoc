<?php

namespace Tests\Feature;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\Usuario\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas del rol Evaluador de Producción y del traslado del aval desde Apoyo Profesoral.
 *
 * Lo que se protege acá no es el CRUD: es que avalar una producción otorga puntos de escalafón
 * (`MotorEscalafonDocenteService::calcularPuntaje`), y por tanto que solo pueda hacerlo el rol
 * correcto, que quede firmado y que caiga sobre la producción completa y no sobre un archivo suelto.
 */
class EvaluadorProduccionApiTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function crearUsuario(string $rol, string $prefijo): User
    {
        $uid = uniqid();

        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '77' . substr($uid, -8),
            'primer_nombre'         => 'Test' . $prefijo,
            'primer_apellido'       => 'Produccion',
            'fecha_nacimiento'      => '1980-04-12',
            'email'                 => $prefijo . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);

        $user->assignRole($rol);

        return $user;
    }

    private function crearEvaluador(): User
    {
        return $this->crearUsuario('Evaluador Produccion', 'evalprod');
    }

    private function crearDocente(): User
    {
        return $this->crearUsuario('Docente', 'docprod');
    }

    /** Un ámbito de divulgación real del catálogo, con su puntaje. */
    private function ambitoConPuntaje(): AmbitoDivulgacion
    {
        $ambito = AmbitoDivulgacion::where('puntaje', '>', 0)->first();

        if (!$ambito) {
            $this->markTestSkipped('El catálogo de ámbitos de divulgación está vacío.');
        }

        return $ambito;
    }

    /**
     * Crea una producción del docente con `$documentos` archivos en estado pendiente.
     */
    private function crearProduccion(User $docente, int $documentos = 1, array $extra = []): ProduccionAcademica
    {
        $produccion = ProduccionAcademica::create(array_merge([
            'user_id'               => $docente->id,
            'ambito_divulgacion_id' => $this->ambitoConPuntaje()->id_ambito_divulgacion,
            'titulo'                => 'Produccion de prueba ' . uniqid(),
            'numero_autores'        => 2,
            'medio_divulgacion'     => 'Revista de Pruebas',
            'fecha_divulgacion'     => '2025-05-10',
        ], $extra));

        for ($i = 0; $i < $documentos; $i++) {
            Documento::create([
                'archivo'           => "documentos/prueba-{$i}-" . uniqid() . '.pdf',
                'estado'            => 'pendiente',
                'documentable_id'   => $produccion->id_produccion_academica,
                'documentable_type' => ProduccionAcademica::class,
            ]);
        }

        return $produccion->fresh('documentosProduccionAcademica');
    }

    private function crearDocumentoDeEstudio(User $docente): Documento
    {
        $estudio = \App\Models\Aspirante\Estudio::create([
            'user_id'            => $docente->id,
            'tipo_estudio'       => 'Pregrado',
            'graduado'           => 'Si',
            'institucion'        => 'Universidad de Pruebas',
            'titulo_convalidado' => 'No',
            'titulo_estudio'     => 'Ingenieria de Pruebas',
            'fecha_inicio'       => '2010-01-15',
            'fecha_graduacion'   => '2015-06-30',
        ]);

        return Documento::create([
            'archivo'           => 'documentos/estudio-' . uniqid() . '.pdf',
            'estado'            => 'pendiente',
            'documentable_id'   => $estudio->id_estudio,
            'documentable_type' => \App\Models\Aspirante\Estudio::class,
        ]);
    }

    // ---------------------------------------------------------------
    // Traslado del aval: quién puede decidir sobre qué
    // ---------------------------------------------------------------

    /**
     * El bloqueo del aval tiene que estar en el servidor. Esconder los botones en
     * `VerProduccionAcademicaDocente.tsx` dejaría el endpoint abierto a una petición directa.
     */
    public function test_apoyo_profesoral_no_puede_avalar_produccion_academica(): void
    {
        $apoyo     = $this->crearUsuario('Apoyo Profesoral', 'apoyoprod');
        $docente   = $this->crearDocente();
        $produccion = $this->crearProduccion($docente);
        $documento = $produccion->documentosProduccionAcademica->first();

        $respuesta = $this->actingAs($apoyo, 'api')
            ->putJson("/api/apoyoProfesoral/actualizar-documento/{$documento->id_documento}", [
                'estado' => 'aprobado',
            ]);

        $respuesta->assertStatus(403);
        $this->assertSame('pendiente', $documento->fresh()->estado);
    }

    /** Lo demás sigue siendo suyo: el traslado es solo de producción académica. */
    public function test_apoyo_profesoral_sigue_avalando_documentos_de_estudio(): void
    {
        $apoyo     = $this->crearUsuario('Apoyo Profesoral', 'apoyoest');
        $docente   = $this->crearDocente();
        $documento = $this->crearDocumentoDeEstudio($docente);

        $respuesta = $this->actingAs($apoyo, 'api')
            ->putJson("/api/apoyoProfesoral/actualizar-documento/{$documento->id_documento}", [
                'estado' => 'aprobado',
            ]);

        $respuesta->assertStatus(200);

        $documento->refresh();
        $this->assertSame('aprobado', $documento->estado);
        // El traslado trajo trazabilidad también para las categorías que se quedan.
        $this->assertSame($apoyo->id, $documento->revisado_por);
        $this->assertNotNull($documento->revisado_en);
    }

    public function test_un_docente_no_entra_a_la_bandeja_del_evaluador(): void
    {
        $docente = $this->crearDocente();

        $this->actingAs($docente, 'api')
            ->getJson('/api/evaluadorProduccion/producciones')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Bandeja: se ven todas, no solo las pendientes
    // ---------------------------------------------------------------

    /**
     * Es la corrección de fondo del controlador anterior, que filtraba
     * `where('estado', 'pendiente')` y hacía desaparecer todo lo ya decidido.
     */
    public function test_la_bandeja_sin_filtro_devuelve_tambien_las_ya_decididas(): void
    {
        $evaluador = $this->crearEvaluador();
        $docente   = $this->crearDocente();

        $pendiente = $this->crearProduccion($docente);
        $avalada   = $this->crearProduccion($docente);

        $this->actingAs($evaluador, 'api')
            ->putJson("/api/evaluadorProduccion/producciones/{$avalada->id_produccion_academica}/avalar")
            ->assertStatus(200);

        $respuesta = $this->actingAs($evaluador, 'api')
            ->getJson('/api/evaluadorProduccion/producciones?docente=' . $docente->id);

        $respuesta->assertStatus(200);

        $ids = collect($respuesta->json('data'))->pluck('id_produccion_academica');

        $this->assertTrue($ids->contains($pendiente->id_produccion_academica));
        $this->assertTrue($ids->contains($avalada->id_produccion_academica));
    }

    public function test_el_filtro_de_estado_separa_pendientes_de_avaladas(): void
    {
        $evaluador = $this->crearEvaluador();
        $docente   = $this->crearDocente();

        $pendiente = $this->crearProduccion($docente);
        $avalada   = $this->crearProduccion($docente);

        $this->actingAs($evaluador, 'api')
            ->putJson("/api/evaluadorProduccion/producciones/{$avalada->id_produccion_academica}/avalar");

        $soloPendientes = $this->actingAs($evaluador, 'api')
            ->getJson('/api/evaluadorProduccion/producciones?docente=' . $docente->id . '&estado=pendiente');

        $ids = collect($soloPendientes->json('data'))->pluck('id_produccion_academica');

        $this->assertTrue($ids->contains($pendiente->id_produccion_academica));
        $this->assertFalse($ids->contains($avalada->id_produccion_academica));
    }

    /**
     * El filtro «sin enlace de consulta» mira `doi` y `url_publicacion`, no el ISSN: el ISSN
     * identifica la revista, no el trabajo, así que con él solo no se llega a la publicación.
     */
    public function test_el_filtro_sin_enlace_deja_fuera_las_que_tienen_doi(): void
    {
        $evaluador = $this->crearEvaluador();
        $docente   = $this->crearDocente();

        $sinEnlace = $this->crearProduccion($docente);
        $conDoi    = $this->crearProduccion($docente, 1, ['doi' => '10.1234/prueba' . uniqid()]);

        $respuesta = $this->actingAs($evaluador, 'api')
            ->getJson('/api/evaluadorProduccion/producciones?docente=' . $docente->id . '&sin_enlace=1');

        $ids = collect($respuesta->json('data'))->pluck('id_produccion_academica');

        $this->assertTrue($ids->contains($sinEnlace->id_produccion_academica));
        $this->assertFalse($ids->contains($conDoi->id_produccion_academica));
    }

    // ---------------------------------------------------------------
    // Decisión: sobre la producción completa y firmada
    // ---------------------------------------------------------------

    /**
     * Antes la decisión era por documento y una producción podía quedar con un archivo aprobado
     * y otro rechazado: un estado que `tieneProduccionAprobada()` resuelve a favor del aprobado
     * mientras el expediente muestra lo contrario.
     */
    public function test_avalar_aprueba_todos_los_documentos_de_la_produccion(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $produccion = $this->crearProduccion($docente, documentos: 2);

        $respuesta = $this->actingAs($evaluador, 'api')
            ->putJson("/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}/avalar");

        $respuesta->assertStatus(200)->assertJsonPath('documentos_afectados', 2);

        foreach ($produccion->fresh('documentosProduccionAcademica')->documentosProduccionAcademica as $documento) {
            $this->assertSame('aprobado', $documento->estado);
            $this->assertSame($evaluador->id, $documento->revisado_por);
            $this->assertNotNull($documento->revisado_en);
        }
    }

    public function test_rechazar_sin_motivo_devuelve_422(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $produccion = $this->crearProduccion($docente);

        $this->actingAs($evaluador, 'api')
            ->putJson("/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}/rechazar", [])
            ->assertStatus(422);

        $this->assertSame(
            'pendiente',
            $produccion->fresh('documentosProduccionAcademica')->documentosProduccionAcademica->first()->estado
        );
    }

    public function test_rechazar_guarda_el_motivo_en_todos_los_documentos(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $produccion = $this->crearProduccion($docente, documentos: 2);

        $motivo = 'El PDF es la constancia de envio, no el articulo publicado.';

        $this->actingAs($evaluador, 'api')
            ->putJson("/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}/rechazar", [
                'motivo' => $motivo,
            ])
            ->assertStatus(200)
            ->assertJsonPath('era_avalada', false);

        foreach ($produccion->fresh('documentosProduccionAcademica')->documentosProduccionAcademica as $documento) {
            $this->assertSame('rechazado', $documento->estado);
            $this->assertSame($motivo, $documento->motivo_rechazo);
        }
    }

    /** Revertir un aval es rechazar algo que ya estaba aprobado: mismo endpoint, otro mensaje. */
    public function test_revertir_un_aval_se_reporta_como_reversion(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $produccion = $this->crearProduccion($docente);
        $ruta       = "/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}";

        $this->actingAs($evaluador, 'api')->putJson("{$ruta}/avalar")->assertStatus(200);

        $this->actingAs($evaluador, 'api')
            ->putJson("{$ruta}/rechazar", ['motivo' => 'Se avalo por error: el ambito no corresponde.'])
            ->assertStatus(200)
            ->assertJsonPath('era_avalada', true);
    }

    // ---------------------------------------------------------------
    // Escalafón: avalar otorga puntos, revertir los quita
    // ---------------------------------------------------------------

    public function test_el_puntaje_de_escalafon_sube_al_avalar_y_baja_al_revertir(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $ambito     = $this->ambitoConPuntaje();
        $produccion = $this->crearProduccion($docente);
        $ruta       = "/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}";

        $motor = app(\App\Services\MotorEscalafonDocenteService::class);

        $antes = $motor->calcularPuntaje($this->docenteConProduccion($docente->id));

        $this->actingAs($evaluador, 'api')->putJson("{$ruta}/avalar")->assertStatus(200);
        $despues = $motor->calcularPuntaje($this->docenteConProduccion($docente->id));

        $this->assertSame($antes + $ambito->puntaje, $despues);

        $this->actingAs($evaluador, 'api')
            ->putJson("{$ruta}/rechazar", ['motivo' => 'Reversion de prueba.'])
            ->assertStatus(200);

        $revertido = $motor->calcularPuntaje($this->docenteConProduccion($docente->id));

        $this->assertSame($antes, $revertido);
    }

    /** Recarga al docente con las relaciones que necesita el motor de escalafón. */
    private function docenteConProduccion(int $id): User
    {
        return User::with([
            'produccionAcademicaUsuario',
            'produccionAcademicaUsuario.documentosProduccionAcademica',
        ])->findOrFail($id);
    }

    // ---------------------------------------------------------------
    // Ficha: enlaces de consulta e impacto
    // ---------------------------------------------------------------

    public function test_la_ficha_trae_los_seis_enlaces_y_el_impacto_en_el_escalafon(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $ambito     = $this->ambitoConPuntaje();
        $produccion = $this->crearProduccion($docente, 1, [
            'doi'             => '10.21500/rces.2026.' . random_int(1000, 9999),
            'issn_isbn'       => '2145-9088',
            'url_publicacion' => 'https://revistaces.edu.co/articulo/4187',
        ]);

        $respuesta = $this->actingAs($evaluador, 'api')
            ->getJson("/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}");

        $respuesta->assertStatus(200);

        $enlaces = collect($respuesta->json('data.enlaces_consulta'));

        $this->assertCount(6, $enlaces);
        // Con los tres identificadores puestos, los seis se pueden construir.
        $this->assertTrue($enlaces->every(fn ($e) => $e['disponible'] === true));
        $this->assertSame(2, $enlaces->where('tipo', 'directo')->count());

        $impacto = $respuesta->json('data.impacto_escalafon');
        $this->assertSame($ambito->puntaje, $impacto['otorga']);
        $this->assertSame($impacto['puntaje_actual'] + $ambito->puntaje, $impacto['puntaje_si_avala']);
    }

    /**
     * Un enlace que no se puede construir viaja igual, apagado y con su motivo: el evaluador
     * necesita ver qué le falta al registro, no solo lo que tiene.
     */
    public function test_sin_identificadores_los_enlaces_por_issn_viajan_no_disponibles(): void
    {
        $evaluador  = $this->crearEvaluador();
        $docente    = $this->crearDocente();
        $produccion = $this->crearProduccion($docente);

        $respuesta = $this->actingAs($evaluador, 'api')
            ->getJson("/api/evaluadorProduccion/producciones/{$produccion->id_produccion_academica}");

        $enlaces = collect($respuesta->json('data.enlaces_consulta'));

        $this->assertCount(6, $enlaces);

        // doi.org, sitio de la publicación y Publindex dependen de datos que no están.
        $publindex = $enlaces->firstWhere('fuente', 'Publindex');
        $this->assertFalse($publindex['disponible']);
        $this->assertSame('Sin ISSN registrado', $publindex['motivo']);

        // Google Scholar solo necesita el título, que siempre está: es la red de seguridad.
        $this->assertTrue($enlaces->firstWhere('fuente', 'Google Scholar')['disponible']);
    }

    // ---------------------------------------------------------------
    // Normalización del DOI
    // ---------------------------------------------------------------

    /**
     * El docente casi siempre pega la URL completa que le da la revista. Guardarla rompería
     * Crossref, que espera el identificador desnudo.
     */
    public function test_el_doi_se_guarda_sin_el_resolvedor(): void
    {
        $docente = $this->crearDocente();
        $ambito  = $this->ambitoConPuntaje();

        $respuesta = $this->actingAs($docente, 'api')->postJson('/api/docente/crear-produccion', [
            'ambito_divulgacion_id' => $ambito->id_ambito_divulgacion,
            'titulo'                => 'Normalizacion de DOI ' . uniqid(),
            'numero_autores'        => 1,
            'medio_divulgacion'     => 'Revista de Pruebas',
            'fecha_divulgacion'     => '2025-03-01',
            'doi'                   => 'https://doi.org/10.21500/rces.2026.4187',
            'archivo'               => \Illuminate\Http\UploadedFile::fake()->create('articulo.pdf', 100, 'application/pdf'),
        ]);

        $respuesta->assertStatus(201);

        $this->assertDatabaseHas('produccion_academicas', [
            'user_id' => $docente->id,
            'doi'     => '10.21500/rces.2026.4187',
        ]);
    }
}
