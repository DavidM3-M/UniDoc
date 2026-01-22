<?php
// Se define el espacio de nombres donde se encuentra este controlador, lo que ayuda a organizar el código.
namespace App\Http\Controllers\ApoyoProfesoral;
// Se importan las clases y dependencias necesarias para el funcionamiento del controlador.
use App\Constants\ConstDocumentos\EstadoDocumentos; // Constantes de estados válidos para documentos.
use App\Models\Aspirante\Documento; // Modelo Documento, representa los documentos en la base de datos.
use App\Models\Usuario\User; // Modelo User, representa a los usuarios del sistema.
use Illuminate\Support\Facades\Storage; // Facade para interactuar con el sistema de archivos.
use Illuminate\Http\Request; // Clase para manejar solicitudes HTTP.
use Illuminate\Validation\Rule; // Clase para reglas de validación.
use Illuminate\Support\Facades\DB; // Facade para ejecutar consultas SQL directas.

class VerificacionDocumentosController
{
    /**
     * Relaciones entre categorías de documentos y sus modelos asociados.
     * Este array define cómo se relacionan las diferentes categorías de información del usuario
     * con los modelos de documentos correspondientes. Por ejemplo, 'estudiosUsuario' se relaciona
     * con 'documentosEstudio', lo que permite cargar documentos de estudios de un usuario.
     */
    protected array $relaciones = [
        'estudiosUsuario'            => 'documentosEstudio',
        'experienciasUsuario'        => 'documentosExperiencia',
        'idiomasUsuario'             => 'documentosIdioma',
        'produccionAcademicaUsuario' => 'documentosProduccionAcademica',
        'rutUsuario'                 => 'documentosRut',
        'informacionContactoUsuario' => 'documentosInformacionContacto',
        'epsUsuario'                 => 'documentosEps',
        'usuario'                    => 'documentosUser',
    ];

    /**
     * Mapa de categorías públicas a relaciones internas.
     *
     * @return array
     */
    private function obtenerMapaCategorias(): array
    {
        return [
            'estudios' => ['relacion' => 'estudiosUsuario', 'documentos' => 'documentosEstudio'],
            'experiencias' => ['relacion' => 'experienciasUsuario', 'documentos' => 'documentosExperiencia'],
            'idiomas' => ['relacion' => 'idiomasUsuario', 'documentos' => 'documentosIdioma'],
            'producciones' => ['relacion' => 'produccionAcademicaUsuario', 'documentos' => 'documentosProduccionAcademica'],
            'rut' => ['relacion' => 'rutUsuario', 'documentos' => 'documentosRut'],
            'informacion-contacto' => ['relacion' => 'informacionContactoUsuario', 'documentos' => 'documentosInformacionContacto'],
            'eps' => ['relacion' => 'epsUsuario', 'documentos' => 'documentosEps'],
            'usuario' => ['relacion' => 'usuario', 'documentos' => 'documentosUser'],
        ];
    }

    /**
     * Prepara las relaciones para hacer eager loading filtrando por estado.
     * Devuelve un array de relaciones anidadas con filtros para cargar solo los documentos
     * que tengan el estado especificado.
     *
     * @param string $estado Estado por el cual se filtrarán los documentos.
     * @return array
     */
    private function prepararRelacionesFiltradas($estado)
    {
        $relacionesFiltradas = []; // Inicializa el array de relaciones filtradas.

        foreach ($this->relaciones as $relacionPadre => $relacionDocumentos) { // Itera sobre cada relación definida en el array $relaciones.

            if ($relacionPadre === 'usuario') { // Saltar la relación directa del usuario
                continue;
            }
            $relacionesFiltradas[$relacionPadre . '.' . $relacionDocumentos] = function ($query) use ($estado) { // Para cada relación anidada, define una función que filtra los documentos por estado.
                $query->where('estado', $estado);
            };
        }

        return $relacionesFiltradas; // Devuelve el array de relaciones filtradas.
    }
    /**
     * Aplica filtros por estado a una consulta Eloquent.
     * Permite buscar usuarios que tengan documentos en el estado especificado, ya sea en relaciones directas
     * o anidadas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Consulta Eloquent a modificar.
     * @param string $estado Estado por el cual se filtrarán los documentos.
     */
    private function aplicarFiltrosPorEstado($query, $estado)
    {
        $query->where(function ($subQuery) use ($estado) {
            $esPrimera = true;

            foreach ($this->relaciones as $relacionPadre => $relacionDocumentos) {
                $whereHas = $relacionPadre === 'usuario' ? 'documentosUser' : $relacionPadre . '.' . $relacionDocumentos;

                if ($esPrimera) {
                    $subQuery->whereHas($whereHas, function ($q) use ($estado) {
                        $q->where('estado', $estado);
                    });
                    $esPrimera = false;
                } else {
                    $subQuery->orWhereHas($whereHas, function ($q) use ($estado) {
                        $q->where('estado', $estado);
                    });
                }
            }
        });
    }

