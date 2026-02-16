<?php

namespace App\Http\Controllers\Aspirante;

use App\Models\Aspirante\FotoPerfil;
use Illuminate\Http\Request;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;

// Este controlador maneja la creación, eliminación y obtención de fotos de perfil para los usuarios aspirantes.
// Utiliza el servicio ArchivoService para manejar la carga y eliminación de archivos.
class FotoPerfilController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta una instancia del servicio `ArchivoService`, que se utiliza para gestionar
     * las operaciones relacionadas con archivos (guardar, actualizar y eliminar) asociados
     * a las fotos de perfil del usuario.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos adjuntos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Crear una nueva foto de perfil para el usuario autenticado.
     *
     * Este método permite registrar una única foto de perfil por usuario. Valida que el archivo cargado
     * sea una imagen válida (JPEG, PNG, JPG) y que no exceda el tamaño permitido. La operación se realiza
     * dentro de una transacción para asegurar la consistencia. Si el usuario ya tiene una foto de perfil,
     * se lanza una excepción con código 409 (conflicto). Si se adjunta una imagen, esta se guarda utilizando
     * el servicio `ArchivoService`. Se retorna un mensaje de éxito o un error en caso de fallo.
     *
     * @param Request $request Solicitud HTTP con el archivo adjunto y el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearFotoPerfil(Request $request)
    {
        try {
            $request->validate([
                'archivo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Valida que se haya enviado un archivo tipo imagen

            ]);

            DB::transaction(function () use ($request) { // Ejecuta  dentro de una transaccion para garantizar consistencia
                $userId = $request->user()->id;

                $existeFoto = FotoPerfil::where('user_id', $userId)->exists(); // Verificar si ya existe una foto de perfil para el usuario
                if ($existeFoto) {
                    throw new \Exception('Ya existe una foto de perfil para este usuario.', 409); // Lanza una excepción si ya hay una foto
                }

                $fotoPerfil = FotoPerfil::create(['user_id' => $userId,]); // Crea un nuevo registro de foto de perfil en la base de datos

                if ($request->hasFile('archivo')) { // Si se adjuntó un archivo, lo guarda usando el servicio
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $fotoPerfil, 'FotoPerfil');
                }
            });

            return response()->json([ // Se retorna una respuesta exitosa con los datos de la experiencia creada.
                'message' => 'Foto perfil creada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // Si ocurre un error, devuelve un mensaje y el error correspondiente
                'mensaje' => 'Error al crear la foto de perfil.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Eliminar la foto de perfil del usuario autenticado.
     *
     * Este método busca y elimina la foto de perfil asociada al usuario actual. Si no existe una foto registrada,
     * retorna un mensaje informativo con estado 200 y valor nulo. En caso de existir, elimina tanto el archivo físico
     * como el registro en la base de datos utilizando el servicio `ArchivoService`. Si ocurre un error durante el
     * proceso, se captura la excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarFotoPerfil(Request $request)
    {
        try {
            $fotoPerfil = FotoPerfil::where('user_id', $request->user()->id)->first();            // Busca la foto de perfil del usuario autenticado

            if (!$fotoPerfil) { // Si no existe una foto, retorna error 404
                return response()->json([
                    'mensaje' => 'No se encontró una foto de perfil para este usuario.',
                    'fotoPerfil' => null
                ], 200);
            }

            $this->archivoService->eliminarArchivoDocumento($fotoPerfil); // Usar el servicio para eliminar el documento y el archivo
            $fotoPerfil->delete(); // Elimina el registro de la base de datos

            return response()->json(['mensaje' => 'Foto de perfil eliminada correctamente.'], 200); // Retorna respuesta de éxito

        } catch (\Exception $e) {
            return response()->json([ // En caso de error, retorna mensaje con código
                'mensaje' => 'Error al eliminar la foto de perfil.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener la foto de perfil del usuario autenticado.
     *
     * Este método recupera la foto de perfil registrada por el usuario, junto con los documentos
     * asociados a dicha foto. Si el usuario no tiene una foto registrada, se retorna una respuesta
     * exitosa con valor nulo. Para cada documento encontrado, se genera una URL accesible al archivo
     * almacenado. En caso de que el usuario no esté autenticado o se presente un error durante la consulta,
     * se captura la excepción y se retorna un mensaje con el error correspondiente.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la foto de perfil o mensaje de error.
     */
    public function obtenerFotoPerfil(Request $request)
    {
        try {

            $user = $request->user(); // Obtiene el usuario autenticado

            if (!$user) {
                throw new \Exception('Usuario no autenticado', 401);
            }

            $fotoPerfil = FotoPerfil::where('user_id', $user->id) // Busca la foto de perfil y carga los documentos asociados
                ->with(['documentosFotoPerfil:id_documento,documentable_id,archivo'])
                ->first();

            if (!$fotoPerfil) {
                return response()->json([ // Si no se encuentra ninguna foto, lanza excepción
                    'mensaje' => 'No tienes foto de perfil registrada aún.',
                    'fotoPerfil' => null
                ], 200); // No es error, simplemente no tiene foto de perfil aún
            }

            foreach ($fotoPerfil->documentosFotoPerfil as $documento) { // Agrega la URL completa del archivo para cada documento asociado
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }

            return response()->json(['fotoPerfil' => $fotoPerfil], 200); // Devuelve la foto de perfil con los documentos adjuntos

        } catch (\Exception $e) {
            return response()->json([ // Devuelve mensaje de error en caso de falla
                'message' => 'Error al obtener la foto de perfil',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }
}
