<?php

namespace App\Http\Controllers\Auth;


use Illuminate\Http\Request;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RequestAuth\ActualizarAuthRequest;
use App\Http\Requests\RequestAuth\CrearAuthRequest;
use App\Services\ArchivoService;
use Carbon\Carbon;

// Este controlador maneja la autenticación de usuarios, incluyendo el registro, inicio de sesión,
// actualización de datos, cierre de sesión y restablecimiento de contraseña.
class AuthController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio `ArchivoService`, utilizado para gestionar las operaciones relacionadas
     * con archivos (guardar, actualizar y eliminar) vinculados al Auth del usuario.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos asociados al RUT.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Registrar un nuevo usuario en el sistema.
     *
     * Este método permite crear un nuevo usuario, asignarle el rol de "Aspirante" y generar un token JWT
     * para su autenticación inmediata. La creación del usuario se realiza dentro de una transacción para
     * asegurar la integridad de los datos. Si ocurre algún error durante el proceso, se captura la excepción
     * y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param CrearAuthRequest $request Solicitud validada con los datos del nuevo usuario.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito, token generado o mensaje de error.
     */
    public function registrar(CrearAuthRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {

                $datosUser = $request->validated(); //Validar los datos de entrada
                $user = User::create($datosUser); // crear un usuario
                $user->assignRole('Aspirante'); // Asignar el rol "aspirante" al usuario recién creado

                return $user; // Retornar el usuario creado
            });

            $token = JWTAuth::fromUser($user); //Generar un token para el usuario

            return response()->json([ //Devolver respuesat con el token y el usuario
                'menssage' => 'Usuario creado exitosamente',
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el usuario',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => collect($e->getTrace())->take(5), // Opcional: muestra solo los primeros 5 pasos del stack
            ], 500);
        }
    }

    /**
     * Iniciar sesión de usuario y generar token JWT.
     *
     * Este método valida las credenciales proporcionadas (correo y contraseña) y, si son correctas,
     * autentica al usuario, genera un token JWT y retorna información básica del usuario autenticado,
     * incluyendo su rol principal. Si la validación falla o las credenciales son incorrectas, se lanza
     * una excepción con el código de error correspondiente.
     *
     * @param Request $request Solicitud HTTP con las credenciales del usuario.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con token de acceso, rol del usuario o mensaje de error.
     */
    public function iniciarSesion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [ // Validar los datos de entrada
                'email'    => 'required|string|email',
                'password' => 'required|string|min:8',
            ]);


            if ($validator->fails()) { // Si la validación falla, devolver un mensaje de error
                throw new \Exception($validator->errors()->toJson(), 400);
            }

            $credentials = $request->only('email', 'password'); // Credenciales para autenticar

            if (!$token = JWTAuth::attempt($credentials)) { // Intentar autenticar y generar un token
                throw new \Exception('Credenciales incorrectas', 401);
            }

            return response()->json([ // Devolver respuesta con el token y el usuario
                'message' => 'Inicio de sesión exitoso',
                'token'   => $token,
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al iniciar sesión',
                'error'   => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Actualizar la información del usuario autenticado.
     *
     * Este método permite modificar los datos del usuario actualmente autenticado. La operación se realiza
     * dentro de una transacción para asegurar la integridad de la información. Si se adjunta un archivo
     * (como un documento de identificación), este se actualiza o se crea usando el servicio `ArchivoService`.
     * Después de la actualización, se retorna el usuario con los datos frescos desde la base de datos,
     * incluyendo las URLs públicas de los documentos relacionados si existen.
     * En caso de error durante el proceso, se captura la excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @param ActualizarAuthRequest $request Solicitud validada con los datos actualizados del usuario y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario actualizado o mensaje de error.
     */
    public function actualizarUsuario(ActualizarAuthRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {

                $user = JWTAuth::user(); // Obtener el usuario autenticado
                $datosAuth = $request->validated(); // Validar los datos de entrada

                $user->update($datosAuth); // Actualizar los datos del usuario


                if ($request->hasFile('archivo')) { // Manejo del archivo (actualizar o crear documento de identificación)
                    $this->archivoService->actualizarArchivoDocumento(
                        $request->file('archivo'),
                        $user,
                        'Identificacion' // Carpeta para documentos de usuarios
                    );
                }

                return $user->fresh(); // Retornar el usuario actualizado
            });


            if ($user->documentosUser) { // Agregar la URL del archivo si existe
                foreach ($user->documentosUser as $documento) {
                    if (!empty($documento->archivo)) {
                        $documento->archivo_url = asset('storage/' . $documento->archivo);
                    }
                }
            }
            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user'    => $user,
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al actualizar el usuario',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cerrar sesión del usuario autenticado.
     *
     * Este método invalida el token JWT activo del usuario, cerrando efectivamente su sesión en el sistema.
     * Si el token es inválido o ocurre algún error durante el proceso, se captura una excepción y se retorna
     * una respuesta con el mensaje de error correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON indicando el éxito o error del cierre de sesión.
     */
    public function cerrarSesion()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken()); // Invalidar el token actual

            return response()->json(['message' => 'Sesión cerrada exitosamente'], 200); // Devolver respuesta

        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al cerrar sesión',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener los datos del usuario autenticado.
     *
     * Este método retorna la información del usuario actualmente autenticado mediante JWT, incluyendo
     * sus documentos asociados. Para cada documento, si existe un archivo, se genera una URL pública
     * para facilitar su visualización o descarga. En caso de error durante la obtención del usuario o
     * sus datos, se captura una excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos del usuario o mensaje de error.
     */
    public function obtenerUsuarioAutenticado()
    {
        try {

            $user = JWTAuth::user(); // Obtener el usuario autenticado

            foreach ($user->documentosUser as $documento) { // Agregar la URL del archivo a cada documento si existe
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }

            return response()->json(['user' => $user], 200); // Devolver respuesta con el usuario

        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener el usuario autenticado',
                'error'   => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Actualizar la contraseña del usuario autenticado.
     *
     * Este método permite al usuario cambiar su contraseña actual. Primero valida los datos de entrada,
     * luego verifica que la contraseña actual proporcionada coincida con la registrada.
     * Si la verificación es exitosa, la nueva contraseña se cifra y se guarda.
     * En caso de errores de validación, verificación o al guardar, se captura la excepción
     * y se retorna un mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con la contraseña actual y la nueva contraseña.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarContrasena(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [ // Validar los datos de entrada
                'password' => 'required|string|min:8',
                'new_password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                throw new \Exception('error', 400);
            }

            $user = JWTAuth::user(); // Obtener el usuario autenticado

            if (!Hash::check($request->password, $user->password)) { // Verificar la contraseña actual
                throw new \Exception('Contraseña incorrecta', 422);
            }

            $user->password = Hash::make($request->new_password); // Actualizar la contraseña
            $user->save();

            return response()->json(['message' => 'Contraseña actualizada exitosamente'], 200); // Devolver respuesta

        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al actualizar la contraseña.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    

    /**
     * Enviar enlace para restablecer la contraseña del usuario.
     *
     * Este método valida el correo electrónico proporcionado y, si el usuario existe, genera un token único
     * para restablecimiento de contraseña. Luego guarda o actualiza dicho token en la tabla `password_reset_tokens`
     * y envía un correo electrónico al usuario con un enlace para restablecer la contraseña.
     * Si la validación falla o el usuario no existe, se retorna un mensaje de error.
     *
     * @param Request $request Solicitud HTTP con el correo electrónico del usuario.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON indicando el resultado del envío del correo.
     */
    public function restablecerContrasena(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [ // Validar los datos de entrada
                'email' => 'required|string|email|max:100',
            ]);

            if ($validator->fails()) { // Si la validación falla, se guarda el mensaje de error
                throw new \Exception('Validación fallida.', 422);
            }

            $user = User::where('email', $request->email)->first(); // Recuperar el usuario por su email
            if (!$user) {
                throw new \Exception('Usuario no encontrado.', 404);
            }

            $rolesNoPermitidos = ['Administrador', 'Talento Humano', 'Apoyo Profesoral', 'Evaluador Produccion'];
            if ($user->hasAnyRole($rolesNoPermitidos)) {
                throw new \Exception('Este usuario no tiene permitido restablecer la contraseña.', 403);
            }

            $token = bin2hex(random_bytes(32)); // Generar un token para restablecer la contraseña

            DB::table('password_reset_tokens')->updateOrInsert( // Guardar el token en la base de datos
                ['email' => $request->email], // Condición para buscar
                ['token' => $token, 'created_at' => now()] // Datos a actualizar o insertar
            );

            $resetLink = rtrim(config('app.frontend_url'), '/') 
                . '/restablecer-contrasena2?token=' . $token 
                . '&email=' . urlencode($user->email); // Crear el enlace de restablecimiento
            Mail::to($user->email)->send(new ResetPasswordMail($user, $resetLink)); // Enviar correo

            return response()->json(['message' => 'Correo electrónico enviado'], 200); // Devolver respuesta

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar el correo de restablecimiento.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }


    public function actualizarContrasenaConToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                throw new \Exception('Validación fallida.', 422);
            }

            $reset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', $request->token)
                ->first();

            if (!$reset) {
                throw new \Exception('Token inválido.', 404);
            }

            // Verificar si el token ha expirado (5 minutos)
            $createdAt = Carbon::parse($reset->created_at);
            if ($createdAt->diffInMinutes(now()) > 5) {
                throw new \Exception('El token ha expirado. Por favor solicita uno nuevo.', 410);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                throw new \Exception('Usuario no encontrado.', 404);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            DB::table('password_reset_tokens')->where('email', $request->email)->delete(); // Eliminar token

            return response()->json([
                'message' => 'Contraseña actualizada correctamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la contraseña.',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}
