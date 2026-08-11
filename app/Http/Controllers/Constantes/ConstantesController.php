<?php

namespace App\Http\Controllers\Constantes;

use App\Constants\ConstAgregarEstudio\TiposEstudio;
use App\Constants\ConstAgregarIdioma\NivelIdioma;
use App\Constants\ConstCertificacionBancaria\TipoCuenta as ConstCertificacionBancariaTipoCuenta;
use App\Constants\ConstEps\EstadoAfiliacion;
use App\Constants\ConstEps\TipoAfiliacion;
use App\Constants\ConstEps\TipoAfiliado;
use App\Constants\ConstInformacionContacto\CategoriaLibretaMilitar;
use App\Constants\ConstRut\CodigoCiiu;
use App\Constants\ConstRut\TipoPersona;
use App\Constants\ConstUsuario\EstadoCivil;
use App\Constants\ConstUsuario\Genero;
use App\Constants\ConstUsuario\TipoIdentificacion;
use App\Constants\ConstCertificacionBancaria\TipoCuenta;
use App\Constants\ConstPension\RegimenPensional;
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
            'codigo_ciiu' => CodigoCiiu::all()
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
    public function obtenerTipoEstudio()
    {
        return response()->json([
            'tipo_estudio' =>TiposEstudio::all()
        ]);
    }

    // Obtener perfiles profesionales para desplegable
    public function obtenerPerfilesProfesionales()
    {
        return response()->json([
            'perfiles_profesionales' => \App\Constants\ConstTalentoHumano\PerfilesProfesionales\PerfilesProfesionales::all()
        ], 200);
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
}


