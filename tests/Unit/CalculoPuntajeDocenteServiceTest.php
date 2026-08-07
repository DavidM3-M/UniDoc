<?php

namespace Tests\Unit;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Docente\EvaluacionDocente;
use App\Models\TalentoHumano\Contratacion;
use App\Models\Usuario\User;
use App\Services\CalculoPuntajeDocenteService;
use Tests\TestCase;

/**
 * Pruebas del cálculo de puntaje y categoría (escalafón) docente.
 *
 * Se arman usuarios en memoria con `setRelation()` en lugar de registros reales:
 * `evaluar()` solo lee relaciones ya cargadas, así que no hace falta base de datos
 * y cada caso deja explícito qué combinación de requisitos se está probando.
 *
 * Estas pruebas fijan el comportamiento actual como red de seguridad antes de
 * volver configurable el umbral de evaluación (hoy 4.0, hardcodeado en
 * `CalculoPuntajeDocenteService::CATEGORIAS`).
 */
class CalculoPuntajeDocenteServiceTest extends TestCase
{
    private CalculoPuntajeDocenteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new CalculoPuntajeDocenteService();
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
            'fecha_inicio'  => $inicio,
            'fecha_fin'     => $fin,
        ]);
    }

    private function estudio(string $tipo, string $estadoDoc = 'aprobado'): Estudio
    {
        $estudio = (new Estudio())->forceFill(['tipo_estudio' => $tipo]);
        $estudio->setRelation('documentosEstudio', collect([$this->documento($estadoDoc)]));

        return $estudio;
    }

    private function idioma(string $nivel, string $estadoDoc = 'aprobado'): Idioma
    {
        $idioma = (new Idioma())->forceFill(['nivel' => $nivel]);
        $idioma->setRelation('documentosIdioma', collect([$this->documento($estadoDoc)]));

        return $idioma;
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

    /**
     * Construye un docente con las relaciones que consume el servicio.
     *
     * @param array $opts contrato, estudios, idiomas, producciones, evaluacion
     */
    private function docente(array $opts = []): User
    {
        $user = new User();
        $user->setRelation('contratacionUsuario', array_key_exists('contrato', $opts) ? $opts['contrato'] : $this->contrato());
        $user->setRelation('estudiosUsuario', collect($opts['estudios'] ?? []));
        $user->setRelation('idiomasUsuario', collect($opts['idiomas'] ?? []));
        $user->setRelation('produccionAcademicaUsuario', collect($opts['producciones'] ?? []));
        $user->setRelation('evaluacionDocenteUsuario', $opts['evaluacion'] ?? null);

        return $user;
    }

    /** Docente que cumple todos los requisitos de Titular. */
    private function docenteTitular(array $sobrescribir = []): User
    {
        return $this->docente(array_merge([
            'contrato'     => $this->contrato('Planta', '2015-01-01'),
            'estudios'     => [$this->estudio('Doctorado')],
            'idiomas'      => [$this->idioma('B2')],
            // Seis producciones "top" a 10 puntos cada una = 60, el mínimo de Titular.
            'producciones' => [
                $this->produccion(1), $this->produccion(1), $this->produccion(1),
                $this->produccion(1), $this->produccion(1), $this->produccion(1),
            ],
            'evaluacion'   => $this->evaluacion(4.5),
        ], $sobrescribir));
    }

    // ---------------------------------------------------------------
    // Guarda: solo aplica a docentes de planta
    // ---------------------------------------------------------------

    public function test_sin_contratacion_no_aplica_evaluacion(): void
    {
        $resultado = $this->servicio->evaluar($this->docente(['contrato' => null]));

        $this->assertFalse($resultado['valido']);
        $this->assertSame('Ninguna', $resultado['categoria_lograda']);
        $this->assertSame('Solo aplica para docentes de planta.', $resultado['razon']);
    }

    public function test_contrato_distinto_de_planta_no_aplica_evaluacion(): void
    {
        $resultado = $this->servicio->evaluar($this->docente([
            'contrato' => $this->contrato('Catedra'),
        ]));

        $this->assertFalse($resultado['valido']);
        $this->assertSame('Ninguna', $resultado['categoria_lograda']);
    }

    public function test_tipo_contrato_es_insensible_a_mayusculas_y_espacios(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'contrato' => $this->contrato('  PLANTA  ', '2015-01-01'),
        ]));

        $this->assertTrue($resultado['valido']);
        $this->assertSame('Titular', $resultado['categoria_lograda']);
    }

    // ---------------------------------------------------------------
    // Categorías
    // ---------------------------------------------------------------

    public function test_cumpliendo_todo_logra_titular(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular());

        $this->assertTrue($resultado['valido']);
        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertSame(60, $resultado['puntaje_total']);
        $this->assertSame([], $resultado['faltantes_por_categoria']);
    }

    public function test_con_doctorado_pero_puntaje_insuficiente_queda_asociado(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'producciones' => [$this->produccion(1)], // 10 puntos, se requieren 60
        ]));

        $this->assertSame('Asociado', $resultado['categoria_lograda']);
        $this->assertSame(10, $resultado['puntaje_total']);

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('puntaje', $campos);
    }

    public function test_con_maestria_y_requisitos_completos_logra_asistente(): void
    {
        $resultado = $this->servicio->evaluar($this->docente([
            'estudios'     => [$this->estudio('Maestría')],
            'idiomas'      => [$this->idioma('B1')],
            'producciones' => [$this->produccion(1), $this->produccion(1)], // 20 puntos
            'evaluacion'   => $this->evaluacion(4.2),
        ]));

        $this->assertSame('Asistente', $resultado['categoria_lograda']);
        $this->assertSame(20, $resultado['puntaje_total']);
    }

    public function test_sin_cumplir_asistente_queda_auxiliar(): void
    {
        $resultado = $this->servicio->evaluar($this->docente([
            'estudios'     => [$this->estudio('Pregrado')],
            'idiomas'      => [$this->idioma('A2')],
            'producciones' => [$this->produccion(3)], // 3 puntos
            'evaluacion'   => $this->evaluacion(4.2),
        ]));

        $this->assertTrue($resultado['valido']);
        $this->assertSame('Auxiliar', $resultado['categoria_lograda']);
        $this->assertArrayHasKey('Asistente', $resultado['faltantes_por_categoria']);
    }

    public function test_estudio_con_documento_no_aprobado_no_cuenta_como_formacion(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'estudios' => [$this->estudio('Doctorado', 'pendiente')],
        ]));

        // Sin doctorado aprobado cae a la rama de Asistente, y ahí tampoco tiene maestría.
        $this->assertSame('Auxiliar', $resultado['categoria_lograda']);
    }

    // ---------------------------------------------------------------
    // Umbral de evaluación docente (lo que HU-2 vuelve configurable)
    // ---------------------------------------------------------------

    public function test_evaluacion_exactamente_en_el_umbral_cumple(): void
    {
        // El servicio compara con >=, por lo que 4.0 exacto aprueba.
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'evaluacion' => $this->evaluacion(4.0),
        ]));

        $this->assertSame('Titular', $resultado['categoria_lograda']);
    }

    public function test_evaluacion_apenas_bajo_el_umbral_no_cumple(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'evaluacion' => $this->evaluacion(3.9),
        ]));

        $this->assertSame('Asociado', $resultado['categoria_lograda']);

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('evaluacion', $campos);
    }

    public function test_sin_evaluacion_asignada_no_cumple_el_requisito(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'evaluacion' => null,
        ]));

        $this->assertSame('Asociado', $resultado['categoria_lograda']);

        $faltantes = collect($resultado['faltantes_por_categoria']['Titular'])
            ->firstWhere('campo', 'evaluacion');

        $this->assertNotNull($faltantes, 'La evaluación ausente debe reportarse como faltante.');
        $this->assertNull($faltantes['actual']);
        $this->assertSame(4.0, $faltantes['requerido']);
    }

    // ---------------------------------------------------------------
    // Puntaje por producción académica
    // ---------------------------------------------------------------

    public function test_puntaje_por_clasificacion_de_ambito(): void
    {
        $casos = [
            1  => 10, // top
            2  => 6,  // a
            3  => 3,  // b
            4  => 0,  // sin clasificar (Revista tipo C)
        ];

        foreach ($casos as $ambitoId => $esperado) {
            $puntaje = $this->servicio->calcularPuntaje($this->docente([
                'producciones' => [$this->produccion($ambitoId)],
            ]));

            $this->assertSame($esperado, $puntaje, "El ámbito {$ambitoId} debe valer {$esperado} puntos.");
        }
    }

    public function test_produccion_con_documento_no_aprobado_no_suma(): void
    {
        $puntaje = $this->servicio->calcularPuntaje($this->docente([
            'producciones' => [$this->produccion(1, 'pendiente'), $this->produccion(1, 'rechazado')],
        ]));

        $this->assertSame(0, $puntaje);
    }

    public function test_puntaje_suma_todas_las_producciones_aprobadas(): void
    {
        $puntaje = $this->servicio->calcularPuntaje($this->docente([
            'producciones' => [
                $this->produccion(1), // 10
                $this->produccion(2), // 6
                $this->produccion(3), // 3
                $this->produccion(1, 'pendiente'), // 0, no aprobada
            ],
        ]));

        $this->assertSame(19, $puntaje);
    }

    public function test_sin_producciones_el_puntaje_es_cero(): void
    {
        $this->assertSame(0, $this->servicio->calcularPuntaje($this->docente()));
    }

    // ---------------------------------------------------------------
    // Nivel de inglés
    // ---------------------------------------------------------------

    public function test_nivel_de_ingles_superior_al_exigido_cumple(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'idiomas' => [$this->idioma('C1')], // se exige B2
        ]));

        $this->assertSame('Titular', $resultado['categoria_lograda']);
    }

    public function test_nivel_de_ingles_inferior_al_exigido_no_cumple(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'idiomas' => [$this->idioma('B1')], // se exige B2
        ]));

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('ingles', $campos);
    }

    public function test_idioma_con_documento_no_aprobado_no_cuenta(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'idiomas' => [$this->idioma('C2', 'pendiente')],
        ]));

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('ingles', $campos);
    }

    // ---------------------------------------------------------------
    // Antigüedad en planta
    // ---------------------------------------------------------------

    public function test_antiguedad_insuficiente_se_reporta_como_faltante(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'contrato' => $this->contrato('Planta', now()->subYears(2)->toDateString()),
        ]));

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('anos', $campos);
    }

    public function test_antiguedad_se_calcula_hasta_la_fecha_fin_si_existe(): void
    {
        // Contrato cerrado de 3 años: no alcanza los 8 exigidos para Titular
        // aunque la fecha de inicio sea antigua.
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'contrato' => $this->contrato('Planta', '2010-01-01', '2013-01-01'),
        ]));

        $faltante = collect($resultado['faltantes_por_categoria']['Titular'])
            ->firstWhere('campo', 'anos');

        $this->assertNotNull($faltante);
        $this->assertSame(3, $faltante['actual']);
    }

    // ---------------------------------------------------------------
    // Producción académica como requisito booleano
    // ---------------------------------------------------------------

    public function test_sin_produccion_aprobada_se_reporta_como_faltante(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'producciones' => [],
        ]));

        $campos = array_column($resultado['faltantes_por_categoria']['Titular'], 'campo');
        $this->assertContains('produccion_academica', $campos);
    }

    // ---------------------------------------------------------------
    // Forma del resultado
    // ---------------------------------------------------------------

    public function test_el_resultado_expone_siempre_las_mismas_claves(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular());

        $this->assertSame(
            ['valido', 'categoria_lograda', 'razon', 'puntaje_total', 'faltantes_por_categoria'],
            array_keys($resultado)
        );
    }

    public function test_cada_faltante_describe_campo_mensaje_requerido_y_actual(): void
    {
        $resultado = $this->servicio->evaluar($this->docenteTitular([
            'evaluacion' => $this->evaluacion(3.0),
        ]));

        foreach ($resultado['faltantes_por_categoria']['Titular'] as $faltante) {
            $this->assertArrayHasKey('campo', $faltante);
            $this->assertArrayHasKey('mensaje', $faltante);
            $this->assertArrayHasKey('requerido', $faltante);
            $this->assertArrayHasKey('actual', $faltante);
        }
    }
}
