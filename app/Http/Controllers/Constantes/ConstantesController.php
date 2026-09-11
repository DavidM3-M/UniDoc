<?php

namespace App\Http\Controllers\Constantes;

use App\Constants\ConstAgregarIdioma\NivelIdioma;
use App\Constants\ConstCertificacionBancaria\TipoCuenta as ConstCertificacionBancariaTipoCuenta;
use App\Constants\ConstEps\EstadoAfiliacion;
use App\Constants\ConstEps\TipoAfiliacion;
use App\Constants\ConstEps\TipoAfiliado;
use App\Constants\ConstInformacionContacto\CategoriaLibretaMilitar;
use App\Models\Rut\CodigoCiiu;
use App\Models\Rut\ResponsabilidadTributaria;
use App\Constants\ConstRut\TipoPersona;
use App\Constants\ConstUsuario\EstadoCivil;
use App\Constants\ConstUsuario\Genero;
use App\Constants\ConstUsuario\TipoIdentificacion;
use App\Constants\ConstCertificacionBancaria\TipoCuenta;
use App\Constants\ConstPension\RegimenPensional;
use App\Models\ExamenIdioma;
use App\Models\Idioma;
use App\Models\NivelFormacionAcademica;
use App\Models\TipoExperiencia;


class ConstantesController
{
    // Constantes Usuario

    // Método para obtener los tipos de documento
    public function obtenerTiposDocumento()
    {
        return response()->json([
            'tipos_documento' =>TipoIdentificacion::all()
        ]);
    }

    // Método para obtener los estados civiles
    public function obtenerEstadoCivil()
    {
        return response()->json([
            'estado_civil' => EstadoCivil::all()
        ]);
    }

    // Metodo para obtener el genero
    public function obtenerGenero()
    {
        return response()->json([
            'genero' => Genero::all()
        ]);
    }

    //contantes Rut

    // Metodo para obtener el tipo de persona
    public function obtenerTipoPersona()
    {
        return response()->json([
            'tipo_persona' => TipoPersona::all()
        ]);
    }

    // Metodo para obtener el codigo ciiu
    public function obtenerCodigoCiiu()
    {
        return response()->json([
            'codigo_ciiu' => CodigoCiiu::orderBy('codigo')->get(['codigo', 'descripcion', 'seccion_titulo'])
        ]);
    }

    // Metodo para obtener las responsabilidades tributarias
    public function obtenerResponsabilidadesTributarias()
    {
        return response()->json([
            'responsabilidades_tributarias' => ResponsabilidadTributaria::orderBy('codigo')->get(['id', 'codigo', 'descripcion'])
        ]);
    }

    // contantes de informacion academica

    //metodo para tipo de libreta militar
    public function obtenerTipoLibretaMilitar()
    {
        return response()->json([
            'tipo_libreta_militar' => CategoriaLibretaMilitar::all()
        ]);
    }

    //Constantes de Eps

    // Estadod e afiliacion
    public function obtenerEstadoAfiliacionEps()
    {
        return response()->json([
            'estado_afiliacion_eps' => EstadoAfiliacion::all()
        ]);
    }

    // Tipo de afiliacion
    public function obtenerTipoAfiliacionEps()
    {
        return response()->json([
            'tipo_afiliacion_eps' => TipoAfiliacion::all()
        ]);
    }

    public function obtenerTipoAfiliadoEps()
    {
        return response()->json([
            'tipo_afiliado_eps' => TipoAfiliado::all()
        ]);
    }

    //const agregar idiomas

    public function obtenerNivelIdioma()
    {
        return response()->json([
            'nivel_idioma' => NivelIdioma::all()
        ]);
    }

    //const agregar experiencia
    // Ya no sale de una constante: el catálogo vive en la tabla `tipo_experiencias` y lo
    // administra el rol Administrador. Se devuelve un array plano de nombres para conservar
    // exactamente el mismo formato de respuesta que cuando era constante.
    public function obtenerTipoExperiencia()
    {
        return response()->json([
            'tipo_experiencia' => TipoExperiencia::activos()
                ->orderBy('nombre_tipo_experiencia')
                ->pluck('nombre_tipo_experiencia')
        ]);
    }

     // constantes de estudio

    // Obtener perfiles profesionales para desplegable
    public function obtenerPerfilesProfesionales()
    {
        return response()->json([
            'perfiles_profesionales' => \App\Constants\ConstTalentoHumano\PerfilesProfesionales\PerfilesProfesionales::all()
        ], 200);
    }

