<?php

namespace Tests\Unit;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Experiencia;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Docente\EvaluacionDocente;
use App\Models\Docente\Puntaje;
use App\Models\EscalonDocente;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\TiposProductoAcademico\ProductoAcademico;
use App\Models\Usuario\User;
use App\Services\EscalafonDocenteService;
use App\Services\MotorEscalafonDocenteService;
use Database\Seeders\EscalonDocenteSeeder;
use Database\Seeders\ReglaExcepcionEscalonSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pruebas de la regla de no retroactividad del escalafón docente.
 *
 * La regla acordada es «reglas del momento del otorgamiento»: que un escalón exija una
 * evaluación más alta no puede bajarle la categoría a quien ya la tenía, pero perder un
 * requisito real (que le rechacen el doctorado, por ejemplo) sí debe bajarla.
 *
 * `MotorEscalafonDocenteService` lee sus reglas de base de datos, así que aquí se siembran los
 * escalones estándar (todos con `evaluacion_minima` = 4.0, ver `EscalonDocenteSeeder`) y se usa
 * `DatabaseTransactions` para no dejar rastro. Para simular que la evaluación mínima de un
 * escalón cambió después de otorgada una categoría, las pruebas actualizan directamente la fila
 * de `EscalonDocente` correspondiente —ya no existe un umbral global inyectable. Solo se prueba
 * `resolver()`; la persistencia (`evaluarYPersistir`) se ejerce en las pruebas de integración
 * del endpoint.
 */
class EscalafonDocenteServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AmbitoDivulgacion $ambitoTop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EscalonDocenteSeeder::class);
        $this->seed(ReglaExcepcionEscalonSeeder::class);

        $producto = ProductoAcademico::create(['nombre_producto_academico' => 'Producto de prueba ' . uniqid()]);
        $this->ambitoTop = AmbitoDivulgacion::create([
            'producto_academico_id' => $producto->id_producto_academico,
            'nombre_ambito_divulgacion' => 'Ámbito de prueba ' . uniqid(),
            'puntaje' => 10,
        ]);
    }

    private function escalafon(): EscalafonDocenteService
    {
        return new EscalafonDocenteService(new MotorEscalafonDocenteService());
    }

    /** Cambia la evaluación mínima que exige un escalón, simulando un ajuste administrativo. */
    private function fijarEvaluacionMinima(string $nombreEscalon, float $valor): void
    {
        EscalonDocente::where('nombre', $nombreEscalon)->update(['evaluacion_minima' => $valor]);
    }

    // ---------------------------------------------------------------
    // Helpers de construcción
    // ---------------------------------------------------------------

    private function documento(string $estado = 'aprobado'): Documento
    {
        return (new Documento())->forceFill(['estado' => $estado]);
    }

    private function estudio(string $tipo, string $estadoDoc = 'aprobado'): Estudio
    {
        $estudio = (new Estudio())->forceFill(['tipo_estudio' => $tipo]);
        $estudio->setRelation('documentosEstudio', collect([$this->documento($estadoDoc)]));

        return $estudio;
    }

    private function produccion(int $ambitoId): ProduccionAcademica
    {
        $produccion = (new ProduccionAcademica())->forceFill(['ambito_divulgacion_id' => $ambitoId]);
        $produccion->setRelation('documentosProduccionAcademica', collect([$this->documento()]));

        return $produccion;
    }

    /** Experiencia Uniautónoma aprobada, generosa en meses para no ser la limitante de estas pruebas. */
    private function experienciaUniautonoma(int $meses = 300): Experiencia
    {
        $experiencia = (new Experiencia())->forceFill([
            'es_uniautonoma' => true,
            'fecha_inicio' => now()->subMonths($meses)->toDateString(),
            'fecha_finalizacion' => null,
        ]);
        $experiencia->setRelation('documentosExperiencia', collect([$this->documento()]));

        return $experiencia;
    }

    /**
     * Docente que cumple todos los requisitos de Titular salvo por el umbral,
     * con la categoría indicada ya otorgada bajo `$umbralOtorgado`.
     */
    private function docente(
        float $evaluacion,
        ?string $categoriaOtorgada = null,
        ?float $umbralOtorgado = null,
        string $formacion = 'Doctorado'
    ): User {
        $user = new User();

        $contrato = (new Contratacion())->forceFill([
            'tipo_contrato' => 'Planta',
            'fecha_inicio'  => '2010-01-01',
            'fecha_fin'     => null,
        ]);

        $idioma = (new Idioma())->forceFill(['idioma' => 'Inglés', 'nivel' => 'C1']);
        $idioma->setRelation('documentosIdioma', collect([$this->documento()]));

        $user->setRelation('contratacionUsuario', $contrato);
        $user->setRelation('estudiosUsuario', collect([$this->estudio($formacion)]));
        $user->setRelation('idiomasUsuario', collect([$idioma]));
        $user->setRelation('produccionAcademicaUsuario', collect(array_fill(0, 6, null))->map(fn() => $this->produccion($this->ambitoTop->id_ambito_divulgacion)));
        $user->setRelation('experienciasUsuario', collect([$this->experienciaUniautonoma()]));
        $user->setRelation('evaluacionDocenteUsuario', (new EvaluacionDocente())->forceFill([
            'promedio_evaluacion_docente' => $evaluacion,
        ]));

        $puntaje = $categoriaOtorgada === null ? null : (new Puntaje())->forceFill([
            'categoria_lograda' => $categoriaOtorgada,
            'umbral_aplicado'   => $umbralOtorgado,
        ]);
        $user->setRelation('puntajeUsuario', $puntaje);

        return $user;
    }

    // ---------------------------------------------------------------
    // Sin categoría previa
    // ---------------------------------------------------------------

    public function test_sin_categoria_previa_devuelve_la_categoria_calculada(): void
    {
        $resultado = $this->escalafon()->resolver($this->docente(4.5));

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    // ---------------------------------------------------------------
    // Protección frente a un cambio en la evaluación mínima del escalón
    // ---------------------------------------------------------------

    public function test_subir_la_evaluacion_minima_no_degrada_una_categoria_ya_otorgada(): void
    {
        // Titular otorgado cuando su escalón exigía evaluación 4.0; la del docente es 4.2.
        // La exigencia de Titular sube a 4.5: sin protección pasaría a Asociado.
        $docente = $this->docente(evaluacion: 4.2, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);
        $this->fijarEvaluacionMinima('Titular', 4.5);

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertTrue($resultado['categoria_protegida']);
        $this->assertSame('Asociado', $resultado['categoria_vigente']);
        $this->assertStringContainsString('Conserva la categoría Titular', $resultado['razon']);
    }

    public function test_la_razon_explica_la_evaluacion_de_otorgamiento_y_la_vigente(): void
    {
        $docente = $this->docente(evaluacion: 4.2, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);
        $this->fijarEvaluacionMinima('Titular', 4.5);

        $razon = $this->escalafon()->resolver($docente)['razon'];

        $this->assertStringContainsString('4', $razon);
        $this->assertStringContainsString('4.5', $razon);
        $this->assertStringContainsString('Asociado', $razon);
    }

    // ---------------------------------------------------------------
    // La protección no encubre degradaciones legítimas
    // ---------------------------------------------------------------

    public function test_perder_un_requisito_real_si_degrada_aunque_haya_categoria_otorgada(): void
    {
        // Titular otorgado con evaluación mínima 4.0, pero ahora no tiene doctorado aprobado:
        // ni con las reglas de su momento alcanzaría Titular.
        $docente = $this->docente(
            evaluacion: 4.5,
            categoriaOtorgada: 'Titular',
            umbralOtorgado: 4.0,
            formacion: 'Maestría'
        );

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Asistente', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    public function test_bajar_la_propia_evaluacion_degrada_aunque_la_evaluacion_minima_no_cambie(): void
    {
        // Le reasignaron la evaluación a 3.0: no cumple ni con la exigencia de su momento.
        $docente = $this->docente(evaluacion: 3.0, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Asociado', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    // ---------------------------------------------------------------
    // Los ascensos siempre se aplican
    // ---------------------------------------------------------------

    public function test_un_ascenso_se_aplica_aunque_exista_categoria_previa(): void
    {
        // Tenía Asociado y ahora cumple Titular: la protección no debe frenarlo.
        $docente = $this->docente(evaluacion: 4.5, categoriaOtorgada: 'Asociado', umbralOtorgado: 4.0);

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    public function test_bajar_la_evaluacion_minima_permite_ascender(): void
    {
        $docente = $this->docente(evaluacion: 3.5, categoriaOtorgada: 'Asociado', umbralOtorgado: 4.0);
        $this->fijarEvaluacionMinima('Titular', 3.0);

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    // ---------------------------------------------------------------
    // Casos borde
    // ---------------------------------------------------------------

    public function test_categoria_otorgada_sin_evaluacion_minima_registrada_no_protege(): void
    {
        // Registros sin umbral_aplicado (el escalón otorgado no exigía evaluación en su
        // momento, o el dato es anterior a este ajuste): sin ancla no se puede saber bajo
        // qué reglas se otorgó, así que no se protege.
        $docente = $this->docente(evaluacion: 4.2, categoriaOtorgada: 'Titular', umbralOtorgado: null);
        $this->fijarEvaluacionMinima('Titular', 4.5);

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Asociado', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    public function test_sin_cambios_la_categoria_se_mantiene_sin_marcarse_protegida(): void
    {
        $docente = $this->docente(evaluacion: 4.5, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);

        $resultado = $this->escalafon()->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }
}
