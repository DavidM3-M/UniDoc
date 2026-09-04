<?php

namespace Tests\Feature;

use App\Exceptions\AscensoEscalafonException;
use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Experiencia;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\ProduccionAcademica;
use App\Constants\ConstTalentoHumano\AreasContratacion;
use App\Constants\ConstTalentoHumano\TipoContratacion;
use App\Models\Docente\EvaluacionDocente;
use App\Models\EscalonDocente;
use App\Models\HistorialEscalonDocente;
use App\Models\PeriodoAscenso;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\Usuario\User;
use App\Services\AscensoEscalafonService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas de la bandeja de escalafón y de los actos que la acompañan.
 *
 * Lo que se protege aquí no es el CRUD: es que ascender a alguien es un acto administrativo con
 * consecuencias —cambia su categoría, reinicia su antigüedad y su puntaje de producción— y por
 * tanto que solo lo pueda hacer Apoyo Profesoral, que se revalide contra la base de datos, que
 * respete el calendario de periodos y que quede firmado y reversible.
 */
class EscalafonAscensoApiTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIJO = '/api/apoyoProfesoral/escalafon';

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function crearUsuario(string $rol, string $prefijo): User
    {
        $uid = uniqid();

        $user = User::create([
            'municipio_id'          => 703,
            'tipo_identificacion'   => 'Cédula de ciudadanía',
            'numero_identificacion' => '78' . substr($uid, -8),
            'primer_nombre'         => 'Test' . $prefijo,
            'primer_apellido'       => 'Escalafon',
            'fecha_nacimiento'      => '1980-04-12',
            'email'                 => $prefijo . $uid . '@test.com',
            'password'              => Hash::make('Password1'),
        ]);

        $user->assignRole($rol);

        return $user;
    }

    private function crearApoyo(): User
    {
        return $this->crearUsuario('Apoyo Profesoral', 'apoyoesc');
    }

    private function crearDocente(): User
    {
        return $this->crearUsuario('Docente', 'docesc');
    }

    /**
     * Contratación del docente. Por defecto, planta y vigente: guardarla ya lo mete al escalafón,
     * porque `ContratacionObserver` escucha esta escritura. Los parámetros permiten construir los
     * casos que no deben meter a nadie (cátedra, vencida, todavía no iniciada).
     */
    private function contratar(
        User $docente,
        string $tipo = TipoContratacion::PLANTA,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): Contratacion {
        return Contratacion::create([
            'user_id' => $docente->id,
            'tipo_contrato' => $tipo,
            'area' => AreasContratacion::FACULTAD_DE_INGENIERIA,
            'fecha_inicio' => $fechaInicio ?? now()->subYears(5)->toDateString(),
            'fecha_fin' => $fechaFin ?? now()->addYear()->toDateString(),
            'valor_contrato' => 5000000,
        ]);
    }

    /** Periodo ya cerrado, que es contra el que se pueden ejecutar ascensos. */
    private function periodoCerrado(string $fechaCierre = '2026-06-30'): PeriodoAscenso
    {
        return PeriodoAscenso::create([
            'nombre' => 'Periodo cerrado ' . uniqid(),
            'fecha_cierre' => $fechaCierre,
            'cerrado_en' => now(),
        ]);
    }

    private function escalon(string $nombre): EscalonDocente
    {
        return EscalonDocente::where('nombre', $nombre)->firstOrFail();
    }

    /**
     * Documento del expediente.
     *
     * `$subidoEn` importa: el motor compara `created_at` contra la fecha de cierre del periodo, y
     * `Documento::create()` lo pone en "ahora", que en estas pruebas es posterior a los cierres que
     * se usan. Sin retrasarlo, el documento queda legítimamente fuera de plazo.
     */
    private function documento(
        string $documentableType,
        int $documentableId,
        string $estado = 'aprobado',
        string $subidoEn = '2020-01-01'
    ): Documento {
        $documento = Documento::create([
            'archivo'           => 'documentos/esc-' . uniqid() . '.pdf',
            'estado'            => $estado,
            'documentable_id'   => $documentableId,
            'documentable_type' => $documentableType,
        ]);

        $documento->forceFill(['created_at' => Carbon::parse($subidoEn)])->save();

        return $documento;
    }

    /**
     * Docente Auxiliar desde `$desde`, con toda la experiencia UniAutónoma aprobada.
     *
     * Escribe el tramo directamente en vez de pasar por una contratación para poder fijar el
     * `desde` sin depender de las fechas del contrato: lo que prueban los tests de ascenso es el
     * ascenso, no cómo se entró. `otorgado_por` va en null porque es lo que deja el ingreso
     * automático, que es la única forma en que hoy nace un tramo de esta vía.
     */
    private function auxiliarDesde(User $docente, string $desde): HistorialEscalonDocente
    {
        $experiencia = Experiencia::create([
            'user_id'                => $docente->id,
            'tipo_experiencia'       => 'Docencia universitaria',
            'institucion_experiencia' => 'Universidad Autónoma',
            'es_uniautonoma'         => true,
            'cargo'                  => 'Docente',
            'trabajo_actual'         => 'Si',
            'fecha_inicio'           => $desde,
            'fecha_finalizacion'     => null,
        ]);
        $this->documento(Experiencia::class, $experiencia->id_experiencia);

        return HistorialEscalonDocente::create([
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'desde' => $desde,
            'via' => HistorialEscalonDocente::VIA_INGRESO,
            'otorgado_por' => null,
        ]);
    }

    /** Le completa al docente todo lo que exige Asistente además de la antigüedad. */
    private function completarRequisitosDeAsistente(User $docente, string $fechaDivulgacion = '2025-05-10'): void
    {
        $estudio = Estudio::create([
            'user_id'            => $docente->id,
            'tipo_estudio'       => 'Maestría',
            'graduado'           => 'Si',
            'institucion'        => 'Universidad de Pruebas',
            'titulo_convalidado' => 'No',
            'titulo_estudio'     => 'Maestría de Pruebas',
            'fecha_inicio'       => '2016-01-15',
            'fecha_graduacion'   => '2018-06-30',
        ]);
        $this->documento(Estudio::class, $estudio->id_estudio);

        $idioma = Idioma::create([
            'user_id'    => $docente->id,
            'idioma'     => 'Inglés',
            'institucion_idioma' => 'Centro de Idiomas',
            'nivel'      => 'B1',
            'fecha_certificado' => '2022-03-01',
        ]);
        $this->documento(Idioma::class, $idioma->id_idioma);

        $ambito = AmbitoDivulgacion::where('puntaje', '>', 0)->orderByDesc('puntaje')->first();

        if (!$ambito) {
            $this->markTestSkipped('El catálogo de ámbitos de divulgación no tiene ninguno con puntaje.');
        }

        // Asistente exige 20 puntos y ningún ámbito del catálogo llega solo, así que se cargan
        // tantas producciones como haga falta.
        $cuantas = (int) ceil(20 / $ambito->puntaje);

        for ($i = 0; $i < $cuantas; $i++) {
            $produccion = ProduccionAcademica::create([
                'user_id'               => $docente->id,
                'ambito_divulgacion_id' => $ambito->id_ambito_divulgacion,
                'titulo'                => 'Produccion de escalafon ' . uniqid(),
                'numero_autores'        => 1,
                'medio_divulgacion'     => 'Revista de Pruebas',
                'fecha_divulgacion'     => $fechaDivulgacion,
            ]);
            // La producción se sube el día de su divulgación: ambas fechas dentro de la ventana.
            $this->documento(
                ProduccionAcademica::class,
                $produccion->id_produccion_academica,
                'aprobado',
                $fechaDivulgacion
            );
        }

        EvaluacionDocente::create([
            'user_id' => $docente->id,
            'promedio_evaluacion_docente' => 4.3,
        ]);
    }

    // ---------------------------------------------------------------
    // Autorización
    // ---------------------------------------------------------------

    public function test_un_docente_no_puede_entrar_a_la_bandeja_de_escalafon(): void
    {
        $this->actingAs($this->crearDocente(), 'api')
            ->getJson(self::PREFIJO . '/docentes')
            ->assertStatus(403);
    }

    public function test_un_docente_no_puede_ascenderse_a_si_mismo(): void
    {
        $docente = $this->crearDocente();

        $this->actingAs($docente, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $this->periodoCerrado()->id_periodo_ascenso,
            ])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Periodos de ascenso
    // ---------------------------------------------------------------

    public function test_no_se_puede_crear_un_periodo_con_cierre_anterior_al_ultimo(): void
    {
        $apoyo = $this->crearApoyo();
        $ultimo = PeriodoAscenso::create([
            'nombre' => 'Vigente ' . uniqid(),
            'fecha_cierre' => now()->addYears(2)->toDateString(),
        ]);

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . '/periodos', [
                'nombre' => 'Intercalado',
                'fecha_cierre' => $ultimo->fecha_cierre->copy()->subMonth()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_cierre');
    }

    public function test_no_se_puede_crear_un_periodo_con_cierre_pasado(): void
    {
        $this->actingAs($this->crearApoyo(), 'api')
            ->postJson(self::PREFIJO . '/periodos', [
                'nombre' => 'Retroactivo',
                'fecha_cierre' => now()->subMonth()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_cierre');
    }

    public function test_un_periodo_ya_cerrado_no_se_puede_modificar(): void
    {
        $periodo = $this->periodoCerrado();

        // La fecha se calcula a partir del último periodo que exista, no con un `addYear()` fijo:
        // estas pruebas corren contra la base de desarrollo, y un periodo con cierre más lejano
        // —sembrado, o creado por alguien probando la pantalla— hacía que la petición muriera antes
        // en el 422 de validación y nunca llegara al 409 que se quiere comprobar.
        $ultimo = PeriodoAscenso::where('id_periodo_ascenso', '!=', $periodo->id_periodo_ascenso)
            ->max('fecha_cierre');

        $cierre = $ultimo
            ? Carbon::parse($ultimo)->addYear()->toDateString()
            : now()->addYear()->toDateString();

        $this->actingAs($this->crearApoyo(), 'api')
            ->putJson(self::PREFIJO . "/periodos/{$periodo->id_periodo_ascenso}", [
                'fecha_cierre' => $cierre,
            ])
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------
    // Ingreso automático al escalafón
    //
    // Entrar al escalafón dejó de ser un acto de nadie: lo dispara la contratación de planta a
    // través de `ContratacionObserver`. Lo que se protege aquí es que la regla valga siempre y en
    // un solo sentido —mete, nunca saca— y que no dependa de que alguien se acuerde de registrarlo.
    // ---------------------------------------------------------------

    public function test_la_contratacion_de_planta_mete_al_docente_al_primer_escalon(): void
    {
        $docente = $this->crearDocente();

        $this->contratar($docente, TipoContratacion::PLANTA, '2019-02-01', now()->addYear()->toDateString());

        // Nace ya en el primer escalón, con la fecha del contrato y sin firma de nadie.
        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'desde' => '2019-02-01',
            'hasta' => null,
            'via' => HistorialEscalonDocente::VIA_INGRESO,
            'otorgado_por' => null,
        ]);

        // La caché denormalizada queda al día para los listados.
        $this->assertDatabaseHas('puntajes', [
            'user_id' => $docente->id,
            'categoria_lograda' => 'Auxiliar',
        ]);
    }

    /** El formulario de ingreso ya no existe: nadie puede meter ni adelantar a un docente a mano. */
    public function test_no_queda_ninguna_ruta_para_ingresar_a_mano(): void
    {
        $docente = $this->crearDocente();

        $this->actingAs($this->crearApoyo(), 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ingreso", ['desde' => '2019-02-01'])
            ->assertStatus(404);
    }

    /**
     * Corregir un contrato mal cargado tiene que surtir el mismo efecto que registrarlo bien: por
     * eso el observer escucha también `updated` y no solo el alta.
     */
    public function test_corregir_el_contrato_a_planta_mete_al_docente_al_escalafon(): void
    {
        $docente = $this->crearDocente();
        $contrato = $this->contratar($docente, TipoContratacion::CATEDRA, '2019-02-01');

        $this->assertDatabaseMissing('historial_escalon_docente', ['user_id' => $docente->id]);

        $contrato->update(['tipo_contrato' => TipoContratacion::PLANTA]);

        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'desde' => '2019-02-01',
        ]);
    }

    /**
     * Una renovación vuelve a disparar el observer. No puede abrir un segundo tramo ni mover el
     * `desde` del que ya existe: eso le reiniciaría la antigüedad al docente cada vez que Talento
     * Humano le renueva el contrato.
     */
    public function test_renovar_el_contrato_no_abre_un_segundo_tramo_ni_reinicia_la_antiguedad(): void
    {
        $docente = $this->crearDocente();
        $this->contratar($docente, TipoContratacion::PLANTA, '2019-02-01', now()->addYear()->toDateString());

        $this->contratar($docente, TipoContratacion::PLANTA, now()->toDateString(), now()->addYears(3)->toDateString());

        $this->assertSame(1, HistorialEscalonDocente::where('user_id', $docente->id)->count());
        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'desde' => '2019-02-01',
        ]);
    }

    /**
     * Una vinculación larga se registra como una cadena de contratos renovados, así que la
     * antigüedad cuelga del primero de planta y no del que esté vigente hoy. Quedarse con el último
     * le borraría al docente los años que lleva.
     */
    public function test_la_antiguedad_cuelga_del_contrato_de_planta_mas_antiguo(): void
    {
        $docente = $this->crearDocente();

        // El primero ya venció, así que por sí solo no mete a nadie: no acredita ser planta hoy.
        $this->contratar($docente, TipoContratacion::PLANTA, '2015-03-01', '2020-01-14');
        $this->assertDatabaseMissing('historial_escalon_docente', ['user_id' => $docente->id]);

        // Al registrarse la renovación entra, pero contando desde que empezó, no desde la renovación.
        $this->contratar($docente, TipoContratacion::PLANTA, '2020-01-15', now()->addYear()->toDateString());

        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'desde' => '2015-03-01',
        ]);
    }

    // ---------------------------------------------------------------
    // El escalafón es solo para docentes de planta
    // ---------------------------------------------------------------

    public function test_un_docente_de_catedra_no_entra_al_escalafon(): void
    {
        $docente = $this->crearDocente();

        $this->contratar($docente, TipoContratacion::CATEDRA);

        $this->assertDatabaseMissing('historial_escalon_docente', ['user_id' => $docente->id]);
    }

    /**
     * `contratacions.fecha_fin` es NOT NULL, así que no existe el contrato indefinido: un docente de
     * planta cuya renovación nadie registró no entra hasta que Talento Humano lo actualice. Es el
     * costo asumido al escribir la regla, y se prueba para que quede explícito.
     */
    public function test_un_contrato_de_planta_vencido_no_mete_a_nadie_al_escalafon(): void
    {
        $docente = $this->crearDocente();

        $this->contratar(
            $docente,
            TipoContratacion::PLANTA,
            now()->subYears(5)->toDateString(),
            now()->subMonth()->toDateString()
        );

        $this->assertDatabaseMissing('historial_escalon_docente', ['user_id' => $docente->id]);
    }

    /** Un contrato que todavía no arranca tampoco acredita ser docente de planta hoy. */
    public function test_un_contrato_de_planta_que_aun_no_inicia_no_mete_a_nadie_al_escalafon(): void
    {
        $docente = $this->crearDocente();

        $this->contratar(
            $docente,
            TipoContratacion::PLANTA,
            now()->addMonth()->toDateString(),
            now()->addYears(2)->toDateString()
        );

        $this->assertDatabaseMissing('historial_escalon_docente', ['user_id' => $docente->id]);
    }

    /**
     * Con varias contrataciones —lo normal tras una convocatoria de ascenso o un cambio de cargo—
     * basta con que una sea de planta y esté vigente. La relación `contratacionUsuario` es un
     * `hasOne` y devolvería una cualquiera, por eso el servicio consulta la tabla directamente.
     */
    public function test_entra_si_alguna_de_sus_contrataciones_es_de_planta_vigente(): void
    {
        $docente = $this->crearDocente();

        $this->contratar(
            $docente,
            TipoContratacion::CATEDRA,
            now()->subYears(8)->toDateString(),
            now()->subYears(5)->toDateString()
        );
        $this->contratar($docente, TipoContratacion::PLANTA, '2021-06-01', now()->addYear()->toDateString());

        // La cátedra, aunque sea más antigua, no cuenta para la fecha: solo cuelga de la planta.
        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'desde' => '2021-06-01',
        ]);
    }

    /**
     * Que el contrato venza no saca a nadie del escalafón. Para eso está la reversión, que es un
     * acto firmado y con motivo; un tramo que se borrara solo se llevaría por delante la antigüedad
     * acumulada sin dejar rastro.
     */
    public function test_dejar_de_ser_planta_no_saca_al_docente_del_escalafon(): void
    {
        $docente = $this->crearDocente();
        $contrato = $this->contratar($docente, TipoContratacion::PLANTA, '2019-02-01', now()->addYear()->toDateString());

        $contrato->update(['tipo_contrato' => TipoContratacion::CATEDRA]);

        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'hasta' => null,
        ]);
    }

    // ---------------------------------------------------------------
    // Ascenso
    // ---------------------------------------------------------------

    public function test_ascenso_de_un_docente_que_cumple_todo(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $periodo = $this->periodoCerrado('2026-06-30');

        $this->auxiliarDesde($docente, '2019-02-01');
        $this->completarRequisitosDeAsistente($docente);

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
                'motivo' => 'Resolución de prueba',
            ])
            ->assertStatus(201);

        // El tramo de Auxiliar cierra en la fecha de cierre, no en la de la firma, para que la
        // ventana de producción del escalón nuevo empalme sin huecos.
        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'hasta' => '2026-06-30',
        ]);

        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Asistente')->id_escalon,
            'desde' => '2026-06-30',
            'hasta' => null,
            'via' => HistorialEscalonDocente::VIA_REQUISITOS,
            'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            'otorgado_por' => $apoyo->id,
        ]);
    }

    public function test_no_asciende_quien_no_tiene_la_antiguedad(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $periodo = $this->periodoCerrado('2026-06-30');

        // Solo 12 meses como Auxiliar, contra los 48 que exige Asistente.
        $this->auxiliarDesde($docente, '2025-06-30');
        $this->completarRequisitosDeAsistente($docente);

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            ])
            ->assertStatus(409);

        $this->assertDatabaseMissing('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Asistente')->id_escalon,
        ]);
    }

    /**
     * El 409 tiene que decirle a quien lo lee qué pasó. Antes traía las claves internas de los
     * criterios ("formacion, idioma, puntaje") y ninguna referencia a la fecha contra la que se
     * midió, que es lo que explica que un docente con todo aprobado aparezca sin nada.
     */
    public function test_el_rechazo_por_requisitos_explica_que_falta_y_contra_que_fecha(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $periodo = $this->periodoCerrado('2026-06-30');

        // Antigüedad de sobra, pero sin ningún otro requisito cargado.
        $this->auxiliarDesde($docente, '2019-02-01');

        $cuerpo = $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            ])
            ->assertStatus(409)
            ->json();

        // Nombres legibles, no las claves internas.
        $this->assertStringContainsString('formación académica', $cuerpo['message']);
        $this->assertStringNotContainsString('produccion_academica', $cuerpo['message']);

        // Y la fecha de corte, que es lo que faltaba para poder interpretarlo.
        $this->assertStringContainsString('30/06/2026', $cuerpo['message']);
        $this->assertSame('2026-06-30', $cuerpo['fecha_corte']);

        // El desglose por criterio viaja aparte, con lo requerido y lo que tiene.
        $this->assertNotEmpty($cuerpo['faltantes']);
        $this->assertSame('Asistente', $cuerpo['escalon_objetivo']);

        $campos = array_column($cuerpo['faltantes'], 'campo');
        $this->assertContains('formacion', $campos);
        $this->assertArrayHasKey('requerido', $cuerpo['faltantes'][0]);
        $this->assertArrayHasKey('actual', $cuerpo['faltantes'][0]);
    }

    /**
     * "Todavía no cumplía" no es lo mismo que "no cumple", y el mensaje tiene que separarlos.
     *
     * Un docente que hoy es elegible pero cuyo expediente se completó después del cierre del periodo
     * elegido no tiene nada que corregir: hay que ascenderlo en otro periodo. Antes ese caso salía
     * como una lista de requisitos incumplidos, indistinguible de quien de verdad no tiene nada.
     */
    public function test_si_el_docente_es_elegible_hoy_el_mensaje_senala_el_periodo_y_no_el_expediente(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $periodo = $this->periodoCerrado('2026-06-30');

        $this->auxiliarDesde($docente, '2019-02-01');
        // La producción se divulga y se sube después del cierre: no cuenta a esa fecha, sí a hoy.
        $this->completarRequisitosDeAsistente($docente, '2026-07-15');

        $mensaje = $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            ])
            ->assertStatus(409)
            ->json('message');

        $this->assertStringContainsString('todavía no cumplía', $mensaje);
        $this->assertStringContainsString('Hoy sí sería elegible', $mensaje);
        $this->assertStringContainsString('periodo que cierre más tarde', $mensaje);

        // Y no lo culpa de un expediente incompleto, que es lo que confundía.
        $this->assertStringNotContainsString('Le falta', $mensaje);
    }

    /**
     * Un periodo abierto sirve para proyectar en la bandeja, no para ejecutar ascensos: sus
     * requisitos se congelan en una fecha que todavía no ha llegado.
     */
    public function test_no_se_puede_ascender_contra_un_periodo_que_sigue_abierto(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();

        $abierto = PeriodoAscenso::create([
            'nombre' => 'Abierto ' . uniqid(),
            'fecha_cierre' => now()->addYear()->toDateString(),
        ]);

        $this->auxiliarDesde($docente, '2019-02-01');
        $this->completarRequisitosDeAsistente($docente);

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $abierto->id_periodo_ascenso,
            ])
            ->assertStatus(409);
    }

    public function test_un_docente_sin_ingreso_al_escalafon_no_asciende(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $periodo = $this->periodoCerrado();

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            ])
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------
    // Reversión
    // ---------------------------------------------------------------

    public function test_revertir_devuelve_al_escalon_anterior_sin_borrar_el_tramo(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $periodo = $this->periodoCerrado('2026-06-30');

        $this->auxiliarDesde($docente, '2019-02-01');
        $this->completarRequisitosDeAsistente($docente);

        $ascenso = $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/docentes/{$docente->id}/ascender", [
                'periodo_ascenso_id' => $periodo->id_periodo_ascenso,
            ])
            ->json('data.id_historial_escalon');

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/historial/{$ascenso}/revertir", [
                'motivo' => 'Certificado de inglés rechazado tras verificación',
            ])
            ->assertStatus(200);

        // El tramo revertido sigue ahí, marcado y firmado.
        $revertido = HistorialEscalonDocente::find($ascenso);
        $this->assertNotNull($revertido);
        $this->assertNotNull($revertido->revertido_en);
        $this->assertSame($apoyo->id, $revertido->revertido_por);

        // Y el de Auxiliar vuelve a estar abierto.
        $this->assertDatabaseHas('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Auxiliar')->id_escalon,
            'hasta' => null,
        ]);

        $this->assertDatabaseHas('puntajes', [
            'user_id' => $docente->id,
            'categoria_lograda' => 'Auxiliar',
        ]);
    }

    public function test_no_se_puede_revertir_dos_veces(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $tramo = $this->auxiliarDesde($docente, '2019-02-01');

        $ruta = self::PREFIJO . "/historial/{$tramo->id_historial_escalon}/revertir";

        $this->actingAs($apoyo, 'api')->postJson($ruta, ['motivo' => 'Error de digitación'])->assertStatus(200);
        $this->actingAs($apoyo, 'api')->postJson($ruta, ['motivo' => 'Otra vez'])->assertStatus(409);
    }

    public function test_el_motivo_es_obligatorio_al_revertir(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $tramo = $this->auxiliarDesde($docente, '2019-02-01');

        $this->actingAs($apoyo, 'api')
            ->postJson(self::PREFIJO . "/historial/{$tramo->id_historial_escalon}/revertir", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    // ---------------------------------------------------------------
    // Bandeja
    // ---------------------------------------------------------------

    public function test_la_bandeja_filtra_por_el_semaforo_de_antiguedad(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $this->periodoCerrado('2026-06-30');

        $this->auxiliarDesde($docente, '2019-02-01');
        $this->completarRequisitosDeAsistente($docente);

        $elegibles = $this->actingAs($apoyo, 'api')
            ->getJson(self::PREFIJO . '/docentes?estado_antiguedad=elegible')
            ->assertStatus(200)
            ->json('data');

        $this->assertContains($docente->id, array_column($elegibles, 'id'));

        $sinExperiencia = $this->actingAs($apoyo, 'api')
            ->getJson(self::PREFIJO . '/docentes?estado_antiguedad=sin_experiencia_suficiente')
            ->assertStatus(200)
            ->json('data');

        $this->assertNotContains($docente->id, array_column($sinExperiencia, 'id'));
    }

    /**
     * La bandeja es de ascensos, así que solo lista a quien está en el escalafón: los de planta.
     * Antes traía a todos los docentes, y los de cátedra la llenaban de filas sin categoría y sin
     * ascenso posible.
     */
    public function test_la_bandeja_solo_lista_a_los_docentes_del_escalafon(): void
    {
        $apoyo = $this->crearApoyo();

        $planta = $this->crearDocente();
        $this->contratar($planta, TipoContratacion::PLANTA, '2019-02-01', now()->addYear()->toDateString());

        $catedra = $this->crearDocente();
        $this->contratar($catedra, TipoContratacion::CATEDRA);

        // Docente sin contrato alguno: tampoco tiene por qué aparecer.
        $sinContrato = $this->crearDocente();

        $ids = array_column(
            $this->actingAs($apoyo, 'api')
                ->getJson(self::PREFIJO . '/docentes')
                ->assertStatus(200)
                ->json('data'),
            'id'
        );

        $this->assertContains($planta->id, $ids);
        $this->assertNotContains($catedra->id, $ids);
        $this->assertNotContains($sinContrato->id, $ids);
    }

    /**
     * Que el contrato deje de ser de planta no lo borra de la bandeja: sigue en el escalafón hasta
     * que alguien firme la reversión, y es precisamente ahí donde hay que poder verlo para firmarla.
     */
    public function test_la_bandeja_sigue_listando_a_quien_dejo_de_ser_planta_sin_revertir(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $contrato = $this->contratar($docente, TipoContratacion::PLANTA, '2019-02-01', now()->addYear()->toDateString());

        $contrato->update(['tipo_contrato' => TipoContratacion::CATEDRA]);

        $ids = array_column(
            $this->actingAs($apoyo, 'api')
                ->getJson(self::PREFIJO . '/docentes')
                ->assertStatus(200)
                ->json('data'),
            'id'
        );

        $this->assertContains($docente->id, $ids);
    }

    /** El listado general de puntajes sigue la misma regla que la bandeja. */
    public function test_el_listado_de_puntajes_solo_trae_a_los_docentes_del_escalafon(): void
    {
        $apoyo = $this->crearApoyo();

        $planta = $this->crearDocente();
        $this->contratar($planta, TipoContratacion::PLANTA, '2019-02-01', now()->addYear()->toDateString());

        $catedra = $this->crearDocente();
        $this->contratar($catedra, TipoContratacion::CATEDRA);

        $ids = array_column(
            $this->actingAs($apoyo, 'api')
                ->getJson('/api/apoyoProfesoral/listar-docentes-puntaje')
                ->assertStatus(200)
                ->json('data'),
            'id'
        );

        $this->assertContains($planta->id, $ids);
        $this->assertNotContains($catedra->id, $ids);
    }

    public function test_el_detalle_muestra_el_historial_completo(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $this->auxiliarDesde($docente, '2019-02-01');

        $respuesta = $this->actingAs($apoyo, 'api')
            ->getJson(self::PREFIJO . "/docentes/{$docente->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame('Auxiliar', $respuesta['evaluacion']['escalon_vigente']);
        $this->assertCount(1, $respuesta['historial']);
        $this->assertSame('Auxiliar', $respuesta['historial'][0]['escalon']);
    }

    // ---------------------------------------------------------------
    // Consulta del docente
    // ---------------------------------------------------------------

    /**
     * La consulta del docente dejó de escribir. Antes este mismo endpoint le otorgaba la categoría.
     */
    public function test_la_consulta_del_docente_no_otorga_ni_persiste_categoria(): void
    {
        $docente = $this->crearDocente();
        $this->periodoCerrado('2026-06-30');

        $this->auxiliarDesde($docente, '2019-02-01');
        $this->completarRequisitosDeAsistente($docente);

        $resultado = $this->actingAs($docente, 'api')
            ->getJson('/api/docente/evaluar-puntaje')
            ->assertStatus(200)
            ->json('resultado');

        $this->assertTrue($resultado['elegible']);
        $this->assertSame('Asistente', $resultado['escalon_objetivo']);

        // Sigue siendo Auxiliar: es elegible, no ascendido.
        $this->assertSame('Auxiliar', $resultado['escalon_vigente']);
        $this->assertDatabaseMissing('historial_escalon_docente', [
            'user_id' => $docente->id,
            'escalon_id' => $this->escalon('Asistente')->id_escalon,
        ]);
    }

    // ---------------------------------------------------------------
    // Identificadores que no corresponden a ninguna fila
    //
    // Todos comparten la misma causa: un ID de la ruta que llega a la consulta sin filtrar. La
    // columna no puede almacenarlo, así que PostgreSQL aborta en vez de no encontrar nada, y la
    // excepción salía por el `catch` genérico como un 500 donde correspondía un 404.
    // ---------------------------------------------------------------

    public function test_el_detalle_de_un_docente_con_id_no_numerico_retorna_404(): void
    {
        $this->actingAs($this->crearApoyo(), 'api')
            ->getJson(self::PREFIJO . '/docentes/abc')
            ->assertStatus(404);
    }

    public function test_ascender_a_un_docente_con_id_no_numerico_retorna_404(): void
    {
        $this->actingAs($this->crearApoyo(), 'api')
            ->postJson(self::PREFIJO . '/docentes/abc/ascender', [
                'periodo_ascenso_id' => $this->periodoCerrado()->id_periodo_ascenso,
            ])
            ->assertStatus(404);
    }

    /**
     * La regla que compara la fecha con la del periodo anterior excluye el que se está editando, y
     * ese `!=` es el que llevaba el ID de la ruta hasta una columna `smallint`. Se evalúa antes que
     * el 404 del controlador, así que el ID fuera de rango tiene que descartarse en la propia regla.
     */
    public function test_actualizar_un_periodo_con_id_fuera_del_rango_de_la_clave_retorna_404(): void
    {
        // El cuerpo tiene que pasar la validación entera: las reglas del atributo no llevan `bail`,
        // así que un 422 por la fecha no impediría que la regla del periodo anterior se ejecutara,
        // pero sí escondería el 404 que se quiere comprobar. La fecha se calcula contra lo que haya
        // en la base —estas pruebas corren contra la de desarrollo— y se fuerza a futuro.
        $ultimo = PeriodoAscenso::max('fecha_cierre');
        $cierre = Carbon::parse($ultimo ?? now())->addYear();

        if (!$cierre->isFuture()) {
            $cierre = now()->addYear();
        }

        $this->actingAs($this->crearApoyo(), 'api')
            ->putJson(self::PREFIJO . '/periodos/999999', ['fecha_cierre' => $cierre->toDateString()])
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Aislamiento del detalle
    // ---------------------------------------------------------------

    /**
     * El escalafón es de los docentes. Sin comprobar el rol, esta ruta contestaba 200 con el nombre
     * completo de cualquier usuario del sistema y una evaluación fabricada sobre una ficha vacía.
     * La escritura ya lo comprobaba (`ingresarManual()` rechaza a quien no es Docente); la lectura
     * no.
     */
    public function test_el_detalle_no_expone_a_un_usuario_que_no_es_docente(): void
    {
        $apoyo = $this->crearApoyo();
        $otroFuncionario = $this->crearApoyo();

        $this->actingAs($apoyo, 'api')
            ->getJson(self::PREFIJO . "/docentes/{$otroFuncionario->id}")
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Concurrencia en la reversión
    // ---------------------------------------------------------------

    /**
     * La guarda de «ya estaba revertido» tiene que leer la base de datos, no la instancia recibida.
     *
     * El controlador carga el tramo antes de abrir la transacción, así que dos peticiones
     * simultáneas entran al servicio con sendas copias sin revertir. Aquí se reproduce con dos
     * instancias del mismo tramo: la segunda reversión tiene que rebotar aunque su copia en memoria
     * siga diciendo que el tramo está vigente. Sin releerlo con `lockForUpdate()` pasaba la guarda y
     * sobrescribía la firma y el motivo de quien lo revirtió de verdad.
     */
    public function test_revertir_con_una_copia_desactualizada_del_tramo_rebota(): void
    {
        $apoyo = $this->crearApoyo();
        $docente = $this->crearDocente();
        $tramo = $this->auxiliarDesde($docente, '2019-02-01');

        $servicio = app(AscensoEscalafonService::class);

        // La copia que tendría en la mano la segunda petición: cargada antes de que nadie revirtiera.
        $copiaDesactualizada = HistorialEscalonDocente::findOrFail($tramo->id_historial_escalon);

        $servicio->revertir(
            HistorialEscalonDocente::findOrFail($tramo->id_historial_escalon),
            $apoyo,
            'Reversión legítima'
        );

        $this->assertFalse(
            $copiaDesactualizada->estaRevertido(),
            'La copia en memoria tiene que seguir creyendo que el tramo está vigente; si no, el test no prueba nada.'
        );

        try {
            $servicio->revertir($copiaDesactualizada, $apoyo, 'Reversión duplicada');
            $this->fail('La segunda reversión debía rebotar con AscensoEscalafonException.');
        } catch (AscensoEscalafonException $e) {
            // Es lo que el controlador traduce a 409.
        }

        // La auditoría conserva el motivo del único acto que ocurrió.
        $this->assertDatabaseHas('historial_escalon_docente', [
            'id_historial_escalon' => $tramo->id_historial_escalon,
            'motivo_reversion' => 'Reversión legítima',
            'revertido_por' => $apoyo->id,
        ]);
    }
}