    /**
     * Niveles de formación académica (catálogo administrable), para la cascada Nivel académico
     * → Nivel de formación → Institución → Programa del formulario de Estudio. Reemplaza a la
     * constante fija `TiposEstudio`.
     *
     * Se devuelve `id` + `nombre` + `nivel_academico` + `orden` (no solo `id`+`nombre` como el
     * resto de catálogos de este controlador): con pocas filas, el frontend arma el primer
     * nivel de la cascada (Pregrado/Posgrado/Formación complementaria) agrupando client-side
     * por `nivel_academico`, sin necesidad de un endpoint aparte. `orden` nulo le permite avisar
     * que ese nivel no cuenta para el escalafón.
     */
    public function obtenerNivelFormacionAcademica()
    {
        return response()->json([
            'opciones' => NivelFormacionAcademica::activos()
                ->orderBy('nivel_academico')
                ->orderBy('orden')
                ->orderBy('nivel_formacion')
                ->get(['id_nivel_formacion_academica as id', 'nivel_formacion as nombre', 'nivel_academico', 'orden']),
        ]);
    }

    /**
     * Catálogo de idiomas (`App\Models\Idioma`, no confundir con el registro del
     * aspirante/docente), para el select de "Idioma" en el formulario de certificación.
     */
    public function obtenerIdiomas()
    {
        return response()->json([
            'opciones' => Idioma::activos()
                ->orderBy('nombre_idioma')
                ->get(['id_idioma_catalogo as id', 'nombre_idioma as nombre']),
        ]);
    }

    /**
     * Exámenes de certificación de un idioma del catálogo (IELTS, TOEFL, Cambridge...), para el
     * select en cascada de "Examen / Certificación" que depende del idioma elegido.
     *
     * Devuelve además `vigencia_meses` y los `rangos` de puntaje de cada examen. Son pocos
     * registros y evitan dos viajes extra al servidor: con eso el formulario puede mostrar de
     * inmediato el rango válido, calcular la vista previa del nivel MCER mientras se escribe el
     * puntaje, y avisar cuándo vence el certificado. El valor que se guarda igual lo recalcula
     * el servidor (ver `Aspirante\IdiomaController::resolverCatalogos()`).
     */
    public function obtenerExamenesIdioma(\Illuminate\Http\Request $request)
    {
        $idiomaCatalogoId = $request->query('idioma_catalogo_id');

        $examenes = ExamenIdioma::activos()
            ->when($idiomaCatalogoId, fn ($q) => $q->where('idioma_catalogo_id', $idiomaCatalogoId))
            ->with('rangos:id_rango_examen_idioma,examen_idioma_id,puntaje_min,puntaje_max,nivel_mcer')
            ->orderBy('nombre_examen')
            ->get(['id_examen_idioma', 'nombre_examen', 'vigencia_meses']);

        return response()->json([
            'opciones' => $examenes->map(fn ($examen) => [
                'id' => $examen->id_examen_idioma,
                'nombre' => $examen->nombre_examen,
                'vigencia_meses' => $examen->vigencia_meses,
                'rangos' => $examen->rangos->map(fn ($rango) => [
                    'puntaje_min' => (float) $rango->puntaje_min,
                    'puntaje_max' => (float) $rango->puntaje_max,
                    'nivel_mcer' => $rango->nivel_mcer,
                ])->values(),
            ]),
        ]);
    }

    //constantes cuenta bancaria
    public function obtenerTipoCuenta()
    {
        return response()->json([
            'tipo_cuenta' =>TipoCuenta::all()
        ]);
    }

    //Constantes regimen pensional
    public function obtenerRegimenPensional()
    {
        return response()->json([
            'regimen_pensional' =>RegimenPensional::all()
        ]);
    }

    /**
     * Escalones del escalafón docente con sus requisitos, para mostrárselos al docente en su
     * hoja de vida (`CategoriasEscalafon.tsx`). Misma fuente de verdad que usa
     * `MotorEscalafonDocenteService` para evaluar — nada hardcodeado ni duplicado aquí.
     *
     * Ojo con `meses_minimos_escalon_anterior`: se llamaba `meses_minimos` y eran meses totales en
     * la Universidad. Ahora son meses en el escalón inmediatamente inferior, así que la pantalla
     * tiene que redactarlo como "4 años como Auxiliar", no como "4 años de antigüedad".
     */
    public function obtenerEscalonesDocente()
    {
        $escalones = \App\Models\EscalonDocente::activos()
            ->with('idioma:id_idioma_catalogo,nombre_idioma')
            ->ordenados()
            ->get(['id_escalon', 'nombre', 'orden', 'formacion_minima', 'idioma_catalogo_id', 'nivel_mcer_minimo', 'puntaje_minimo', 'meses_minimos_escalon_anterior', 'evaluacion_minima']);

        return response()->json([
            'escalones_docente' => $escalones,
        ]);
    }

    /**
     * Periodo de ascenso vigente, para que el docente vea contra qué fecha de cierre se está
     * midiendo su expediente.
     *
     * Devuelve null entre un periodo y el siguiente: ahí el docente sigue subiendo documentos con
     * normalidad —nada depende de una ventana de carga abierta—, simplemente no hay ascensos que
     * ejecutar todavía.
     */
    public function obtenerPeriodoAscensoVigente()
    {
        return response()->json([
            'periodo_ascenso' => \App\Models\PeriodoAscenso::vigente()?->only([
                'id_periodo_ascenso', 'nombre', 'fecha_cierre',
            ]),
        ]);
    }
}


