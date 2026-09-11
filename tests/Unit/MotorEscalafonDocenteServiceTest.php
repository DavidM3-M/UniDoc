<?php

namespace Tests\Unit;

use App\Constants\ConstAgregarExperiencia\TrabajoActual;
use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Experiencia;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Docente\EvaluacionDocente;
use App\Models\EscalonDocente;
use App\Models\HistorialEscalonDocente;
use App\Models\PeriodoAscenso;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\TiposProductoAcademico\ProductoAcademico;
use App\Models\Usuario\User;
use App\Services\MotorEscalafonDocenteService;
use Carbon\Carbon;
use Database\Seeders\EscalonDocenteSeeder;
use Database\Seeders\ReglaExcepcionEscalonSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pruebas del motor del escalafón docente.
 *
 * El motor ya no otorga categorías: responde "¿es elegible para ascender?" contra la fecha de
 * cierre de un periodo de ascenso. Lo que se prueba aquí son las tres reglas del nuevo reglamento
 * que cambian el resultado —antigüedad en el escalón anterior, producción no acumulable, y todo
 * congelado al cierre— más las que ya regían y no debían romperse.
 *
 * Las reglas siguen viniendo de base de datos, así que se siembran `EscalonDocenteSeeder` y
 * `ReglaExcepcionEscalonSeeder`, y se usa `DatabaseTransactions` para no dejar rastro.
 *
 * Los escalones sembrados: Auxiliar (base), Asistente (48 meses como Auxiliar, Maestría, inglés B1,
 * 20 puntos, evaluación 4.0), Asociado (120 como Asistente, Doctorado, B2, 30 puntos) y Titular
 * (216 como Asociado, Doctorado, B2, 60 puntos).
 */
class MotorEscalafonDocenteServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MotorEscalafonDocenteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EscalonDocenteSeeder::class);
        $this->seed(ReglaExcepcionEscalonSeeder::class);

        $this->servicio = new MotorEscalafonDocenteService();
    }

    // ---------------------------------------------------------------
    // Helpers de construcción
    // ---------------------------------------------------------------

    private function documento(string $estado = 'aprobado', ?string $subidoEn = null): Documento
    {
        return (new Documento())->forceFill([
            'estado' => $estado,
            // `created_at` es lo que compara el motor contra la fecha de cierre: lo que importa es
            // cuándo lo subió el docente, no cuándo se lo avalaron.
            'created_at' => $subidoEn ? Carbon::parse($subidoEn) : now()->subYears(20),
        ]);
    }

    private function estudio(string $tipo, string $estadoDoc = 'aprobado', ?string $subidoEn = null): Estudio
    {
        $estudio = (new Estudio())->forceFill(['tipo_estudio' => $tipo]);
        $estudio->setRelation('documentosEstudio', collect([$this->documento($estadoDoc, $subidoEn)]));

        return $estudio;
    }

    private function idioma(string $nivel, string $estadoDoc = 'aprobado', string $nombreIdioma = 'Inglés'): Idioma
    {
        $idioma = (new Idioma())->forceFill(['idioma' => $nombreIdioma, 'nivel' => $nivel]);
        $idioma->setRelation('documentosIdioma', collect([$this->documento($estadoDoc)]));

        return $idioma;
    }

    /** Crea un ámbito de divulgación real (persistido) con el puntaje indicado. */
    private function ambitoConPuntaje(int $puntaje): AmbitoDivulgacion
    {
        $producto = ProductoAcademico::create(['nombre_producto_academico' => 'Producto de prueba ' . uniqid()]);

        return AmbitoDivulgacion::create([
            'producto_academico_id' => $producto->id_producto_academico,
            'nombre_ambito_divulgacion' => 'Ámbito de prueba ' . uniqid(),
            'puntaje' => $puntaje,
        ]);
    }

    /**
     * Producción académica con sus dos fechas relevantes: cuándo se divulgó y cuándo se subió el
     * documento. Ambas tienen que caer dentro de la ventana del escalón para que puntúe.
     */
    private function produccion(
        int $ambitoId,
        string $fechaDivulgacion,
        ?string $subidoEn = null,
        string $estadoDoc = 'aprobado'
    ): ProduccionAcademica {
        $produccion = (new ProduccionAcademica())->forceFill([
            'ambito_divulgacion_id' => $ambitoId,
            'fecha_divulgacion' => $fechaDivulgacion,
        ]);
        $produccion->setRelation(
            'documentosProduccionAcademica',
            collect([$this->documento($estadoDoc, $subidoEn ?? $fechaDivulgacion)])
        );

        return $produccion;
    }

    private function evaluacion(float $promedio): EvaluacionDocente
    {
        return (new EvaluacionDocente())->forceFill(['promedio_evaluacion_docente' => $promedio]);
    }

    /** Experiencia en la Universidad Autónoma entre dos fechas. */
    private function experiencia(
        string $inicio,
        ?string $fin = null,
        string $estadoDoc = 'aprobado',
        bool $esUniautonoma = true,
        string $trabajoActual = TrabajoActual::NO
    ): Experiencia {
        $experiencia = (new Experiencia())->forceFill([
            'es_uniautonoma' => $esUniautonoma,
            'trabajo_actual' => $trabajoActual,
            'fecha_inicio' => $inicio,
            'fecha_finalizacion' => $fin,
        ]);
        $experiencia->setRelation('documentosExperiencia', collect([$this->documento($estadoDoc)]));

        return $experiencia;
    }

    /** Un tramo del historial: el docente estuvo en `$escalon` entre esas fechas. */
    private function tramo(string $escalon, string $desde, ?string $hasta = null, bool $revertido = false): HistorialEscalonDocente
    {
        $modelo = EscalonDocente::where('nombre', $escalon)->firstOrFail();

        $tramo = (new HistorialEscalonDocente())->forceFill([
            'escalon_id' => $modelo->id_escalon,
            'desde' => $desde,
            'hasta' => $hasta,
            'via' => HistorialEscalonDocente::VIA_INGRESO,
            'revertido_en' => $revertido ? now() : null,
        ]);
        $tramo->setRelation('escalon', $modelo);

        return $tramo;
    }

    private function periodo(string $fechaCierre): PeriodoAscenso
    {
        return (new PeriodoAscenso())->forceFill([
            'id_periodo_ascenso' => 1,
            'nombre' => 'Periodo de prueba',
            'fecha_cierre' => Carbon::parse($fechaCierre),
        ]);
    }

    /**
     * Construye un docente con las relaciones que consume el motor.
     *
     * @param array $opts estudios, idiomas, producciones, experiencias, evaluacion, historial
     */
    private function docente(array $opts = []): User
    {
        $user = new User();
        $user->setRelation('estudiosUsuario', collect($opts['estudios'] ?? []));
        $user->setRelation('idiomasUsuario', collect($opts['idiomas'] ?? []));
        $user->setRelation('produccionAcademicaUsuario', collect($opts['producciones'] ?? []));
        $user->setRelation('experienciasUsuario', collect($opts['experiencias'] ?? []));
        $user->setRelation('evaluacionDocenteUsuario', $opts['evaluacion'] ?? null);
        $user->setRelation('historialEscalonUsuario', collect($opts['historial'] ?? []));

        return $user;
    }

    /**
     * Auxiliar desde 2019 que cumple absolutamente todo lo que pide Asistente al cierre de 2026.
     * Los tests van rompiendo un requisito a la vez a partir de aquí.
     */
    private function auxiliarQueCumpleTodo(array $sobrescribir = []): User
    {
        $ambito = $this->ambitoConPuntaje(25);

        return $this->docente(array_merge([
            'historial' => [$this->tramo('Auxiliar', '2019-02-01')],
            'experiencias' => [$this->experiencia('2019-02-01')],
            'estudios' => [$this->estudio('Maestría', 'aprobado', '2020-01-01')],
            'idiomas' => [$this->idioma('B1')],
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2023-03-01')],
            'evaluacion' => $this->evaluacion(4.3),
        ], $sobrescribir));
    }

    // ---------------------------------------------------------------
    // Antigüedad: tiempo en el escalón anterior, no total en la Universidad
    // ---------------------------------------------------------------

    public function test_sin_historial_no_es_elegible_para_nada(): void
    {
        $resultado = $this->servicio->evaluarAscenso(
            $this->docente(['experiencias' => [$this->experiencia('2000-01-01')]]),
            $this->periodo('2026-12-31')
        );

        $this->assertFalse($resultado['elegible']);
        $this->assertNull($resultado['escalon_vigente']);
        $this->assertStringContainsString('no ha ingresado al escalafón', $resultado['razon']);
        // Fuera del escalafón no hay ventana contra la cual medir producción: ambos en cero, no null.
        $this->assertSame(0, $resultado['puntaje_total']);
        $this->assertSame(0, $resultado['puntaje_declarado']);
    }

    public function test_antiguedad_insuficiente_no_asciende(): void
    {
        // Auxiliar desde hace 47 meses: le falta uno para los 48 que pide Asistente.
        $desde = Carbon::parse('2026-12-31')->subMonths(47)->toDateString();

        $resultado = $this->servicio->evaluarAscenso(
            $this->auxiliarQueCumpleTodo([
                'historial' => [$this->tramo('Auxiliar', $desde)],
                'experiencias' => [$this->experiencia($desde)],
            ]),
            $this->periodo('2026-12-31')
        );

        $this->assertFalse($resultado['elegible']);
        $this->assertSame(47, $resultado['meses_en_escalon']);
        $this->assertContains('antiguedad', array_column($resultado['faltantes'], 'campo'));
    }

    /**
     * Un cargo marcado como el trabajo actual del docente no se detiene en la fecha que tenga
     * guardada: cuenta hasta el corte.
     *
     * Es el caso de quien informó su vinculación vigente y de paso llenó la fecha de fin con el
     * día en que llenó el formulario. Antes esa fecha congelaba la antigüedad ahí mismo y el
     * docente tenía que volver a editar el registro para que el escalafón se moviera.
     */
    public function test_trabajo_actual_no_se_detiene_en_la_fecha_de_finalizacion_guardada(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [$this->tramo('Auxiliar', '2019-02-01')],
            'experiencias' => [
                $this->experiencia('2019-02-01', '2019-03-01', 'aprobado', true, TrabajoActual::SI),
            ],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        // De 2019-02-01 al cierre de 2026 son 94 meses, no el mes que decía la fecha guardada.
        $this->assertSame(94, $resultado['meses_en_escalon']);
        $this->assertTrue($resultado['elegible']);
    }

    /**
     * La antigüedad de un trabajo actual avanza sola con el calendario.
     *
     * Al docente al que hoy le falta un día para los 48 meses que pide Asistente, mañana ya no le
     * falta, sin que nadie toque el registro. Es la razón de ser de `fechaFinEfectiva()`.
     */
    public function test_antiguedad_del_trabajo_actual_avanza_con_el_calendario(): void
    {
        $hoy = Carbon::parse('2026-09-02');
        // Un día después de hoy se cumplen los 48 meses exactos.
        $desde = $hoy->copy()->addDay()->subMonths(48)->toDateString();

        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [$this->tramo('Auxiliar', $desde)],
            'experiencias' => [
                $this->experiencia($desde, null, 'aprobado', true, TrabajoActual::SI),
            ],
        ]);

        // Sin periodo de ascenso el corte es hoy, que es justamente lo que debe moverse. Que no
        // haya ninguno vigente es la precondición del test, y se declara en vez de suponerse: la
        // base de desarrollo puede tener uno abierto —del seeder de demo, o creado por alguien
        // probando la pantalla— y entonces el corte sería su fecha de cierre y no hoy. La
        // transacción de la prueba lo deshace.
        PeriodoAscenso::query()->update(['cerrado_en' => now()]);

        Carbon::setTestNow($hoy);
        $hoyMismo = $this->servicio->evaluarAscenso($docente);

        Carbon::setTestNow($hoy->copy()->addDay());
        $manana = $this->servicio->evaluarAscenso($docente);

        Carbon::setTestNow();

        $this->assertSame(47, $hoyMismo['meses_en_escalon']);
        $this->assertFalse($hoyMismo['elegible']);

        $this->assertSame(48, $manana['meses_en_escalon']);
        $this->assertTrue($manana['elegible']);
    }

    public function test_cumpliendo_todo_es_elegible_al_siguiente_escalon(): void
    {
        $resultado = $this->servicio->evaluarAscenso(
            $this->auxiliarQueCumpleTodo(),
            $this->periodo('2026-12-31')
        );

        $this->assertTrue($resultado['elegible']);
        $this->assertSame('Auxiliar', $resultado['escalon_vigente']);
        $this->assertSame('Asistente', $resultado['escalon_objetivo']);
        $this->assertSame(HistorialEscalonDocente::VIA_REQUISITOS, $resultado['via']);
        $this->assertSame(MotorEscalafonDocenteService::ELEGIBLE, $resultado['estado_antiguedad']);
    }

    /**
     * Los tramos de un mismo escalón se acumulan aunque el docente se haya retirado en medio.
     */
    public function test_tramos_interrumpidos_del_mismo_escalon_se_acumulan(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [
                $this->tramo('Auxiliar', '2019-02-01', '2021-02-01'), // 24 meses
                $this->tramo('Auxiliar', '2023-02-01'),               // 24 meses hasta 2025-02-01
            ],
            'experiencias' => [
                $this->experiencia('2019-02-01', '2021-02-01'),
                $this->experiencia('2023-02-01'),
            ],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2025-02-01'));

        $this->assertSame(48, $resultado['meses_en_escalon']);
        $this->assertTrue($resultado['elegible']);
    }

    /**
     * El historial dice 60 meses pero el certificado solo cubre 30: cuentan 30.
     *
     * Es la regla de "la antigüedad tiene que estar respaldada". Lo que el historial afirma y el
     * documento no acredita no suma.
     */
    public function test_solo_cuenta_la_antiguedad_respaldada_por_experiencia_aprobada(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [$this->tramo('Auxiliar', '2021-01-01')], // 60 meses hasta 2026-01-01
            'experiencias' => [$this->experiencia('2021-01-01', '2023-07-01')], // 30 meses
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-01-01'));

        $this->assertSame(30, $resultado['meses_en_escalon']);
        $this->assertFalse($resultado['elegible']);
    }

    /**
     * Dos experiencias solapadas no pueden contar doble.
     *
     * Regresión: el cálculo anterior sumaba cada experiencia por separado, así que un docente con
     * dos registros que cubrían el mismo periodo acreditaba el doble de antigüedad de la que tenía.
     */
    public function test_experiencias_solapadas_no_cuentan_doble(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [$this->tramo('Auxiliar', '2020-01-01')],
            'experiencias' => [
                $this->experiencia('2020-01-01', '2024-01-01'), // 48 meses
                $this->experiencia('2021-01-01', '2023-01-01'), // dentro de la anterior
            ],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2024-01-01'));

        $this->assertSame(48, $resultado['meses_en_escalon']);
    }

    public function test_experiencia_que_no_es_uniautonoma_no_respalda_antiguedad(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'experiencias' => [$this->experiencia('2019-02-01', null, 'aprobado', false)],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(0, $resultado['meses_en_escalon']);
        $this->assertFalse($resultado['elegible']);
    }

    public function test_un_tramo_revertido_no_suma_antiguedad(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [
                $this->tramo('Auxiliar', '2019-02-01', '2023-02-01', true), // revertido
                $this->tramo('Auxiliar', '2023-02-01'),
            ],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2025-02-01'));

        // Solo los 24 meses del tramo que sigue contando.
        $this->assertSame(24, $resultado['meses_en_escalon']);
    }

    // ---------------------------------------------------------------
    // Semáforo de la bandeja
    // ---------------------------------------------------------------

    public function test_experiencia_sin_aprobar_deja_el_semaforo_en_por_verificar(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'experiencias' => [$this->experiencia('2019-02-01', null, 'pendiente')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(MotorEscalafonDocenteService::POR_VERIFICAR_EXPERIENCIA, $resultado['estado_antiguedad']);
        $this->assertSame(0, $resultado['meses_en_escalon']);
        $this->assertGreaterThanOrEqual(48, $resultado['meses_en_escalon_declarados']);
    }

    public function test_sin_experiencia_suficiente_ni_declarando(): void
    {
        $desde = Carbon::parse('2026-12-31')->subMonths(10)->toDateString();

        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [$this->tramo('Auxiliar', $desde)],
            'experiencias' => [$this->experiencia($desde, null, 'pendiente')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(MotorEscalafonDocenteService::SIN_EXPERIENCIA_SUFICIENTE, $resultado['estado_antiguedad']);
    }

    public function test_antiguedad_cumplida_pero_faltan_otros_requisitos(): void
    {
        $docente = $this->auxiliarQueCumpleTodo(['idiomas' => []]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertFalse($resultado['elegible']);
        $this->assertSame(MotorEscalafonDocenteService::ANTIGUEDAD_CUMPLIDA, $resultado['estado_antiguedad']);
        $this->assertContains('idioma', array_column($resultado['faltantes'], 'campo'));
    }

    // ---------------------------------------------------------------
    // Producción académica: la ventana del escalón
    // ---------------------------------------------------------------

    /**
     * Lo divulgado antes de entrar al escalón ya se usó para llegar a él.
     */
    public function test_produccion_divulgada_antes_de_entrar_al_escalon_no_puntua(): void
    {
        $ambito = $this->ambitoConPuntaje(25);

        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2018-05-01')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(0, $resultado['puntaje_total']);
        $this->assertFalse($resultado['elegible']);
        $this->assertContains('puntaje', array_column($resultado['faltantes'], 'campo'));
    }

    /**
     * Divulgada en ventana pero cargada al sistema después del cierre: va para el siguiente periodo.
     */
    public function test_produccion_subida_despues_del_cierre_no_puntua(): void
    {
        $ambito = $this->ambitoConPuntaje(25);

        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2026-03-01', '2027-01-02')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(0, $resultado['puntaje_total']);
        $this->assertFalse($resultado['elegible']);
    }

    /**
     * Subida a tiempo y avalada después del cierre: sí puntúa.
     *
     * Lo que el reglamento exige es haberla presentado a tiempo. Que el Evaluador de Producción
     * tarde en avalarla no puede perjudicar al docente, que ya hizo su parte.
     */
    public function test_produccion_subida_a_tiempo_puntua_aunque_el_aval_llegue_despues(): void
    {
        $ambito = $this->ambitoConPuntaje(25);

        // El documento se subió en ventana; su estado es 'aprobado' hoy, después del cierre.
        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2026-03-01', '2026-03-05')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(25, $resultado['puntaje_total']);
        $this->assertTrue($resultado['elegible']);
    }

    public function test_produccion_sin_aval_no_puntua(): void
    {
        $ambito = $this->ambitoConPuntaje(25);

        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2023-03-01', null, 'pendiente')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(0, $resultado['puntaje_total']);
        $this->assertContains('produccion_academica', array_column($resultado['faltantes'], 'campo'));
    }

    // ---------------------------------------------------------------
    // puntaje_declarado: lo que sumaría si le avalaran lo que ya subió
    // ---------------------------------------------------------------

    /**
     * El hermano de `meses_en_escalon_declarados` para la producción.
     *
     * Sin él, el docente sube un producto, ve el puntaje quieto y cree que se perdió; el requisito
     * de ascenso sigue leyendo `puntaje_total`, que no se mueve hasta que haya aval.
     */
    public function test_la_produccion_pendiente_suma_al_declarado_pero_no_al_total(): void
    {
        $avalada = $this->ambitoConPuntaje(25);
        $enRevision = $this->ambitoConPuntaje(6);

        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [
                $this->produccion($avalada->id_ambito_divulgacion, '2023-03-01'),
                $this->produccion($enRevision->id_ambito_divulgacion, '2023-04-01', null, 'pendiente'),
            ],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(25, $resultado['puntaje_total']);
        $this->assertSame(31, $resultado['puntaje_declarado']);
    }

    /** Lo rechazado ya se decidió que no vale: no puede reaparecer como promesa en el declarado. */
    public function test_la_produccion_rechazada_no_suma_ni_al_declarado(): void
    {
        $avalada = $this->ambitoConPuntaje(25);
        $rechazada = $this->ambitoConPuntaje(6);

        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [
                $this->produccion($avalada->id_ambito_divulgacion, '2023-03-01'),
                $this->produccion($rechazada->id_ambito_divulgacion, '2023-04-01', null, 'rechazado'),
            ],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(25, $resultado['puntaje_total']);
        $this->assertSame(25, $resultado['puntaje_declarado']);
    }

    /**
     * Sin nada en revisión los dos números coinciden: el campo no se omite ni llega null, para que
     * el frontend pueda restarlos siempre.
     */
    public function test_sin_nada_pendiente_el_declarado_iguala_al_total(): void
    {
        $resultado = $this->servicio->evaluarAscenso(
            $this->auxiliarQueCumpleTodo(),
            $this->periodo('2026-12-31')
        );

        $this->assertSame(25, $resultado['puntaje_total']);
        $this->assertSame($resultado['puntaje_total'], $resultado['puntaje_declarado']);
    }

    /**
     * El declarado relaja el aval, no la ventana: lo divulgado antes de entrar al escalón sigue sin
     * contar aunque esté pendiente de revisión.
     */
    public function test_el_declarado_respeta_la_ventana_del_escalon(): void
    {
        $ambito = $this->ambitoConPuntaje(25);

        $docente = $this->auxiliarQueCumpleTodo([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2018-05-01', null, 'pendiente')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame(0, $resultado['puntaje_total']);
        $this->assertSame(0, $resultado['puntaje_declarado']);
    }

    /**
     * El puntaje arranca en cero en cada escalón: lo que sirvió para llegar aquí no vuelve a servir.
     */
    public function test_la_produccion_no_es_acumulable_entre_escalones(): void
    {
        $ambito = $this->ambitoConPuntaje(45);

        // Ascendió a Asistente en 2024; toda su producción es de cuando era Auxiliar.
        $docente = $this->docente([
            'historial' => [
                $this->tramo('Auxiliar', '2019-02-01', '2024-01-01'),
                $this->tramo('Asistente', '2024-01-01'),
            ],
            'experiencias' => [$this->experiencia('2019-02-01')],
            'estudios' => [$this->estudio('Doctorado')],
            'idiomas' => [$this->idioma('B2')],
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2022-05-01')],
            'evaluacion' => $this->evaluacion(4.5),
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame('Asistente', $resultado['escalon_vigente']);
        $this->assertSame(0, $resultado['puntaje_total']);
    }

    // ---------------------------------------------------------------
    // Ascensos de uno en uno
    // ---------------------------------------------------------------

    /**
     * Un Auxiliar que cumple todo lo de Titular sigue teniendo por objetivo Asistente.
     *
     * El motor evalúa un solo escalón —el inmediatamente superior— en vez de recorrerlos todos de
     * mayor a menor como antes, que permitía saltar de Auxiliar a Titular de una sola vez.
     */
    public function test_no_se_pueden_saltar_escalones(): void
    {
        $ambito = $this->ambitoConPuntaje(100);

        $docente = $this->docente([
            'historial' => [$this->tramo('Auxiliar', '2000-01-01')],
            'experiencias' => [$this->experiencia('2000-01-01')],
            // Sin Doctorado, para que no se dispare la excepción y se vea el tope por escalón.
            'estudios' => [$this->estudio('Maestría')],
            'idiomas' => [$this->idioma('C2')],
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, '2020-01-01')],
            'evaluacion' => $this->evaluacion(5.0),
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertSame('Asistente', $resultado['escalon_objetivo']);
        $this->assertTrue($resultado['elegible']);
    }

    public function test_el_escalon_mas_alto_no_tiene_ascenso_posible(): void
    {
        $resultado = $this->servicio->evaluarAscenso(
            $this->docente([
                'historial' => [$this->tramo('Titular', '2000-01-01')],
                'experiencias' => [$this->experiencia('2000-01-01')],
            ]),
            $this->periodo('2026-12-31')
        );

        $this->assertFalse($resultado['elegible']);
        $this->assertNull($resultado['escalon_objetivo']);
        $this->assertStringContainsString('escalón más alto', $resultado['razon']);
    }

    // ---------------------------------------------------------------
    // Reglas de excepción
    // ---------------------------------------------------------------

    /**
     * El Doctorado otorga Asociado directo, sin antigüedad, sin puntaje y saltándose Asistente.
     */
    public function test_la_excepcion_por_doctorado_salta_todos_los_requisitos(): void
    {
        $docente = $this->docente([
            'historial' => [$this->tramo('Auxiliar', '2026-11-01')], // recién ingresado
            'estudios' => [$this->estudio('Doctorado')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertTrue($resultado['elegible']);
        $this->assertSame('Asociado', $resultado['escalon_objetivo']);
        $this->assertSame(HistorialEscalonDocente::VIA_EXCEPCION, $resultado['via']);
        $this->assertSame(0, $resultado['meses_en_escalon']);
        $this->assertSame([], $resultado['faltantes']);
    }

    /**
     * Quien llegó a Asociado por excepción acumula tiempo como Asociado desde el acto, aunque nunca
     * haya cumplido los 120 meses de Asistente.
     */
    public function test_quien_entra_por_excepcion_acumula_tiempo_en_su_nuevo_escalon(): void
    {
        $docente = $this->docente([
            'historial' => [
                $this->tramo('Auxiliar', '2023-01-01', '2024-01-01'),
                $this->tramo('Asociado', '2024-01-01'), // entró por excepción
            ],
            'experiencias' => [$this->experiencia('2023-01-01')],
            'estudios' => [$this->estudio('Doctorado')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-01-01'));

        $this->assertSame('Asociado', $resultado['escalon_vigente']);
        $this->assertSame('Titular', $resultado['escalon_objetivo']);
        $this->assertSame(24, $resultado['meses_en_escalon']);
    }

    /**
     * La excepción no aplica hacia abajo: quien ya es Asociado no "asciende" a Asociado.
     */
    public function test_la_excepcion_no_aplica_si_ya_esta_en_ese_escalon_o_por_encima(): void
    {
        $docente = $this->docente([
            'historial' => [$this->tramo('Asociado', '2024-01-01')],
            'estudios' => [$this->estudio('Doctorado')],
            'experiencias' => [$this->experiencia('2024-01-01')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-01-01'));

        $this->assertSame('Titular', $resultado['escalon_objetivo']);
        $this->assertFalse($resultado['elegible']);
    }

    // ---------------------------------------------------------------
    // La fecha de corte
    // ---------------------------------------------------------------

    /**
     * La antigüedad se mide al cierre, no al día en que se firma.
     *
     * Un docente que cumple sus 48 meses tres semanas después del cierre no entra en ese periodo,
     * aunque el acto se firme más tarde y para entonces ya los tenga. Es lo que hace que dos
     * expedientes iguales reciban la misma respuesta.
     */
    public function test_la_antiguedad_se_mide_a_la_fecha_de_cierre(): void
    {
        // 48 meses exactos el 2027-01-21, tres semanas después del cierre.
        $docente = $this->auxiliarQueCumpleTodo([
            'historial' => [$this->tramo('Auxiliar', '2023-01-21')],
            'experiencias' => [$this->experiencia('2023-01-21')],
        ]);

        $alCierre = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));
        $tresSemanasDespues = $this->servicio->evaluarAscenso($docente, $this->periodo('2027-01-21'));

        $this->assertFalse($alCierre['elegible']);
        $this->assertSame(47, $alCierre['meses_en_escalon']);

        $this->assertTrue($tresSemanasDespues['elegible']);
        $this->assertSame(48, $tresSemanasDespues['meses_en_escalon']);
    }

    /**
     * Un documento subido después del cierre no cuenta, aunque hoy esté aprobado.
     */
    public function test_un_estudio_subido_despues_del_cierre_no_cuenta(): void
    {
        $docente = $this->auxiliarQueCumpleTodo([
            'estudios' => [$this->estudio('Maestría', 'aprobado', '2027-02-01')],
        ]);

        $resultado = $this->servicio->evaluarAscenso($docente, $this->periodo('2026-12-31'));

        $this->assertFalse($resultado['elegible']);
        $this->assertContains('formacion', array_column($resultado['faltantes'], 'campo'));
    }

    // ---------------------------------------------------------------
    // Requisitos que ya regían y no debían romperse
    // ---------------------------------------------------------------

    public function test_un_doctorado_cumple_un_requisito_de_maestria(): void
    {
        $this->assertTrue(
            $this->servicio->tieneFormacionAprobada(
                $this->docente(['estudios' => [$this->estudio('Doctorado')]]),
                'Maestría'
            )
        );
    }

    public function test_el_nivel_de_idioma_solo_cuenta_para_el_idioma_exigido(): void
    {
        $docente = $this->docente(['idiomas' => [$this->idioma('C1', 'aprobado', 'Francés')]]);

        $this->assertSame('C1', $this->servicio->nivelMcerMaximoAprobado($docente));
        $this->assertNull($this->servicio->nivelMcerMaximoAprobado($docente, 'Inglés'));
    }

    public function test_una_evaluacion_docente_baja_bloquea_el_ascenso(): void
    {
        $resultado = $this->servicio->evaluarAscenso(
            $this->auxiliarQueCumpleTodo(['evaluacion' => $this->evaluacion(3.9)]),
            $this->periodo('2026-12-31')
        );

        $this->assertFalse($resultado['elegible']);
        $this->assertContains('evaluacion', array_column($resultado['faltantes'], 'campo'));
    }
}
