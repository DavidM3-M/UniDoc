<?php

namespace Tests\Unit;

use App\Models\Aspirante\Documento;
use App\Models\Aspirante\Estudio;
use App\Models\Aspirante\Idioma;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Docente\EvaluacionDocente;
use App\Models\Docente\Puntaje;
use App\Models\TalentoHumano\Contratacion;
use App\Models\Usuario\User;
use App\Services\CalculoPuntajeDocenteService;
use App\Services\EscalafonDocenteService;
use App\Services\UmbralEvaluacionDocenteService;
use Tests\TestCase;

/**
 * Pruebas de la regla de no retroactividad del escalafón docente.
 *
 * La regla acordada es «reglas del momento del otorgamiento»: subir el umbral de
 * evaluación no puede bajarle la categoría a quien ya la tenía, pero perder un
 * requisito real (que le rechacen el doctorado, por ejemplo) sí debe bajarla.
 *
 * Solo se prueba `resolver()`, que no toca base de datos. La persistencia
 * (`evaluarYPersistir`) se ejerce en las pruebas de integración del endpoint.
 */
class EscalafonDocenteServiceTest extends TestCase
{
    /** Construye el servicio con un umbral vigente fijo. */
    private function escalafon(float $umbralVigente): EscalafonDocenteService
    {
        $umbrales = new class($umbralVigente) extends UmbralEvaluacionDocenteService {
            public function __construct(private float $valor)
            {
            }

            public function valorVigente(): float
            {
                return $this->valor;
            }
        };

        return new EscalafonDocenteService(new CalculoPuntajeDocenteService($umbrales));
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

        $idioma = (new Idioma())->forceFill(['nivel' => 'C1']);
        $idioma->setRelation('documentosIdioma', collect([$this->documento()]));

        $user->setRelation('contratacionUsuario', $contrato);
        $user->setRelation('estudiosUsuario', collect([$this->estudio($formacion)]));
        $user->setRelation('idiomasUsuario', collect([$idioma]));
        $user->setRelation('produccionAcademicaUsuario', collect(array_fill(0, 6, null))->map(fn() => $this->produccion(1)));
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
        $resultado = $this->escalafon(4.0)->resolver($this->docente(4.5));

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    // ---------------------------------------------------------------
    // Protección frente a un cambio de umbral
    // ---------------------------------------------------------------

    public function test_subir_el_umbral_no_degrada_una_categoria_ya_otorgada(): void
    {
        // Titular otorgado cuando el umbral era 4.0; su evaluación es 4.2.
        // El umbral sube a 4.5: sin protección pasaría a Asociado.
        $docente = $this->docente(evaluacion: 4.2, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);

        $resultado = $this->escalafon(4.5)->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertTrue($resultado['categoria_protegida']);
        $this->assertSame('Asociado', $resultado['categoria_vigente']);
        $this->assertStringContainsString('Conserva la categoría Titular', $resultado['razon']);
    }

    public function test_la_razon_explica_el_umbral_de_otorgamiento_y_el_vigente(): void
    {
        $docente = $this->docente(evaluacion: 4.2, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);

        $razon = $this->escalafon(4.5)->resolver($docente)['razon'];

        $this->assertStringContainsString('4', $razon);
        $this->assertStringContainsString('4.5', $razon);
        $this->assertStringContainsString('Asociado', $razon);
    }

    // ---------------------------------------------------------------
    // La protección no encubre degradaciones legítimas
    // ---------------------------------------------------------------

    public function test_perder_un_requisito_real_si_degrada_aunque_haya_categoria_otorgada(): void
    {
        // Titular otorgado con umbral 4.0, pero ahora no tiene doctorado aprobado:
        // ni con las reglas de su momento alcanzaría Titular.
        $docente = $this->docente(
            evaluacion: 4.5,
            categoriaOtorgada: 'Titular',
            umbralOtorgado: 4.0,
            formacion: 'Maestría'
        );

        $resultado = $this->escalafon(4.0)->resolver($docente);

        $this->assertSame('Asistente', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    public function test_bajar_la_propia_evaluacion_degrada_aunque_el_umbral_no_cambie(): void
    {
        // Le reasignaron la evaluación a 3.0: no cumple ni con el umbral de su momento.
        $docente = $this->docente(evaluacion: 3.0, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);

        $resultado = $this->escalafon(4.0)->resolver($docente);

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

        $resultado = $this->escalafon(4.0)->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    public function test_bajar_el_umbral_permite_ascender(): void
    {
        $docente = $this->docente(evaluacion: 3.5, categoriaOtorgada: 'Asociado', umbralOtorgado: 4.0);

        $resultado = $this->escalafon(3.0)->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    // ---------------------------------------------------------------
    // Casos borde
    // ---------------------------------------------------------------

    public function test_categoria_otorgada_sin_umbral_registrado_no_protege(): void
    {
        // Registros anteriores a HU-2 no tienen umbral_aplicado: sin ancla no se puede
        // saber bajo qué reglas se otorgó, así que no se protege.
        $docente = $this->docente(evaluacion: 4.2, categoriaOtorgada: 'Titular', umbralOtorgado: null);

        $resultado = $this->escalafon(4.5)->resolver($docente);

        $this->assertSame('Asociado', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }

    public function test_sin_cambios_la_categoria_se_mantiene_sin_marcarse_protegida(): void
    {
        $docente = $this->docente(evaluacion: 4.5, categoriaOtorgada: 'Titular', umbralOtorgado: 4.0);

        $resultado = $this->escalafon(4.0)->resolver($docente);

        $this->assertSame('Titular', $resultado['categoria_lograda']);
        $this->assertFalse($resultado['categoria_protegida']);
    }
}