    /**
     * Agrega la URL pública de acceso a cada documento de los usuarios recibidos.
     * Esto permite que el frontend pueda acceder y mostrar los archivos de los documentos.
     *
     * @param iterable $usuarios Colección de usuarios a los que se les agregarán las URLs.
     */
    private function agregarUrlADocumentos($usuarios)
    {
        foreach ($usuarios as $usuario) {
            foreach ($this->relaciones as $relacionPadre => $relacionDocumentos) {
                if ($relacionPadre === 'usuario') {
                    $this->agregarUrlADocumentosDirectos($usuario);

                    if (empty($usuario->documentosUser) || $usuario->documentosUser->isEmpty()) {
                        $usuario->unsetRelation('documentosUser');
                    }
                } else {
                    $this->agregarUrlADocumentosRelacionados($usuario, $relacionPadre, $relacionDocumentos);

                    $relacion = $usuario->$relacionPadre ?? null;

                    if (is_iterable($relacion)) {
                        // Si es HasMany: filtrar elementos sin documentos
                        $filtrados = collect($relacion)->filter(function ($item) use ($relacionDocumentos) {
                            return count($item->$relacionDocumentos ?? []) > 0;
                        })->values();

                        $usuario->setRelation($relacionPadre, $filtrados);
                    } elseif (is_object($relacion)) {
                        // Si es HasOne: eliminar si no tiene documentos
                        if (empty($relacion->$relacionDocumentos) || count($relacion->$relacionDocumentos) === 0) {
                            $usuario->unsetRelation($relacionPadre);
                        }
                    }
                }
            }
        }
    }

    /**
     * Agrega la URL pública a los documentos directos del usuario.
     *
     * @param User $usuario Usuario al que se le agregarán las URLs.
     */

    private function agregarUrlADocumentosDirectos($usuario): void
    {
        // Itera sobre cada documento directo del usuario.
        foreach ($usuario->documentosUser ?? [] as $documentoUser) {
            // Genera la URL pública del archivo y la asigna al atributo 'archivo_url'.
            $documentoUser->archivo_url = Storage::url($documentoUser->archivo);
        }
    }

    /**
     * Agrega la URL pública a los documentos de relaciones anidadas del usuario.
     *
     * @param User $usuario Usuario al que se le agregarán las URLs.
     * @param string $relacionPadre Nombre de la relación padre (ej: 'estudiosUsuario').
     * @param string $relacionDocumentos Nombre de la relación de documentos (ej: 'documentosEstudio').
     */

    private function agregarUrlADocumentosRelacionados($usuario, $relacionPadre, $relacionDocumentos): void
    {
        $relacion = $usuario->$relacionPadre ?? null;

        if (is_iterable($relacion)) {
            // Caso HasMany
            foreach ($relacion as $elemento) {
                foreach ($elemento->$relacionDocumentos ?? [] as $documento) {
                    $documento->archivo_url = Storage::url($documento->archivo);
                }
            }
        } elseif (is_object($relacion)) {
            // Caso HasOne
            foreach ($relacion->$relacionDocumentos ?? [] as $documento) {
                $documento->archivo_url = Storage::url($documento->archivo);
            }
        }
    }


