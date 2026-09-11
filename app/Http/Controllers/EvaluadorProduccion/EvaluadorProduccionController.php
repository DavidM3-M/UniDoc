<?php

namespace App\Http\Controllers\EvaluadorProduccion;

use App\Constants\ConstDocumentos\EstadoDocumentos;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Usuario\User;
use App\Services\AvalProduccionService;
use App\Services\EnlacesConsultaService;
use App\Services\MotorEscalafonDocenteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Aval de la producción académica de los docentes.
 *
 * Este rol reemplaza a Apoyo Profesoral en una sola tarea: decidir si una producción académica
 * queda aprobada. No es un cambio administrativo. `MotorEscalafonDocenteService::calcularPuntaje()`
 * suma el puntaje del ámbito de divulgación de cada producción que tenga un documento aprobado, así
 * que avalar es otorgar puntos de escalafón, y el escalafón determina la categoría del docente.
 *
 * Tres diferencias con la versión anterior de este controlador, todas deliberadas:
 *
 * 1. **Se ven todas las producciones, no solo las pendientes.** Antes los tres métodos filtraban
 *    `where('estado', 'pendiente')`: apenas el evaluador avalaba algo, desaparecía de su pantalla
 *    y no había forma de auditar ni de corregir una decisión propia.
 *
 * 2. **Se decide por producción, no por documento.** Los endpoints reciben el id de la producción
 *    y `AvalProduccionService` aplica el estado a todos sus documentos en una transacción. El
 *    endpoint anterior recibía un `documento_id` y permitía dejar una producción con un archivo
 *    aprobado y otro rechazado.
 *
 * 3. **Toda decisión queda firmada.** `revisado_por` y `revisado_en` se escriben siempre. Antes se
 *    cambiaba el estado sin registrar quién ni cuándo, ni se notificaba al docente al rechazar.
 */
class EvaluadorProduccionController
{
    public function __construct(
        private readonly AvalProduccionService $avalProduccion,
        private readonly EnlacesConsultaService $enlacesConsulta,
        private readonly MotorEscalafonDocenteService $motorEscalafon,
    ) {
    }

    /**
     * Relaciones que necesita cualquier respuesta de este controlador.
     *
     * Se declaran una vez porque olvidar `documentosProduccionAcademica` convierte `estadoAval()`
     * en una consulta por fila, y con 350 producciones eso son 350 consultas.
     */
    private const RELACIONES = [
        'usuarioProduccionAcademica:id,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,numero_identificacion,email',
        'documentosProduccionAcademica',
        'documentosProduccionAcademica.revisor:id,primer_nombre,primer_apellido',
        'ambitoDivulgacionProduccionAcademica:id_ambito_divulgacion,nombre_ambito_divulgacion,producto_academico_id,puntaje',
        'ambitoDivulgacionProduccionAcademica.productoAcademicoAmbitoDivulgacion:id_producto_academico,nombre_producto_academico',
    ];

    // =================================================================
    // Lectura
    // =================================================================

