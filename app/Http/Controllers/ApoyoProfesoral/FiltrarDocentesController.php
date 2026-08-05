<?php
// Declaración del namespace donde se encuentra este controlador, 
// lo que ayuda a organizar el código y evitar conflictos de nombres.

namespace App\Http\Controllers\ApoyoProfesoral;
// Importa el modelo User, que representa a los usuarios en la base de datos.
// Este modelo se usará para consultar y filtrar docentes.

use App\Models\Usuario\User;
// Importa la fachada Log de Laravel, que permite registrar mensajes en los logs del sistema.
// Se utiliza para registrar errores y facilitar la depuración.

use Illuminate\Support\Facades\Log;
// Definición de la clase FiltrarDocentesController, que contiene métodos para filtrar y mostrar información de docentes.

use Illuminate\Support\Facades\Storage;

class FiltrarDocentesController
{
    /**
     * Muestra todos los estudios de los docentes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function mostrarTodosLosEstudios()
    {
        try {
            // Consulta todos los usuarios que tengan el rol 'Docente'.
            // whereHas verifica que el usuario tenga al menos un rol con el nombre 'Docente'.
            $usuarios = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                // Eager loading de la relación 'estudiosUsuario' para traer los estudios de cada docente.
                // Esto evita el problema N+1 y mejora el rendimiento.
                ->with('estudiosUsuario') // Sin filtros
                // Ejecuta la consulta y obtiene todos los resultados.
                ->get();
            // Devuelve una respuesta JSON con el estado 'success' y los datos de los usuarios encontrados.
            return response()->json([
                'status' => 'success',
                'data' => $usuarios
            ], 200);
        } catch (\Exception $e) {
            // Si ocurre una excepción, se registra el error en los logs con el mensaje correspondiente.
            Log::error('Error al obtener los estudios: ' . $e->getMessage());
            // Devuelve una respuesta JSON con estado 'error', un mensaje y el error específico.
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los estudios.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Muestra los estudios de un docente específico.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerEstudiosPorDocente($id)
    {
        try {
            // Busca al usuario con rol Docente y carga los estudios con sus documentos
            $usuario = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente'); // Asegúrate que el nombre del rol está bien escrito
            })
                ->with('estudiosUsuario.documentosEstudio') // Carga documentos asociados a los estudios
                ->findOrFail($id);
            $estudios = $usuario->estudiosUsuario->map(function ($estudio) {
                $estudio->documentosEstudio->map(function ($documento) {
                    $documento->archivo_url = url(Storage::url($documento->archivo)); // usa el campo 'archivo'
                    return $documento;
                });
                return $estudio;
            });
            return response()->json([
                'status' => 'success',
                'data' => $estudios // Aquí ya vendrán los documentos también
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener estudios del docente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudieron obtener los estudios del docente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Filtra los docentes por tipo de estudio.
     *
     * @param string $tipo
     * @return \Illuminate\Http\JsonResponse
     */
    public function filtrarPorTipoEstudio($tipo)
    {
        try {
            // Filtrar usuarios con rol docente
            $usuarios = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                // Eager loading de la relación 'estudiosUsuario', pero solo los estudios que coincidan con el tipo dado.
                ->with(['estudiosUsuario' => function ($query) use ($tipo) {
                    $query->where('tipo_estudio', $tipo);
                }])
                ->get();

            // Solo los que tienen estudios de ese tipo
            $usuariosConEstudios = $usuarios->filter(function ($usuario) {
                // isNotEmpty() verifica que la colección de estudios no esté vacía.
                return $usuario->estudiosUsuario->isNotEmpty();
            })->values();
            // Devuelve los usuarios filtrados.
            return response()->json([
                'status' => 'success',
                'data' => $usuariosConEstudios
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al filtrar estudios: ' . $e->getMessage());

            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al filtrar los estudios.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Muestra todos los idiomas de los docentes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function mostrarTodosLosIdiomas()
    {
        try {
            // Busca todos los usuarios con rol 'Docente' y carga sus idiomas.
            $usuarios = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                ->with('idiomasUsuario') // Sin filtros
                ->get();
            // Devuelve los usuarios con sus idiomas.
            return response()->json([
                'status' => 'success',
                'data' => $usuarios
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al obtener los idiomas: ' . $e->getMessage());
            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los idiomas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filtra los docentes por idioma.
     *
     * @param string $idioma
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerIdiomasPorDocente($id)
    {
        try {
            // Busca el usuario con rol 'Docente' y carga sus idiomas.
            $usuario = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                ->with('idiomasUsuario.documentosIdioma') // Carga documentos asociados a los idiomas
                ->findOrFail($id);
            $idiomas = $usuario->idiomasUsuario->map(function ($idioma) {
                $idioma->documentosIdioma->map(function ($documento) {
                    $documento->archivo_url = url(Storage::url($documento->archivo)); // usa el campo 'archivo'
                    return $documento;
                });
                return $idioma;
            });
            return response()->json([
                'status' => 'success',
                'data' => $idiomas
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al obtener idiomas del docente: ' . $e->getMessage());
            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudieron obtener los idiomas del docente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Filtra los docentes por nivel de idioma.
     *
     * @param string $idioma El nivel de idioma a filtrar (por ejemplo, 'Básico', 'Avanzado').
     * @return \Illuminate\Http\JsonResponse
     */
    public function filtrarPorNivelIdioma($idioma)
    {
        try {
            // Filtrar usuarios con rol docente
            $usuarios = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                ->with(['idiomasUsuario' => function ($query) use ($idioma) {
                    $query->where('nivel', $idioma);
                }])
                ->get();

            // Solo los que tienen ese idioma
            $usuariosConIdioma = $usuarios->filter(function ($usuario) {
                return $usuario->idiomasUsuario->isNotEmpty();
            })->values();
            // Devuelve los usuarios filtrados.
            return response()->json([
                'status' => 'success',
                'data' => $usuariosConIdioma
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al filtrar idiomas: ' . $e->getMessage());
            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al filtrar los idiomas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Muestra todas las experiencias de los docentes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerTodasLasExperiencias()
    {
        try {
            // Filtrar usuarios con rol Docente y traer todas sus experiencias
            $usuarios = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                ->with('experienciasUsuario')
                ->get();

            // Solo los que tienen experiencias
            $usuariosConExperiencias = $usuarios->filter(function ($usuario) {
                return $usuario->experienciasUsuario->isNotEmpty();
            })->values();
            // Devuelve los usuarios filtrados.
            return response()->json([
                'status' => 'success',
                'data' => $usuariosConExperiencias
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al obtener experiencias: ' . $e->getMessage());
            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener las experiencias.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Filtra los docentes por tipo de experiencia.
     *
     * @param string $tipo El tipo de experiencia a filtrar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function filtrarPorTipoExperiencia($tipo)
    {
        try {
            // Filtrar usuarios con rol docente
            $usuarios = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                ->with(['experienciasUsuario' => function ($query) use ($tipo) {
                    $query->where('tipo_experiencia', $tipo);
                }])
                ->get();

            // Solo los que tienen experiencias del tipo indicado
            $usuariosConExperiencias = $usuarios->filter(function ($usuario) {
                return $usuario->experienciasUsuario->isNotEmpty();
            })->values();
            // Devuelve los usuarios filtrados.
            return response()->json([
                'status' => 'success',
                'data' => $usuariosConExperiencias
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al filtrar experiencias: ' . $e->getMessage());
            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al filtrar las experiencias.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Obtiene las experiencias de un docente específico.
     *
     * @param int $id El ID del docente.
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerExperienciasPorDocente($id)
    {
        try {
            // Busca el usuario con rol 'Docente' y carga sus experiencias.
            $usuario = User::whereHas('roles', function ($query) {
                $query->where('name', 'Docente');
            })
                ->with('experienciasUsuario.documentosExperiencia') // Carga documentos asociados a las experiencias
                ->findOrFail($id);
            $experiencias = $usuario->experienciasUsuario->map(function ($experiencia) {
                $experiencia->documentosExperiencia->map(function ($documento) {
                    $documento->archivo_url = url(Storage::url($documento->archivo)); // usa el campo 'archivo'
                    return $documento;
                });
                return $experiencia;
            });
            return response()->json([
                'status' => 'success',
                'data' => $experiencias
            ], 200);
        } catch (\Exception $e) {
            // Registra el error.
            Log::error('Error al obtener experiencias del docente: ' . $e->getMessage());
            // Devuelve respuesta de error.
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudieron obtener las experiencias del docente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Muestra toda la producción académica de los docentes.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function mostrarTodaLaProduccionAcademica()
    {
        try {
            // Busca usuarios con rol 'Docente' y carga toda su producción académica.
            $usuarios = User::whereHas('roles', function ($query) {
                // Esta función anónima se utiliza para modificar la consulta de la relación 'roles'.
                // El método whereHas permite filtrar los usuarios que tengan al menos un rol que cumpla la condición.
                // En este caso, la condición es que el nombre del rol ('name') sea exactamente 'Docente'.
                // Esto asegura que solo se seleccionen los usuarios que son docentes.

                $query->where('name', 'Docente');
            })
                // El método with permite realizar eager loading de relaciones.
                // Aquí se indica que, además de los datos del usuario, se debe cargar la relación
                // 'produccionAcademicaUsuario', que representa toda la producción académica asociada a ese usuario.
                // No se aplican filtros adicionales, por lo que se traerán todos los registros de producción académica
                // de cada docente
                ->with('produccionAcademicaUsuario') // Sin filtros
                // El método get ejecuta la consulta y obtiene todos los resultados como una colección de objetos User.
                // Cada objeto User incluirá, en su propiedad 'produccionAcademicaUsuario', una colección con todos los
                // registros de producción académica relacionados.
                ->get();
            // Una vez obtenidos los usuarios y su producción académica, se prepara la respuesta.
            // Se utiliza el helper response()->json() para devolver una respuesta HTTP en formato JSON.
            // El primer parámetro es un array asociativo con dos claves:
            //  - 'status' => 'success': indica que la operación fue exitosa.
            //  - 'data' => $usuarios: contiene la colección de usuarios con su producción académica.
            // El segundo parámetro es el código de estado HTTP, en este caso 200 (OK).
            return response()->json([
                'status' => 'success',
                'data' => $usuarios
            ], 200);
        } catch (\Exception $e) {
            // Si ocurre cualquier excepción durante la ejecución del bloque try, se captura aquí.
            // Se utiliza la fachada Log de Laravel para registrar el error en los logs del sistema.
            // El mensaje incluye un texto descriptivo y el mensaje específico de la excepción.
            Log::error('Error al obtener la producción académica: ' . $e->getMessage());
            // Se devuelve una respuesta JSON indicando que ocurrió un error.
            // El array incluye:
            //  - 'status' => 'error': indica que la operación falló.
            //  - 'message': un mensaje genérico para el usuario.
            //  - 'error': el mensaje específico de la excepción, útil para depuración.
            // El código de estado HTTP es 500 (Internal Server Error).
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener la producción académica.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function obtenerProduccionAcademicaPorDocente($id)
    {
        try {
            // Inicia una consulta sobre el modelo User para buscar un usuario específico.
            $usuario = User::whereHas('roles', function ($query) {
                // Esta función anónima se utiliza para modificar la consulta de la relación 'roles'.
                // El método whereHas filtra los usuarios que tengan al menos un rol que cumpla la condición.
                // En este caso, la condición es que el nombre del rol ('name') sea exactamente 'Docente'.
                // Así, solo se seleccionan los usuarios que son docentes.
                $query->where('name', 'Docente');
            })
                // El método with permite realizar eager loading de relaciones.
                // Aquí se indica que, además de los datos del usuario, se debe cargar la relación
                // 'produccionAcademicaUsuario', que representa toda la producción académica asociada a ese usuario.
                // No se aplican filtros adicionales, por lo que se traerán todos los registros de producción académica
                // de ese docente.
                ->with('produccionAcademicaUsuario.documentosProduccionAcademica') // Carga documentos asociados a la producción académica
                // El método findOrFail busca el usuario por su ID.
                // Si el usuario no existe, lanza una excepción ModelNotFoundException.
                ->findOrFail($id);
            $produccionAcademica = $usuario->produccionAcademicaUsuario->map(function ($produccion) {
                $produccion->documentosProduccionAcademica->map(function ($documento) {
                    $documento->archivo_url = url(Storage::url($documento->archivo));// usa el campo 'archivo'
                    return $documento;
                });
                return $produccion;
            });
            return response()->json([
                'status' => 'success',
                'data' => $produccionAcademica
            ], 200);
        } catch (\Exception $e) {
            // Si ocurre cualquier excepción durante la ejecución del bloque try, se captura aquí.
            // Se utiliza la fachada Log de Laravel para registrar el error en los logs del sistema.
            // El mensaje incluye un texto descriptivo y el mensaje específico de la excepción.
            Log::error('Error al obtener la producción académica del docente: ' . $e->getMessage());
            // Se devuelve una respuesta JSON indicando que ocurrió un error.
            // El array incluye:
            //  - 'status' => 'error': indica que la operación falló.
            //  - 'message': un mensaje genérico para el usuario.
            //  - 'error': el mensaje específico de la excepción, útil para depuración.
            // El código de estado HTTP es 500 (Internal Server Error).
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener la producción académica del docente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Filtra la producción académica de los docentes por ámbito de divulgación.
     *
     * Este método permite obtener una lista de docentes que tienen producción académica
     * asociada a un ámbito de divulgación específico. Explicación línea por línea:
     */
    public function filtrarPorAmbitoDivulgacion($ambitoId)
    {
        try {
            // Inicia una consulta sobre el modelo User para buscar usuarios con el rol 'Docente'.
            $usuarios = User::whereHas('roles', function ($query) {
                // Esta función anónima modifica la consulta de la relación 'roles'.
                // El método whereHas filtra los usuarios que tengan al menos un rol que cumpla la condición.
                // En este caso, la condición es que el nombre del rol ('name') sea exactamente 'Docente'.
                $query->where('name', 'Docente');
            })
                // El método with permite realizar eager loading de relaciones.
                // Aquí se indica que, además de los datos del usuario, se debe cargar la relación
                // 'produccionAcademicaUsuario', pero solo aquellos registros donde el campo
                // 'ambito_divulgacion_id' coincida con el parámetro recibido ($ambitoId).
                ->with(['produccionAcademicaUsuario' => function ($query) use ($ambitoId) {
                    $query->where('ambito_divulgacion_id', $ambitoId);
                }])
                // Ejecuta la consulta y obtiene todos los resultados como una colección de objetos User.
                ->get();
            // Filtra los usuarios para quedarse solo con aquellos que realmente tienen producción académica
            // en el ámbito solicitado (es decir, que la colección no esté vacía).
            $usuariosConProduccion = $usuarios->filter(function ($usuario) {
                // isNotEmpty() verifica que la colección de producción académica no esté vacía.
                return $usuario->produccionAcademicaUsuario->isNotEmpty();
            })->values();
            // Retorna una respuesta JSON con los docentes filtrados y su producción académica correspondiente.
            // El array incluye:
            //  - 'status' => 'success': indica que la operación fue exitosa.
            //  - 'data' => $usuariosConProduccion: contiene la colección de usuarios filtrados.
            // El segundo parámetro es el código de estado HTTP, en este caso 200 (OK).
            return response()->json([
                'status' => 'success',
                'data' => $usuariosConProduccion
            ], 200);
        } catch (\Exception $e) {
            // Si ocurre cualquier excepción durante la ejecución del bloque try, se captura aquí.
            // Se utiliza la fachada Log de Laravel para registrar el error en los logs del sistema.
            // El mensaje incluye un texto descriptivo y el mensaje específico de la excepción.
            Log::error('Error al filtrar la producción académica por ámbito: ' . $e->getMessage());
            // Se devuelve una respuesta JSON indicando que ocurrió un error.
            // El array incluye:
            //  - 'status' => 'error': indica que la operación falló.
            //  - 'message': un mensaje genérico para el usuario.
            //  - 'error': el mensaje específico de la excepción, útil para depuración.
            // El código de estado HTTP es 500 (Internal Server Error).
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al filtrar la producción académica.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