    /**
     * Obtiene todos los usuarios con documentos en un estado específico.
     * Carga solo los documentos que estén en el estado solicitado y agrega la URL pública de cada archivo.
     *
     * @param string $estado Estado por el cual se filtrarán los documentos.
     * @return \Illuminate\Http\JsonResponse
     */

    public function obtenerDocumentosPorEstado($estado)
    {
        try {
            $relacionesFiltradas = $this->prepararRelacionesFiltradas($estado); // Prepara las relaciones filtradas para cargar solo los documentos con el estado solicitado.
            // Realiza la consulta a la base de datos:
            // - Filtra usuarios con rol 'Docente'.
            // - Carga las relaciones filtradas (solo documentos con el estado solicitado).
            // - Aplica filtros para asegurar que solo se incluyan usuarios con al menos un documento en ese estado.
            $usuarios = User::role('Docente')
                ->with($relacionesFiltradas)
                ->where(function ($query) use ($estado) {
                    $this->aplicarFiltrosPorEstado($query, $estado);
                })
                ->get();

            $this->agregarUrlADocumentos($usuarios); // Agrega la URL pública de los archivos a cada documento de los usuarios obtenidos.
            // Retorna una respuesta JSON con los usuarios y sus documentos filtrados.
            // Si no se encontraron usuarios, se envía un mensaje informativo.
            return response()->json([
                'data' => $usuarios,
                'message' => $usuarios->isEmpty() ? 'No se encontraron documentos con ese estado.' : ''
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // Si ocurre cualquier excepción, retorna un mensaje de error y el detalle de la excepción.
                'message' => 'Error al obtener documentos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista todos los docentes del sistema.
     * Devuelve información básica de cada docente, como su nombre completo, email y número de identificación.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function listarDocentes()
    {
        try {
            $docentes = User::role('Docente')
                ->select(
                    'id',
                    DB::raw("
                    CONCAT(
                        primer_nombre, ' ',
                        COALESCE(segundo_nombre, ''), ' ',
                        primer_apellido, ' ',
                        COALESCE(segundo_apellido, '')
                    ) AS nombre_completo
                "),
                    'email',
                    'numero_identificacion'
                )
                ->get();

            return response()->json([
                'data' => $docentes,
                'message' => $docentes->isEmpty() ? 'No hay docentes registrados.' : ''
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los docentes.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los documentos de un docente específico.
     * Carga todas las relaciones de documentos asociadas al usuario y agrega la URL pública de cada archivo.
     *
     * @param int $user_id ID del usuario (docente) a consultar.
     * @return \Illuminate\Http\JsonResponse
     */

    public function verDocumentosPorDocente($user_id)
    {
        try {
            // Carga el usuario con todas las relaciones de documentos definidas en $relaciones.
            // Se utiliza array_merge para combinar las relaciones anidadas y la relación directa 'documentosUser'.
            $usuario = User::with(array_merge(
                // Cargar relaciones anidadas
                collect($this->relaciones)
                    ->reject(fn($relacionDocumentos, $relacionPadre) => $relacionPadre === 'usuario')
                    ->mapWithKeys(fn($relacionDocumentos, $relacionPadre) => [
                        $relacionPadre . '.' . $relacionDocumentos => fn($q) => $q,
                    ])
                    ->toArray(),
                // Cargar documentosUser directamente
                ['documentosUser']
            ))->findOrFail($user_id);

            $this->agregarUrlADocumentos([$usuario]); // Agrega la URL pública de los archivos a cada documento del usuario.
            return response()->json([ // Retorna una respuesta JSON con el usuario y sus documentos.
                'usuario' => $usuario,
                'message' => 'Documentos cargados correctamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([    // Si ocurre cualquier excepción, retorna un mensaje de error y el detalle de la excepción.
                'message' => 'Error al obtener los documentos del usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los documentos de un docente filtrados por categoría.
     *
     * @param int $user_id ID del usuario (docente) a consultar.
     * @param string $categoria Categoría de documentos a consultar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function verDocumentosPorCategoria($user_id, $categoria)
    {
        try {
            $mapa = $this->obtenerMapaCategorias();

            if (! array_key_exists($categoria, $mapa)) {
                return response()->json([
                    'message' => 'Categoría no válida.',
                    'categorias_disponibles' => array_keys($mapa),
                ], 422);
            }

            $relacionPadre = $mapa[$categoria]['relacion'];
            $relacionDocumentos = $mapa[$categoria]['documentos'];

            if ($relacionPadre === 'usuario') {
                $usuario = User::with(['documentosUser'])->findOrFail($user_id);
                $documentos = collect($usuario->documentosUser ?? []);
            } else {
                $usuario = User::with([$relacionPadre => function ($q) use ($relacionDocumentos) {
                    $q->with($relacionDocumentos);
                }])->findOrFail($user_id);

                $documentos = collect();
                $relacion = $usuario->$relacionPadre ?? null;

                if (is_iterable($relacion)) {
                    foreach ($relacion as $item) {
                        foreach ($item->$relacionDocumentos ?? [] as $documento) {
                            $documentos->push($documento);
                        }
                    }
                } elseif (is_object($relacion)) {
                    foreach ($relacion->$relacionDocumentos ?? [] as $documento) {
                        $documentos->push($documento);
                    }
                }
            }

            foreach ($documentos as $documento) {
                $documento->archivo_url = Storage::url($documento->archivo);
            }

            return response()->json([
                'categoria' => $categoria,
                'data' => $documentos->values(),
                'message' => $documentos->isEmpty() ? 'No se encontraron documentos en esta categoría.' : '',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los documentos por categoría.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el estado de un documento específico.
     *
     * Este método recibe una solicitud HTTP para actualizar el estado de un documento identificado por su ID.
     * Valida la entrada, realiza la actualización y responde en formato JSON.
     */

    public function actualizarEstadoDocumento(Request $request, $documento_id)
    {
        try {
            // Valida que el campo 'estado' esté presente en la solicitud y que su valor sea uno de los permitidos.
            // Rule::in(EstadoDocumentos::all()) asegura que solo se acepten estados válidos definidos en la constante.
            $request->validate([
                'estado' => ['required', Rule::in(EstadoDocumentos::all())],
            ]);
            // Busca el documento en la base de datos usando el ID proporcionado.
            // Si no se encuentra, lanza una excepción ModelNotFoundException que será capturada por el catch.
            $documento = Documento::findOrFail($documento_id);
            // Asigna el nuevo estado al documento usando el valor recibido en la solicitud.
            $documento->estado = $request->estado;
            // Guarda los cambios realizados en el documento en la base de datos.
            $documento->save();
            // El mensaje informa que el estado del documento fue actualizado correctamente.
            return response()->json([
                'message' => 'Estado del documento actualizado correctamente.',
            ]);
        } catch (\Exception $e) {
            // Si ocurre cualquier excepción durante el proceso (validación, búsqueda o guardado),
            // se captura aquí y se retorna una respuesta JSON con un mensaje de error.
            // También se incluye el mensaje específico de la excepción para facilitar la depuración.
            // El código de estado HTTP es 500, indicando un error interno del servidor.
            return response()->json([
                'message' => 'Error al actualizar el estado del documento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene un documento específico y agrega la URL pública del archivo.
     *
     * @param int $documento_id ID del documento a consultar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function verDocumento($documento_id)
    {
        try {
            $documento = Documento::findOrFail($documento_id);
            $disk = Storage::disk('public');

            if (! $disk->exists($documento->archivo)) {
                return response()->json([
                    'message' => 'Archivo no encontrado.',
                ], 404);
            }

            $path = $disk->path($documento->archivo);
            $mime = $disk->mimeType($documento->archivo) ?? 'application/pdf';

            return response()->file($path, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($documento->archivo) . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el documento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
