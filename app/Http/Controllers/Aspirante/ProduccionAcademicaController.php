<?php

namespace App\Http\Controllers\Aspirante;

use Illuminate\Http\Request;
use App\Models\Aspirante\ProduccionAcademica;
use App\Http\Requests\RequestAspirante\RequestProduccionAcademica\ActualizarProduccionAcademicaRequest;
use App\Services\ArchivoService;
use App\Http\Requests\RequestAspirante\RequestProduccionAcademica\CrearProduccionAcademicaRequest;
use Illuminate\Support\Facades\DB;

// Controlador para manejar la producción académica de los aspirantes
// Este controlador permite crear, obtener, actualizar y eliminar producciones académicas
class ProduccionAcademicaController
{
   protected $archivoService;

   /**
    * Constructor del controlador.
    *
    * Inyecta el servicio `ArchivoService`, encargado de gestionar las operaciones relacionadas con archivos 
    * (guardar, actualizar y eliminar) vinculados a las producciones academicas registradas en el sistema.
    *
    * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos asociados a normativas.
    */
   public function __construct(ArchivoService $archivoService)
   {
      $this->archivoService = $archivoService;
   }

   /**
    * Registrar una nueva producción académica para el usuario autenticado.
    *
    * Este método permite crear un nuevo registro de producción académica asociado al usuario que realiza la solicitud.
    * La operación se ejecuta dentro de una transacción para garantizar la integridad de los datos. Si se adjunta un archivo
    * (por ejemplo, un artículo, ponencia, libro u otro producto académico), este se guarda utilizando el servicio `ArchivoService`.
    * En caso de presentarse algún error durante el proceso, se captura la excepción y se retorna una respuesta con el mensaje correspondiente.
    *
    * @param CrearProduccionAcademicaRequest $request Solicitud validada con los datos de la producción y archivo opcional.
    * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
    */
   public function crearProduccion(CrearProduccionAcademicaRequest $request)
   {
      try {

         DB::transaction(function () use ($request) { // Inicia una transacción para asegurar integridad de datos   
            $datos = $request->validated(); // Valida los datos recibidos
            $datos['user_id'] = $request->user()->id; // Asocia la producción académica al usuario autenticado
            $produccionAcademica = ProduccionAcademica::create($datos); // Crea el registro en la base de datos

            if ($request->hasFile('archivo')) { // Si se sube un archivo, lo guarda usando el servicio de archivos
               $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $produccionAcademica, 'ProduccionAcademica');
            }
         });

         return response()->json([ // Respuesta JSON de éxito
            'message' => 'Producción académica y documento guardados correctamente',
         ], 201);
      } catch (\Exception $e) {

         return response()->json([ // Manejo de errores
            'message' => 'Error al crear la producción académica.',
            'error' => $e->getMessage()
         ], 500);
      }
   }

   /**
    * Obtener las producciones académicas del usuario autenticado.
    *
    * Este método consulta todas las producciones académicas registradas por el usuario actual, incluyendo los documentos
    * asociados a cada producción. Para cada archivo encontrado, se genera una URL pública que permite su acceso.
    * Si no existen registros, se retorna una respuesta exitosa con un mensaje informativo y valor nulo.
    * En caso de ocurrir un error durante la consulta, se captura la excepción y se responde con el mensaje de error correspondiente.
    *
    * @param Request $request Solicitud HTTP con el usuario autenticado.
    * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de producciones académicas o mensaje de error.
    */
   public function obtenerProducciones(Request $request)
   {
      try {
         $user = $request->user(); // Obtiene el usuario actual

         $producciones = ProduccionAcademica::where('user_id', $user->id) // Consulta las producciones académicas del usuario con sus documentos
            ->with(['documentosProduccionAcademica:id_documento,documentable_id,archivo,estado'])
            ->orderBy('created_at')
            ->get();

         if ($producciones->isEmpty()) { // Verifica si hay resultados
            return response()->json([
               'mensaje' => 'No se encontraron producciones',
               'producciones' => null
            ], 200);
         }

         $producciones->each(function ($produccion) { // Para cada producción, genera la URL pública del archivo
            $produccion->documentosProduccionAcademica->each(function ($documento) {
               if (!empty($documento->archivo)) {
                  $documento->archivo_url = asset('storage/' . $documento->archivo);
               }
            });
         });

         return response()->json(['producciones' => $producciones], 200); // Devuelve las producciones en una respuesta JSON

      } catch (\Exception $e) {
         return response()->json([ // Manejo de errores
            'message' => 'Error al obtener las producciones académicas.',
            'error' => $e->getMessage()
         ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
      }
   }

   /**
    * Obtener una producción académica específica del usuario autenticado por su ID.
    *
    * Este método busca y devuelve una producción académica registrada por el usuario, identificada por su ID.
    * También carga los documentos asociados a dicha producción y genera una URL pública para cada archivo encontrado.
    * Si la producción no existe o no pertenece al usuario, se lanza una excepción y se retorna un mensaje de error.
    *
    * @param Request $request Solicitud HTTP con el usuario autenticado.
    * @param int $id ID de la producción académica que se desea consultar.
    * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos de la producción o mensaje de error.
    */
   public function obtenerProduccionPorId(Request $request, $id)
   {
      try {
         $user = $request->user(); // Usuario autenticado

         $produccion = ProduccionAcademica::where('id_produccion_academica', $id) // Busca la producción académica del usuario por ID
            ->where('user_id', $user->id)
            ->with(['documentosProduccionAcademica:id_documento,documentable_id,archivo,estado'])
            ->firstOrFail();

         $produccion->documentosProduccionAcademica->each(function ($documento) { // Agrega URL pública del archivo a cada documento
            if (!empty($documento->archivo)) {
               $documento->archivo_url = asset('storage/' . $documento->archivo);
            }
         });

         return response()->json(['produccion' => $produccion], 200); // Devuelve la producción en JSON

      } catch (\Exception $e) {
         return response()->json([ // Error al buscar
            'message' => 'Error al obtener la producción académica.',
            'error' => $e->getMessage()
         ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
      }
   }

   /**
    * Actualizar una producción académica del usuario autenticado.
    *
    * Este método permite modificar los datos de una producción académica existente, siempre y cuando
    * pertenezca al usuario autenticado. La operación se ejecuta dentro de una transacción para asegurar 
    * la integridad de los datos. Si se adjunta un nuevo archivo (como una versión actualizada del documento),
    * este se reemplaza utilizando el servicio `ArchivoService`. En caso de que la producción no se encuentre 
    * o ocurra un error, se captura la excepción y se retorna una respuesta con el mensaje de error.
    *
    * @param ActualizarProduccionAcademicaRequest $request Solicitud validada con los nuevos datos y archivo opcional.
    * @param int $id ID de la producción académica que se desea actualizar.
    * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
    */
   public function actualizarProduccion(ActualizarProduccionAcademicaRequest $request, $id)
   {
      try {

         DB::transaction(function () use ($request, $id) { // Transacción para garantizar integridad
            $user = $request->user(); // Usuario actual

            $produccionAcademica = ProduccionAcademica::where('id_produccion_academica', $id) // Busca la producción a actualizar
               ->where('user_id', $user->id)
               ->firstOrFail();

            $datos = $request->validated(); // Valida los datos recibidos
            $produccionAcademica->update($datos); // Actualiza la producción

            if ($request->hasFile('archivo')) { // Si hay un nuevo archivo, lo actualiza
               $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $produccionAcademica, 'ProduccionAcademica');
            }
         });
         // Respuesta exitosa con datos actualizados
         return response()->json([
            'message' => 'Producción académica actualizada correctamente',
         ], 200);
      } catch (\Exception $e) {
         return response()->json([ // Error al actualizar
            'message' => 'Error al actualizar la producción académica.',
            'error' => $e->getMessage()
         ], 500);
      }
   }

   /**
    * Eliminar una producción académica del usuario autenticado.
    *
    * Este método elimina una producción académica específica registrada por el usuario, identificada por su ID.
    * Antes de eliminar el registro de la base de datos, se elimina también el archivo asociado utilizando el
    * servicio `ArchivoService`. La operación se realiza dentro de una transacción para garantizar que ambos
    * procesos (archivo y base de datos) se completen correctamente. En caso de error o si la producción no se
    * encuentra, se captura una excepción y se retorna una respuesta adecuada.
    *
    * @param Request $request Solicitud HTTP con el usuario autenticado.
    * @param int $id ID de la producción académica que se desea eliminar.
    * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
    */
   public function eliminarProduccion(Request $request, $id)
   {
      try {
         $user = $request->user(); // Usuario autenticado

         $produccionAcademica = ProduccionAcademica::where('id_produccion_academica', $id) // Busca la producción del usuario a eliminar
            ->where('user_id', $user->id)
            ->firstOrFail();

         DB::transaction(function () use ($produccionAcademica) { // Transacción para eliminar el archivo y luego el registro
            $this->archivoService->eliminarArchivoDocumento($produccionAcademica); // Elimina archivo
            $produccionAcademica->delete(); // Elimina el registro de la base de datos
         });

         return response()->json(['message' => 'Producción académica eliminada correctamente'], 200); // Respuesta exitosa

      } catch (\Exception $e) {
         return response()->json([ // Error al eliminar
            'message' => 'Error al eliminar la producción académica.',
            'error' => $e->getMessage()
         ], 500);
      }
   }
}