    /**
     * Contadores de la cabecera de la bandeja.
     *
     * Responden las cuatro preguntas que el evaluador se hace al abrir la pantalla: cuánto tengo
     * por revisar, cuánto llevo hecho este mes, cuánto rechacé y cuántos registros no se pueden
     * verificar. El último es el que dirá, dentro de unos meses, si conviene volver obligatorio
     * el DOI.
     */
    public function resumen()
    {
        try {
            $inicioMes = now()->startOfMonth();

            return response()->json([
                'data' => [
                    'pendientes' => $this->porEstado(ProduccionAcademica::query(), 'pendiente')->count(),

                    'avaladas_mes' => $this->porEstado(ProduccionAcademica::query(), 'aprobado')
                        ->whereHas('documentosProduccionAcademica', fn ($q) => $q->where('revisado_en', '>=', $inicioMes))
                        ->count(),

                    'rechazadas_mes' => $this->porEstado(ProduccionAcademica::query(), 'rechazado')
                        ->whereHas('documentosProduccionAcademica', fn ($q) => $q->where('revisado_en', '>=', $inicioMes))
                        ->count(),

                    'sin_enlace' => $this->soloSinEnlace(ProduccionAcademica::query())->count(),

                    'total' => ProduccionAcademica::count(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@resumen: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al obtener el resumen.'], 500);
        }
    }

    /**
     * Bandeja: todas las producciones del sistema, filtrables y paginadas.
     *
     * Sin parámetro `estado` devuelve todas, incluidas las ya resueltas. Es exactamente lo que la
     * versión anterior no permitía.
     */
    public function obtenerProducciones(Request $request)
    {
        try {
            $request->validate([
                'estado'      => ['nullable', Rule::in(EstadoDocumentos::all())],
                'producto'    => ['nullable', 'integer'],
                'ambito'      => ['nullable', 'integer'],
                'docente'     => ['nullable', 'integer'],
                'desde'       => ['nullable', 'date'],
                'hasta'       => ['nullable', 'date'],
                'sin_enlace'  => ['nullable', 'boolean'],
                'q'           => ['nullable', 'string', 'max:255'],
                'por_pagina'  => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $consulta = ProduccionAcademica::with(self::RELACIONES);

            $this->aplicarFiltros($consulta, $request);

            $pagina = $consulta
                ->orderByDesc('fecha_divulgacion')
                ->orderByDesc('id_produccion_academica')
                ->paginate($request->integer('por_pagina') ?: 25);

            return response()->json([
                'data' => $pagina->getCollection()->map(fn ($p) => $this->fila($p))->values(),
                'meta' => [
                    'pagina_actual' => $pagina->currentPage(),
                    'por_pagina'    => $pagina->perPage(),
                    'total'         => $pagina->total(),
                    'ultima_pagina' => $pagina->lastPage(),
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@obtenerProducciones: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al obtener las producciones académicas.'], 500);
        }
    }

    /**
     * Ficha completa: todo lo que hace falta para decidir sin salir de la pantalla.
     *
     * Además de la producción y sus documentos, devuelve `enlaces_consulta` —los seis sitios donde
     * verificarla— e `impacto_escalafon`, que dice cuántos puntos otorga el aval y cómo queda el
     * docente. Ese último dato es el que convierte el clic en una decisión informada: hasta ahora
     * quien aprobaba no tenía forma de saber qué estaba concediendo.
     */
    public function verProduccion($id)
    {
        try {
            $produccion = ProduccionAcademica::with(self::RELACIONES)->findOrFail($id);

            return response()->json([
                'data' => array_merge($this->fila($produccion), [
                    'documentos'        => $this->documentos($produccion),
                    'enlaces_consulta'  => $this->enlacesConsulta->para($produccion),
                    'impacto_escalafon' => $this->impactoEscalafon($produccion),
                ]),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'La producción académica no existe.'], 404);
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@verProduccion: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al obtener la producción académica.'], 500);
        }
    }

    /**
     * Docentes que tienen al menos una producción registrada, con cuántas hay en cada estado.
     *
     * Es la entrada al expediente. Solo lista a quien tiene producciones: un docente sin ninguna
     * no le da trabajo a este rol y solo alargaría la lista.
     */
    public function docentes()
    {
        try {
            $docentes = User::whereHas('produccionAcademicaUsuario')
                ->with([
                    'produccionAcademicaUsuario:id_produccion_academica,user_id,ambito_divulgacion_id',
                    'produccionAcademicaUsuario.documentosProduccionAcademica:id_documento,documentable_id,documentable_type,estado',
                ])
                ->select(
                    'id',
                    'primer_nombre',
                    'segundo_nombre',
                    'primer_apellido',
                    'segundo_apellido',
                    'numero_identificacion',
                    'email'
                )
                ->orderBy('primer_apellido')
                ->get();

            $filas = $docentes->map(function (User $docente) {
                $estados = $docente->produccionAcademicaUsuario->map(fn ($p) => $p->estadoAval());

                return array_merge($this->docente($docente), [
                    'total'      => $estados->count(),
                    'pendientes' => $estados->filter(fn ($e) => $e === 'pendiente')->count(),
                    'avaladas'   => $estados->filter(fn ($e) => $e === 'aprobado')->count(),
                    'rechazadas' => $estados->filter(fn ($e) => $e === 'rechazado')->count(),
                ]);
            });

            return response()->json(['data' => $filas->values()], 200);
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@docentes: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al obtener los docentes.'], 500);
        }
    }

    /**
     * Expediente del docente: todas sus producciones y el puntaje que suman las avaladas.
     *
     * Sirve para lo que la bandeja no resuelve: ver el peso acumulado de las decisiones sobre esa
     * persona y detectar la misma publicación registrada dos veces con títulos distintos.
     */
    public function produccionesPorDocente($userId)
    {
        try {
            $docente = User::select(
                'id',
                'primer_nombre',
                'segundo_nombre',
                'primer_apellido',
                'segundo_apellido',
                'numero_identificacion',
                'email'
            )->findOrFail($userId);

            $producciones = ProduccionAcademica::with(self::RELACIONES)
                ->where('user_id', $docente->id)
                ->orderByDesc('fecha_divulgacion')
                ->get();

            $filas = $producciones->map(fn ($p) => $this->fila($p));

            return response()->json([
                'data' => [
                    'docente'      => $this->docente($docente),
                    'producciones' => $filas->values(),
                    'resumen'      => [
                        // Puntaje real que está sumando hoy: se pide al mismo motor que calcula el
                        // escalafón, para que la cifra que ve el evaluador no pueda divergir de la
                        // que ve el docente en su hoja de vida.
                        'puntaje_avalado'   => $this->puntajeAvalado($docente),
                        'puntaje_declarado' => $filas->sum('puntaje'),
                        'avaladas'          => $filas->where('estado', 'aprobado')->count(),
                        'pendientes'        => $filas->where('estado', 'pendiente')->count(),
                        'rechazadas'        => $filas->where('estado', 'rechazado')->count(),
                    ],
                    'posibles_duplicados' => $this->posiblesDuplicados($producciones),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'El docente no existe.'], 404);
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@produccionesPorDocente: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al obtener las producciones del docente.'], 500);
        }
    }

    // =================================================================
    // Decisión
    // =================================================================

    /**
     * Avala la producción: aprueba todos sus documentos y firma la decisión.
     */
    public function avalar(Request $request, $id)
    {
        try {
            $produccion = ProduccionAcademica::with('documentosProduccionAcademica')->findOrFail($id);

            if ($produccion->documentosProduccionAcademica->isEmpty()) {
                return response()->json([
                    'message' => 'La producción no tiene documentos de soporte que avalar.',
                ], 422);
            }

            $afectados = $this->avalProduccion->avalar($produccion, $request->user());

            return response()->json([
                'message'             => 'Producción académica avalada correctamente.',
                'documentos_afectados' => $afectados,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'La producción académica no existe.'], 404);
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@avalar: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al avalar la producción académica.'], 500);
        }
    }

    /**
     * Rechaza la producción, o revierte un aval ya otorgado.
     *
     * Es el mismo endpoint para los dos casos porque son la misma escritura: revertir es rechazar
     * algo que estaba aprobado. El motivo es obligatorio en ambos, y no solo en la pantalla: si
     * llegara vacío, el docente recibiría una notificación que no le dice qué corregir.
     */
    public function rechazar(Request $request, $id)
    {
        try {
            $request->validate([
                'motivo' => ['required', 'string', 'max:1000'],
            ]);

            $produccion = ProduccionAcademica::with('documentosProduccionAcademica', 'usuarioProduccionAcademica')
                ->findOrFail($id);

            if ($produccion->documentosProduccionAcademica->isEmpty()) {
                return response()->json([
                    'message' => 'La producción no tiene documentos de soporte que rechazar.',
                ], 422);
            }

            $eraAvalada = $produccion->estadoAval() === 'aprobado';

            $afectados = $this->avalProduccion->rechazar($produccion, $request->user(), $request->input('motivo'));

            return response()->json([
                'message' => $eraAvalada
                    ? 'Aval revertido. El puntaje de escalafón del docente se recalculará.'
                    : 'Producción académica rechazada correctamente.',
                'documentos_afectados' => $afectados,
                'era_avalada'          => $eraAvalada,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'La producción académica no existe.'], 404);
        } catch (\Exception $e) {
            Log::error('EvaluadorProduccionController@rechazar: ' . $e->getMessage(), ['excepcion' => $e]);

            return response()->json(['message' => 'Error al rechazar la producción académica.'], 500);
        }
    }

    // =================================================================
    // Filtros
    // =================================================================

    private function aplicarFiltros(Builder $consulta, Request $request): void
    {
        if ($request->filled('estado')) {
            $this->porEstado($consulta, $request->input('estado'));
        }

        if ($request->filled('ambito')) {
            $consulta->where('ambito_divulgacion_id', $request->integer('ambito'));
        }

        if ($request->filled('docente')) {
            $consulta->where('user_id', $request->integer('docente'));
        }

        if ($request->filled('producto')) {
            $consulta->whereHas(
                'ambitoDivulgacionProduccionAcademica',
                fn ($q) => $q->where('producto_academico_id', $request->integer('producto'))
            );
        }

        if ($request->filled('desde')) {
            $consulta->whereDate('fecha_divulgacion', '>=', $request->date('desde'));
        }

        if ($request->filled('hasta')) {
            $consulta->whereDate('fecha_divulgacion', '<=', $request->date('hasta'));
        }

        if ($request->boolean('sin_enlace')) {
            $this->soloSinEnlace($consulta);
        }

        if ($request->filled('q')) {
            $termino = '%' . $request->input('q') . '%';

            $consulta->where(function ($q) use ($termino) {
                $q->where('titulo', 'ILIKE', $termino)
                    ->orWhere('medio_divulgacion', 'ILIKE', $termino)
                    ->orWhereHas('usuarioProduccionAcademica', function ($u) use ($termino) {
                        $u->where('primer_nombre', 'ILIKE', $termino)
                            ->orWhere('primer_apellido', 'ILIKE', $termino)
                            ->orWhere('segundo_apellido', 'ILIKE', $termino)
                            ->orWhere('numero_identificacion', 'ILIKE', $termino);
                    });
            });
        }
    }

    /**
     * Filtra por el estado de la producción entendida como unidad.
     *
     * Reproduce en SQL la precedencia de `ProduccionAcademica::estadoAval()`: pendiente gana sobre
     * todo, y aprobado gana sobre rechazado. Un `whereHas` a secas no bastaría: una producción con
     * un documento pendiente y otro aprobado aparecería bajo el filtro «avaladas» aunque la ficha
     * la muestre como pendiente, y el evaluador vería dos pantallas contradiciéndose.
     */
    private function porEstado(Builder $consulta, string $estado): Builder
    {
        $tienePendiente = fn ($q) => $q->where('estado', 'pendiente');

        return match ($estado) {
            'pendiente' => $consulta->whereHas('documentosProduccionAcademica', $tienePendiente),

            'aprobado' => $consulta
                ->whereHas('documentosProduccionAcademica', fn ($q) => $q->where('estado', 'aprobado'))
                ->whereDoesntHave('documentosProduccionAcademica', $tienePendiente),

            'rechazado' => $consulta
                ->whereHas('documentosProduccionAcademica', fn ($q) => $q->where('estado', 'rechazado'))
                ->whereDoesntHave('documentosProduccionAcademica', fn ($q) => $q->whereIn('estado', ['pendiente', 'aprobado'])),

            default => $consulta,
        };
    }

    /**
     * Producciones que no se pueden verificar con un enlace directo.
     *
     * Solo cuentan `doi` y `url_publicacion`: el ISSN identifica la revista, no el trabajo, así que
     * con ISSN pero sin DOI ni URL el evaluador sigue sin poder llegar a la publicación concreta.
     */
    private function soloSinEnlace(Builder $consulta): Builder
    {
        return $consulta->where(function ($q) {
            $q->whereNull('doi')->orWhere('doi', '');
        })->where(function ($q) {
            $q->whereNull('url_publicacion')->orWhere('url_publicacion', '');
        });
    }

    // =================================================================
    // Forma de la respuesta
    // =================================================================

    /**
     * Una producción tal como la consumen la bandeja, el expediente y la ficha.
     */
    private function fila(ProduccionAcademica $produccion): array
    {
        $ambito   = $produccion->ambitoDivulgacionProduccionAcademica;
        $decisor  = $produccion->documentoDecisorio();
        $estado   = $produccion->estadoAval();

        return [
            'id_produccion_academica' => $produccion->id_produccion_academica,
            'titulo'                  => $produccion->titulo,
            'medio_divulgacion'       => $produccion->medio_divulgacion,
            'fecha_divulgacion'       => $produccion->fecha_divulgacion,
            'numero_autores'          => $produccion->numero_autores,
            'registrada_en'           => $produccion->created_at,

            'doi'             => $produccion->doi,
            'issn_isbn'       => $produccion->issn_isbn,
            'url_publicacion' => $produccion->url_publicacion,
            // Lo que pinta la columna «Enlaces» de la bandeja sin tener que construir los seis.
            'tiene_enlace_directo' => $this->enlacesConsulta->tieneEnlaceDirecto($produccion),

            'producto_academico' => $ambito?->productoAcademicoAmbitoDivulgacion?->nombre_producto_academico,
            'ambito_divulgacion' => $ambito?->nombre_ambito_divulgacion,
            'puntaje'            => (int) ($ambito?->puntaje ?? 0),

            'estado' => $estado,
            // Estado no pendiente sin revisor: lo decidió Apoyo Profesoral antes del traslado.
            // La interfaz lo muestra como «aval anterior al cambio» en vez de atribuirlo a nadie.
            'es_decision_historica' => (bool) $decisor?->esDecisionHistorica(),
            'motivo_rechazo'        => $decisor?->motivo_rechazo,
            'revisado_en'           => $decisor?->revisado_en,
            'revisado_por'          => $decisor?->revisor
                ? [
                    'id'     => $decisor->revisor->id,
                    'nombre' => trim($decisor->revisor->primer_nombre . ' ' . $decisor->revisor->primer_apellido),
                ]
                : null,

            'docente' => $produccion->usuarioProduccionAcademica
                ? $this->docente($produccion->usuarioProduccionAcademica)
                : null,
        ];
    }

    private function documentos(ProduccionAcademica $produccion): array
    {
        return $produccion->documentosProduccionAcademica->map(fn ($documento) => [
            'id_documento'   => $documento->id_documento,
            'archivo'        => $documento->archivo,
            'archivo_url'    => $documento->archivo ? Storage::url($documento->archivo) : null,
            'estado'         => $documento->estado,
            'motivo_rechazo' => $documento->motivo_rechazo,
            'cargado_en'     => $documento->created_at,
            'revisado_en'    => $documento->revisado_en,
        ])->values()->all();
    }

    private function docente(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre_completo' => trim(preg_replace('/\s+/', ' ', implode(' ', [
                $usuario->primer_nombre,
                $usuario->segundo_nombre,
                $usuario->primer_apellido,
                $usuario->segundo_apellido,
            ]))),
            'numero_identificacion' => $usuario->numero_identificacion,
            'email'                 => $usuario->email,
        ];
    }

    /**
     * Qué le pasa al escalafón del docente si esta producción se avala.
     *
     * Es el dato del aviso que encabeza la ficha. Se calcula con el mismo motor que produce el
     * escalafón real: si acá se sumara a mano, la cifra podría divergir de la que ve el docente.
     */
    private function impactoEscalafon(ProduccionAcademica $produccion): array
    {
        $docente = $produccion->usuarioProduccionAcademica;

        if (!$docente) {
            return ['puntaje_actual' => 0, 'puntaje_si_avala' => 0, 'otorga' => 0];
        }

        $actual = $this->puntajeAvalado($docente);
        $otorga = (int) ($produccion->ambitoDivulgacionProduccionAcademica?->puntaje ?? 0);
        $estado = $produccion->estadoAval();

        return [
            'puntaje_actual' => $actual,
            'otorga'         => $otorga,
            // Si ya está avalada, sus puntos están dentro de `actual`: avalarla otra vez no suma,
            // y lo que interesa mostrar es cuánto se perdería al revertir.
            'puntaje_si_avala'    => $estado === 'aprobado' ? $actual : $actual + $otorga,
            'puntaje_si_revierte' => $estado === 'aprobado' ? $actual - $otorga : $actual,
        ];
    }

    private function puntajeAvalado(User $docente): int
    {
        $docente->loadMissing([
            'produccionAcademicaUsuario',
            'produccionAcademicaUsuario.documentosProduccionAcademica',
        ]);

        return $this->motorEscalafon->calcularPuntaje($docente);
    }

    /**
     * Producciones del mismo docente que probablemente sean la misma publicación registrada dos
     * veces (por ejemplo, la ponencia y el artículo que salió de ella, con títulos casi iguales).
     *
     * Es un aviso, nunca un bloqueo: la decisión sigue siendo del evaluador. Se compara la parte
     * significativa del título normalizada, junto con el año, que es lo que separa una publicación
     * duplicada de una serie legítima del mismo autor sobre el mismo tema.
     *
     * @return array<int, array{titulos: array<int, string>, anio: string}>
     */
    private function posiblesDuplicados($producciones): array
    {
        $grupos = [];

        foreach ($producciones as $produccion) {
            $clave = $this->claveTitulo($produccion->titulo)
                . '|' . substr((string) $produccion->fecha_divulgacion, 0, 4);

            $grupos[$clave][] = $produccion->titulo;
        }

        return collect($grupos)
            ->filter(fn ($titulos) => count($titulos) > 1)
            ->map(fn ($titulos, $clave) => [
                'titulos' => array_values($titulos),
                'anio'    => substr($clave, strrpos($clave, '|') + 1),
            ])
            ->values()
            ->all();
    }

    /**
     * Reduce un título a sus primeras palabras significativas, sin tildes, mayúsculas ni palabras
     * vacías, para que "Modelos de predicción de deserción en programas de ingeniería" y "Modelo
     * predictivo de deserción en ingeniería" no se escapen por una letra de diferencia.
     */
    private function claveTitulo(?string $titulo): string
    {
        $vacias = ['de', 'del', 'la', 'las', 'el', 'los', 'en', 'y', 'un', 'una', 'para', 'con', 'por', 'a', 'al'];

        $normalizado = mb_strtolower((string) $titulo, 'UTF-8');
        $normalizado = strtr($normalizado, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $normalizado = preg_replace('/[^a-z0-9\s]/', ' ', $normalizado) ?? '';

        $palabras = array_values(array_filter(
            preg_split('/\s+/', trim($normalizado)) ?: [],
            fn ($palabra) => $palabra !== '' && !in_array($palabra, $vacias, true)
        ));

        // Solo la raíz de cada palabra: "modelos" y "modelo", "prediccion" y "predictivo"
        // comparten los primeros caracteres, que es lo que hace comparable a los dos títulos.
        $raices = array_map(fn ($palabra) => mb_substr($palabra, 0, 5), array_slice($palabras, 0, 4));

        return implode(' ', $raices);
    }
}
