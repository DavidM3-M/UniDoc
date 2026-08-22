<?php

namespace Tests\Unit;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Experiencia;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Docente\EvaluacionDocente;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\TiposProductoAcademico\ProductoAcademico;
use App\Models\Usuario\User;
use App\Services\MotorEscalafonDocenteService;
use Database\Seeders\EscalonDocenteSeeder;
use Database\Seeders\ReglaExcepcionEscalonSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pruebas del motor de evaluación data-driven del escalafón docente.
 *
 * A diferencia del `CalculoPuntajeDocenteService` que reemplaza, este motor lee sus reglas de
 * base de datos (escalones, excepciones, puntaje por ámbito), así que las pruebas siembran esas
 * tablas con `EscalonDocenteSeeder`/`ReglaExcepcionEscalonSeeder` —los mismos valores que ya
 * regían hardcodeados— y usan `DatabaseTransactions` para no dejar rastro en la base real.
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

    private function documento(string $estado = 'aprobado'): Documento
    {
        return (new Documento())->forceFill(['estado' => $estado]);
    }

    private function contrato(string $tipo = 'Planta', string $inicio = '2015-01-01', ?string $fin = null): Contratacion
    {
        return (new Contratacion())->forceFill([
            'tipo_contrato' => $tipo,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ]);
    }

    private function estudio(string $tipo, string $estadoDoc = 'aprobado'): Estudio
    {
        $estudio = (new Estudio())->forceFill(['tipo_estudio' => $tipo]);
        $estudio->setRelation('documentosEstudio', collect([$this->documento($estadoDoc)]));

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

    private function produccion(int $ambitoId, string $estadoDoc = 'aprobado'): ProduccionAcademica
    {
        $produccion = (new ProduccionAcademica())->forceFill(['ambito_divulgacion_id' => $ambitoId]);
        $produccion->setRelation('documentosProduccionAcademica', collect([$this->documento($estadoDoc)]));

        return $produccion;
    }

    private function evaluacion(float $promedio): EvaluacionDocente
    {
        return (new EvaluacionDocente())->forceFill(['promedio_evaluacion_docente' => $promedio]);
    }

    /** Experiencia Uniautónoma aprobada, de `$meses` meses de duración. */
    private function experienciaUniautonoma(int $meses, bool $esUniautonoma = true, string $estadoDoc = 'aprobado'): Experiencia
    {
        $inicio = now()->subMonths($meses);
        $experiencia = (new Experiencia())->forceFill([
            'es_uniautonoma' => $esUniautonoma,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_finalizacion' => null,
        ]);
        $experiencia->setRelation('documentosExperiencia', collect([$this->documento($estadoDoc)]));

        return $experiencia;
    }

    /**
     * Construye un docente con las relaciones que consume el servicio.
     *
     * @param array $opts contrato, estudios, idiomas, producciones, experiencias, evaluacion
     */
    private function docente(array $opts = []): User
    {
        $user = new User();
        $user->setRelation('contratacionUsuario', array_key_exists('contrato', $opts) ? $opts['contrato'] : $this->contrato());
        $user->setRelation('estudiosUsuario', collect($opts['estudios'] ?? []));
        $user->setRelation('idiomasUsuario', collect($opts['idiomas'] ?? []));
        $user->setRelation('produccionAcademicaUsuario', collect($opts['producciones'] ?? []));
        $user->setRelation('experienciasUsuario', collect($opts['experiencias'] ?? []));
        $user->setRelation('evaluacionDocenteUsuario', $opts['evaluacion'] ?? null);

        return $user;
    }

    /** Docente que cumple todos los requisitos de Titular. */
    private function docenteTitular(AmbitoDivulgacion $ambitoTop, array $sobrescribir = []): User
    {
        return $this->docente(array_merge([
            'contrato' => $this->contrato('Planta', '2000-01-01'),
            'estudios' => [$this->estudio('Doctorado')],
            'idiomas' => [$this->idioma('B2')],
            'producciones' => [
                $this->produccion($ambitoTop->id_ambito_divulgacion),
                $this->produccion($ambitoTop->id_ambito_divulgacion),
                $this->produccion($ambitoTop->id_ambito_divulgacion),
                $this->produccion($ambitoTop->id_ambito_divulgacion),
                $this->produccion($ambitoTop->id_ambito_divulgacion),
                $this->produccion($ambitoTop->id_ambito_divulgacion),
            ],
            'experiencias' => [$this->experienciaUniautonoma(216)],
            'evaluacion' => $this->evaluacion(4.5),
        ], $sobrescribir));
    }

    // ---------------------------------------------------------------
    // El escalafon no depende del tipo de contratacion
    // ---------------------------------------------------------------

    /**
     * Un docente sin contrato registrado se evalua igual que cualquier otro.
     *
     * Antes una guarda cortaba aqui y devolvia categoria "Ninguna" sin mirar un solo requisito,
     * asi que quien no tuviera contrato de planta aparecia con puntaje 0 aunque cumpliera todo.
     */
    public function test_sin_contratacion_igual_se_evalua(): void
    {
        $ambito = $this->ambitoConPuntaje(10);

        $resultado = $this->servicio->evaluar(
            $this->docenteTitular($ambito, ['contrato' => null])
        );

        $this->assertTrue($resultado['valido']);
        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertSame(60, $resultado['puntaje_total']);
    }

    /** Sin cumplir requisitos cae al escalon base, no a "Ninguna". */
    public function test_sin_contratacion_y_sin_requisitos_cae_al_escalon_base(): void
    {
        $resultado = $this->servicio->evaluar($this->docente(['contrato' => null]));

        $this->assertTrue($resultado['valido']);
        $this->assertNotSame('Ninguna', $resultado['categoria_lograda']);
    }

    // ---------------------------------------------------------------
    // Categorías, requisitos por escalón
    // ---------------------------------------------------------------

    public function test_cumpliendo_todo_logra_titular(): void
    {
        $ambito = $this->ambitoConPuntaje(10);

        $resultado = $this->servicio->evaluar($this->docenteTitular($ambito));

        $this->assertTrue($resultado['valido']);
        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertSame(60, $resultado['puntaje_total']);
        $this->assertSame([], $resultado['faltantes_por_categoria']);
    }

    public function test_con_maestria_y_requisitos_completos_logra_asistente(): void
    {
        $ambito = $this->ambitoConPuntaje(10);

        $resultado = $this->servicio->evaluar($this->docente([
            'estudios' => [$this->estudio('Maestría')],
            'idiomas' => [$this->idioma('B1')],
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion), $this->produccion($ambito->id_ambito_divulgacion)],
            'experiencias' => [$this->experienciaUniautonoma(48)],
            'evaluacion' => $this->evaluacion(4.2),
        ]));

        $this->assertSame('Asistente', $resultado['categoria_lograda']);
        $this->assertSame(20, $resultado['puntaje_total']);
    }

    public function test_sin_cumplir_nada_queda_en_el_escalon_base(): void
    {
        $resultado = $this->servicio->evaluar($this->docente([
            'estudios' => [$this->estudio('Pregrado')],
            'idiomas' => [$this->idioma('A2')],
            'evaluacion' => $this->evaluacion(4.2),
        ]));

        $this->assertTrue($resultado['valido']);
        $this->assertSame('Auxiliar', $resultado['categoria_lograda']);
        $this->assertArrayHasKey('Asistente', $resultado['faltantes_por_categoria']);
    }

    // ---------------------------------------------------------------
    // Excepciones: "tiene Doctorado -> mínimo Asociado"
    // ---------------------------------------------------------------

    public function test_doctorado_con_puntaje_insuficiente_queda_asociado_por_excepcion(): void
    {
        $ambito = $this->ambitoConPuntaje(10);

        $resultado = $this->servicio->evaluar($this->docente([
            'estudios' => [$this->estudio('Doctorado')],
            'idiomas' => [$this->idioma('B2')],
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion)], // 10 puntos, Titular pide 60
            'experiencias' => [$this->experienciaUniautonoma(216)],
            'evaluacion' => $this->evaluacion(4.5),
        ]));

        $this->assertSame('Asociado', $resultado['categoria_lograda']);
        $this->assertSame(10, $resultado['puntaje_total']);

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('puntaje', $campos);
    }

    // ---------------------------------------------------------------
    // Jerarquía de formación: "al menos este nivel", no coincidencia exacta
    // ---------------------------------------------------------------

    public function test_un_nivel_superior_cumple_el_requisito_de_uno_inferior(): void
    {
        // Doctorado (orden 60) debe cumplir un requisito de Maestría (orden 50). Antes fallaba:
        // se comparaba por igualdad de texto y tener más formación no servía de nada.
        $this->assertTrue(
            $this->servicio->tieneFormacionAprobada(
                $this->docente(['estudios' => [$this->estudio('Doctorado')]]),
                'Maestría'
            )
        );
    }

    public function test_un_nivel_inferior_no_cumple_el_requisito_de_uno_superior(): void
    {
        $this->assertFalse(
            $this->servicio->tieneFormacionAprobada(
                $this->docente(['estudios' => [$this->estudio('Maestría')]]),
                'Doctorado'
            )
        );
    }

    public function test_niveles_sinonimos_con_el_mismo_orden_se_cumplen_entre_si(): void
    {
        // "Pregrado" (constante vieja) y "Universitario" (SNIES) comparten orden 30, así que un
        // estudio viejo sigue cumpliendo un requisito expresado con el nombre nuevo.
        $this->assertTrue(
            $this->servicio->tieneFormacionAprobada(
                $this->docente(['estudios' => [$this->estudio('Pregrado')]]),
                'Universitario'
            )
        );
    }

    public function test_la_formacion_complementaria_no_cumple_requisitos_del_escalafon(): void
    {
        // Un diplomado (orden nulo) se registra en la hoja de vida pero no asciende a nadie.
        $this->assertFalse(
            $this->servicio->tieneFormacionAprobada(
                $this->docente(['estudios' => [$this->estudio('Diplomado')]]),
                'Universitario'
            )
        );
    }

    public function test_un_estudio_sin_documento_aprobado_no_cumple_aunque_el_nivel_alcance(): void
    {
        $this->assertFalse(
            $this->servicio->tieneFormacionAprobada(
                $this->docente(['estudios' => [$this->estudio('Doctorado', 'pendiente')]]),
                'Maestría'
            )
        );
    }

    public function test_sin_doctorado_no_aplica_la_excepcion(): void
    {
        $resultado = $this->servicio->evaluar($this->docente([
            'estudios' => [$this->estudio('Pregrado')],
            'idiomas' => [$this->idioma('A2')],
            'evaluacion' => $this->evaluacion(4.2),
        ]));

        $this->assertSame('Auxiliar', $resultado['categoria_lograda']);
    }

    public function test_doctorado_no_aprobado_no_activa_la_excepcion(): void
    {
        $resultado = $this->servicio->evaluar($this->docente([
            'estudios' => [$this->estudio('Doctorado', 'pendiente')],
            'idiomas' => [$this->idioma('A2')],
            'evaluacion' => $this->evaluacion(4.2),
        ]));

        $this->assertSame('Auxiliar', $resultado['categoria_lograda']);
    }

    // ---------------------------------------------------------------
    // Puntaje: sale del ámbito real, no de una clasificación hardcodeada
    // ---------------------------------------------------------------

    public function test_el_puntaje_sale_del_ambito_configurado_en_el_catalogo(): void
    {
        $ambito = $this->ambitoConPuntaje(7); // valor arbitrario, no uno de los "clásicos" 10/6/3

        $puntaje = $this->servicio->calcularPuntaje($this->docente([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion)],
        ]));

        $this->assertSame(7, $puntaje);
    }

    public function test_produccion_con_documento_no_aprobado_no_suma(): void
    {
        $ambito = $this->ambitoConPuntaje(10);

        $puntaje = $this->servicio->calcularPuntaje($this->docente([
            'producciones' => [$this->produccion($ambito->id_ambito_divulgacion, 'pendiente')],
        ]));

        $this->assertSame(0, $puntaje);
    }

    // ---------------------------------------------------------------
    // Antigüedad: meses de experiencia Uniautónoma aprobada
    // ---------------------------------------------------------------

    public function test_meses_uniautonoma_solo_cuenta_experiencias_marcadas_y_aprobadas(): void
    {
        $meses = $this->servicio->calcularMesesUniautonoma($this->docente([
            'experiencias' => [
                $this->experienciaUniautonoma(24),
                $this->experienciaUniautonoma(12, esUniautonoma: false), // no marcada, no cuenta
                $this->experienciaUniautonoma(6, estadoDoc: 'pendiente'), // no aprobada, no cuenta
            ],
        ]));

        $this->assertSame(24, $meses);
    }

    public function test_meses_uniautonoma_suma_varias_experiencias(): void
    {
        $meses = $this->servicio->calcularMesesUniautonoma($this->docente([
            'experiencias' => [
                $this->experienciaUniautonoma(24),
                $this->experienciaUniautonoma(30),
            ],
        ]));

        $this->assertSame(54, $meses);
    }

    // ---------------------------------------------------------------
    // Forma del resultado y orden del escalafón
    // ---------------------------------------------------------------

    public function test_el_resultado_expone_siempre_las_mismas_claves(): void
    {
        $ambito = $this->ambitoConPuntaje(10);
        $resultado = $this->servicio->evaluar($this->docenteTitular($ambito));

        $this->assertSame(
            ['valido', 'categoria_lograda', 'razon', 'puntaje_total', 'faltantes_por_categoria', 'evaluacion_minima_aplicada'],
            array_keys($resultado)
        );
    }

    public function test_rango_de_categoria_ordena_el_escalafon(): void
    {
        $this->assertGreaterThan(
            MotorEscalafonDocenteService::rangoCategoria('Asociado'),
            MotorEscalafonDocenteService::rangoCategoria('Titular')
        );
        $this->assertGreaterThan(
            MotorEscalafonDocenteService::rangoCategoria('Auxiliar'),
            MotorEscalafonDocenteService::rangoCategoria('Asistente')
        );
        $this->assertSame(0, MotorEscalafonDocenteService::rangoCategoria(null));
        $this->assertSame(0, MotorEscalafonDocenteService::rangoCategoria('Inexistente'));
    }
}
