<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Http\Controllers\Controller;
use App\Mail\NotificacionMail;
use App\Models\Usuario\User;
use App\Notifications\NotificacionGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class NotificacionController extends Controller
{
    // -------------------------------------------------------------------------
    // Métodos estáticos: envío de notificaciones desde otros controladores
    // -------------------------------------------------------------------------

    /**
     * Notifica a todos los Aspirantes sobre una nueva convocatoria publicada
     * mediante correo electrónico y notificación en base de datos.
     *
     * @param \Illuminate\Database\Eloquent\Collection $usuarios
     * @param \App\Models\TalentoHumano\Convocatoria|null $convocatoria
     */
    public static function nuevaConvocatoria($usuarios, $convocatoria = null): void
    {
        $titulo  = $convocatoria->nombre_convocatoria ?? 'nueva convocatoria';
        $asunto  = "Nueva convocatoria disponible: {$titulo} – UniDoc";
        $mensaje = 'Se ha publicado una nueva convocatoria en el sistema UniDoc. '
                 . 'Ingresa a la plataforma para conocer los detalles y postularte.';

        $detalles = self::buildDetallesConvocatoria($convocatoria);

        Log::info('[NotificacionController] nuevaConvocatoria: enviando a ' . $usuarios->count() . ' aspirante(s).');

        foreach ($usuarios as $usuario) {
            try {
                Log::info("[NotificacionController] Intentando enviar correo a: {$usuario->email}");
                Mail::to($usuario->email)->send(
                    new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
                );
                Log::info("[NotificacionController] Correo enviado correctamente a: {$usuario->email}");
                $usuario->notify(new NotificacionGeneral($mensaje));
            } catch (\Throwable $e) {
                Log::error("[NotificacionController] Error al notificar nueva convocatoria a {$usuario->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Notifica a los Docentes (ya contratados) sobre una nueva convocatoria publicada.
     *
     * @param \Illuminate\Database\Eloquent\Collection $docentes
     * @param \App\Models\TalentoHumano\Convocatoria|null $convocatoria
     */
    public static function nuevaConvocatoriaDocente($docentes, $convocatoria = null): void
    {
        $titulo  = $convocatoria->nombre_convocatoria ?? 'nueva convocatoria';
        $asunto  = "Nueva convocatoria publicada: {$titulo} – UniDoc";
        $mensaje = 'Se ha publicado una nueva convocatoria en el sistema UniDoc. '
                 . 'Ingresa a la plataforma para conocer los detalles.';

        $detalles = self::buildDetallesConvocatoria($convocatoria);

        Log::info('[NotificacionController] nuevaConvocatoriaDocente: enviando a ' . $docentes->count() . ' docente(s).');

        foreach ($docentes as $docente) {
            try {
                Log::info("[NotificacionController] Intentando enviar correo a docente: {$docente->email}");
                Mail::to($docente->email)->send(
                    new NotificacionMail($asunto, $mensaje, $docente->primer_nombre, $detalles)
                );
                Log::info("[NotificacionController] Correo enviado correctamente a docente: {$docente->email}");
                $docente->notify(new NotificacionGeneral($mensaje));
            } catch (\Throwable $e) {
                Log::error("[NotificacionController] Error al notificar nueva convocatoria a docente {$docente->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Construye el array de detalles a mostrar en el correo a partir de una convocatoria.
     */
    private static function buildDetallesConvocatoria($convocatoria): array
    {
        if (!$convocatoria) {
            return [];
        }

        $detalles = [];

        if (!empty($convocatoria->nombre_convocatoria))
            $detalles['Convocatoria']        = $convocatoria->nombre_convocatoria;
        if (!empty($convocatoria->numero_convocatoria))
            $detalles['Número']              = $convocatoria->numero_convocatoria;
        if (!empty($convocatoria->tipo))
            $detalles['Tipo']                = $convocatoria->tipo;
        if (!empty($convocatoria->periodo_academico))
            $detalles['Período académico']   = $convocatoria->periodo_academico;
        if (!empty($convocatoria->tipo_vinculacion))
            $detalles['Tipo de vinculación'] = $convocatoria->tipo_vinculacion;
        if (!empty($convocatoria->personas_requeridas))
            $detalles['Plazas disponibles']  = $convocatoria->personas_requeridas;
        if (!empty($convocatoria->solicitante))
            $detalles['Solicitante']         = $convocatoria->solicitante;
        if (!empty($convocatoria->fecha_publicacion))
            $detalles['Fecha de publicación'] = \Carbon\Carbon::parse($convocatoria->fecha_publicacion)->format('d/m/Y');
        if (!empty($convocatoria->fecha_cierre))
            $detalles['Fecha de cierre']     = \Carbon\Carbon::parse($convocatoria->fecha_cierre)->format('d/m/Y');
        if (!empty($convocatoria->fecha_inicio_contrato))
            $detalles['Inicio de contrato']  = \Carbon\Carbon::parse($convocatoria->fecha_inicio_contrato)->format('d/m/Y');
        if (!empty($convocatoria->descripcion))
            $detalles['Descripción']         = $convocatoria->descripcion;

        return $detalles;
    }

    /**
     * Notifica al usuario que el estado de su postulación ha cambiado.
     *
     * @param \App\Models\Usuario\User $usuario
     * @param string $estado Nuevo estado de la postulación.
     */
    public static function cambioEstadoPostulacion(User $usuario, string $estado): void
    {
        $asunto  = 'Actualización en tu postulación – UniDoc';
        $mensaje = "El estado de tu postulación ha sido actualizado a: {$estado}. "
                 . 'Ingresa a la plataforma para más detalles.';

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar cambio de estado a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Notifica a los administradores de Talento Humano que un aspirante se ha postulado.
     *
     * @param \Illuminate\Database\Eloquent\Collection $admins Usuarios con rol Talento Humano.
     * @param \App\Models\Usuario\User|null $aspirante Usuario que se postuló.
     */
    public static function nuevaPostulacion($admins, ?User $aspirante = null): void
    {
        $nombreAspirante = $aspirante
            ? "{$aspirante->primer_nombre} {$aspirante->primer_apellido}"
            : 'Un aspirante';

        $asunto  = 'Nueva postulación recibida – UniDoc';
        $mensaje = "{$nombreAspirante} se ha postulado a una convocatoria. "
                 . 'Ingresa a la plataforma para revisar la postulación.';

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(
                    new NotificacionMail($asunto, $mensaje, $admin->primer_nombre)
                );
                $admin->notify(new NotificacionGeneral($mensaje));
            } catch (\Exception $e) {
                Log::error("Error al notificar nueva postulación a {$admin->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Confirma al postulante (aspirante/docente) que su postulación fue recibida.
     *
     * @param \App\Models\Usuario\User $usuario
     * @param \App\Models\TalentoHumano\Convocatoria|null $convocatoria
     */
    public static function confirmacionPostulacion(User $usuario, $convocatoria = null): void
    {
        $nombreConvocatoria = $convocatoria->nombre_convocatoria ?? 'la convocatoria';
        $asunto  = "Postulación recibida: {$nombreConvocatoria} – UniDoc";
        $mensaje = 'Tu postulación ha sido recibida exitosamente. '
                 . 'El equipo de Talento Humano la revisará y te notificará sobre cualquier novedad.';

        $detalles = [];
        if ($convocatoria) {
            if (!empty($convocatoria->nombre_convocatoria))
                $detalles['Convocatoria']        = $convocatoria->nombre_convocatoria;
            if (!empty($convocatoria->numero_convocatoria))
                $detalles['Número']              = $convocatoria->numero_convocatoria;
            if (!empty($convocatoria->tipo))
                $detalles['Tipo']                = $convocatoria->tipo;
            if (!empty($convocatoria->periodo_academico))
                $detalles['Período académico']   = $convocatoria->periodo_academico;
            if (!empty($convocatoria->tipo_vinculacion))
                $detalles['Tipo de vinculación'] = $convocatoria->tipo_vinculacion;
            if (!empty($convocatoria->fecha_cierre))
                $detalles['Fecha de cierre']     = \Carbon\Carbon::parse($convocatoria->fecha_cierre)->format('d/m/Y');
            $detalles['Estado de tu postulación'] = 'Enviada';
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Throwable $e) {
            Log::error("Error al enviar confirmación de postulación a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Notifica a un usuario que ha sido contratado.
     *
     * @param \App\Models\Usuario\User $usuario
     */
    public static function nuevaContratacion(User $usuario): void
    {
        $asunto  = '¡Felicitaciones! Has sido contratado – UniDoc';
        $mensaje = '¡Felicitaciones! Has sido contratado exitosamente. '
                 . 'Tu rol en la plataforma ha sido actualizado a Docente. '
                 . 'Ingresa a UniDoc para continuar.';

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar contratación a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Notifica a los Coordinadores que un aspirante tiene el aval de Talento Humano
     * y está listo para revisión de Coordinación.
     *
     * @param \Illuminate\Database\Eloquent\Collection $coordinadores
     * @param \App\Models\Usuario\User $aspirante
     */
    public static function listoParaCoordinador($coordinadores, User $aspirante): void
    {
        $nombre  = "{$aspirante->primer_nombre} {$aspirante->primer_apellido}";
        $asunto  = 'Aspirante listo para revisión de Coordinación – UniDoc';
        $mensaje = "El aspirante {$nombre} ha recibido el aval de Talento Humano "
                 . 'y está listo para ser revisado por Coordinación.';

        foreach ($coordinadores as $coord) {
            try {
                Mail::to($coord->email)->send(
                    new NotificacionMail($asunto, $mensaje, $coord->primer_nombre)
                );
                $coord->notify(new NotificacionGeneral($mensaje));
            } catch (\Exception $e) {
                Log::error("Error al notificar coordinador {$coord->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Notifica a los usuarios de Vicerrectoría que un aspirante tiene el aval
     * de Coordinación y está listo para revisión de Vicerrectoría.
     *
     * @param \Illuminate\Database\Eloquent\Collection $vicerrectores
     * @param \App\Models\Usuario\User $aspirante
     */
    public static function listoParaVicerrectoria($vicerrectores, User $aspirante): void
    {
        $nombre  = "{$aspirante->primer_nombre} {$aspirante->primer_apellido}";
        $asunto  = 'Aspirante listo para revisión de Vicerrectoría – UniDoc';
        $mensaje = "El aspirante {$nombre} ha recibido el aval de Coordinación "
                 . 'y está listo para ser revisado por Vicerrectoría.';

        foreach ($vicerrectores as $vice) {
            try {
                Mail::to($vice->email)->send(
                    new NotificacionMail($asunto, $mensaje, $vice->primer_nombre)
                );
                $vice->notify(new NotificacionGeneral($mensaje));
            } catch (\Exception $e) {
                Log::error("Error al notificar vicerrectoría {$vice->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Notifica a los usuarios de Rectoría que un aspirante tiene el aval
     * de Vicerrectoría y está listo para aprobación final.
     *
     * @param \Illuminate\Database\Eloquent\Collection $rectores
     * @param \App\Models\Usuario\User $aspirante
     */
    public static function listoParaRectoria($rectores, User $aspirante): void
    {
        $nombre  = "{$aspirante->primer_nombre} {$aspirante->primer_apellido}";
        $asunto  = 'Aspirante listo para aprobación de Rectoría – UniDoc';
        $mensaje = "El aspirante {$nombre} ha recibido el aval de Vicerrectoría "
                 . 'y está listo para la aprobación final de Rectoría.';

        foreach ($rectores as $rector) {
            try {
                Mail::to($rector->email)->send(
                    new NotificacionMail($asunto, $mensaje, $rector->primer_nombre)
                );
                $rector->notify(new NotificacionGeneral($mensaje));
            } catch (\Exception $e) {
                Log::error("Error al notificar rectoría {$rector->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Notifica al aspirante que su hoja de vida fue avalada por Rectoría (proceso completo).
     *
     * @param \App\Models\Usuario\User $aspirante
     */
    public static function avalFinalCompletado(User $aspirante): void
    {
        $asunto  = 'Tu hoja de vida ha sido avalada – UniDoc';
        $mensaje = '¡Tu hoja de vida ha sido revisada y avalada por Rectoría! '
                 . 'El proceso de revisión ha concluido. '
                 . 'Ingresa a UniDoc para conocer los próximos pasos.';

        try {
            Mail::to($aspirante->email)->send(
                new NotificacionMail($asunto, $mensaje, $aspirante->primer_nombre)
            );
            $aspirante->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar aval final a {$aspirante->email}: " . $e->getMessage());
        }
    }

    /**
     * Notifica al postulante que su postulación ha sido rechazada, indicando el motivo y quién la rechazó.
     *
     * @param \App\Models\Usuario\User $usuario
     * @param string|null $motivo    Motivo del rechazo (falta de documentos, perfil no cumple requisitos, etc.).
     * @param string|null $rechazadoPor  Rol o nombre de quien rechazó (Talento Humano, Coordinador, Vicerrectoría…).
     */
    public static function postulacionRechazada(User $usuario, ?string $motivo, ?string $rechazadoPor = null): void
    {
        $porQuien = $rechazadoPor ? " por {$rechazadoPor}" : '';
        $asunto   = 'Tu postulación ha sido rechazada – UniDoc';
        $mensaje  = "Tu postulación ha sido rechazada{$porQuien}. "
                  . 'Ingresa a la plataforma para más información.';

        $detalles = [];
        if (!empty($motivo)) {
            $detalles['Motivo del rechazo'] = $motivo;
        }
        if (!empty($rechazadoPor)) {
            $detalles['Rechazado por'] = $rechazadoPor;
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar rechazo de postulación a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Notifica al aspirante que su hoja de vida / perfil ha sido rechazado en la cadena de avales,
     * indicando la etapa y el motivo del rechazo.
     *
     * @param \App\Models\Usuario\User $aspirante
     * @param string|null $motivo  Motivo del rechazo.
     * @param string      $rol     Rol que rechazó (Talento Humano, Coordinador, Vicerrectoría, Rectoría).
     */
    public static function avalRechazado(User $aspirante, ?string $motivo, string $rol): void
    {
        $asunto  = "Tu hoja de vida fue rechazada en la etapa de {$rol} – UniDoc";
        $mensaje = "Tu hoja de vida ha sido revisada por {$rol} y no ha sido aprobada en esta etapa. "
                 . 'Ingresa a la plataforma para conocer el detalle.';

        $detalles = ['Etapa' => $rol];
        if (!empty($motivo)) {
            $detalles['Motivo del rechazo'] = $motivo;
        }

        try {
            Mail::to($aspirante->email)->send(
                new NotificacionMail($asunto, $mensaje, $aspirante->primer_nombre, $detalles)
            );
            $aspirante->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar rechazo de aval [{$rol}] a {$aspirante->email}: " . $e->getMessage());
        }
    }

    /**
     * Notifica al aspirante/docente que uno de sus documentos ha sido rechazado.
     *
     * @param \App\Models\Usuario\User $usuario
     * @param string|null $motivo  Motivo del rechazo del documento.
     * @param string|null $rol     Rol que rechazó el documento.
     */
    public static function documentoRechazado(User $usuario, ?string $motivo, ?string $rol = null): void
    {
        $porQuien = $rol ? " por {$rol}" : '';
        $asunto   = 'Un documento ha sido rechazado – UniDoc';
        $mensaje  = "Uno de tus documentos cargados en UniDoc ha sido rechazado{$porQuien}. "
                  . 'Por favor, revisa el motivo e ingresa un nuevo documento válido.';

        $detalles = [];
        if (!empty($motivo)) {
            $detalles['Motivo del rechazo'] = $motivo;
        }
        if (!empty($rol)) {
            $detalles['Rechazado por'] = $rol;
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar rechazo de documento a {$usuario->email}: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Métodos de instancia: endpoints REST para consultar y gestionar notificaciones
    // -------------------------------------------------------------------------

    /**
     * Obtener todas las notificaciones del usuario autenticado,
     * ordenadas de la más reciente a la más antigua.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerNotificaciones(Request $request)
    {
        try {
            $notificaciones = $request->user()
                ->notifications()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($n) {
                    return [
                        'id'         => $n->id,
                        'mensaje'    => $n->data['mensaje'] ?? null,
                        'leida'      => !is_null($n->read_at),
                        'created_at' => $n->created_at,
                    ];
                });

            return response()->json(['notificaciones' => $notificaciones], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ocurrió un error al obtener las notificaciones.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar una notificación específica del usuario autenticado como leída.
     *
     * @param Request $request
     * @param string $id UUID de la notificación.
     * @return \Illuminate\Http\JsonResponse
     */
    public function marcarComoLeida(Request $request, $id)
    {
        try {
            $notificacion = $request->user()->notifications()->where('id', $id)->first();

            if (!$notificacion) {
                return response()->json(['message' => 'Notificación no encontrada.'], 404);
            }

            $notificacion->markAsRead();

            return response()->json(['message' => 'Notificación marcada como leída.'], 200);
        } catch (\Exception $e) {
            Log::error('Error al marcar notificación como leída: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ocurrió un error al marcar la notificación.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones no leídas del usuario autenticado como leídas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function marcarTodasComoLeidas(Request $request)
    {
        try {
            $request->user()->unreadNotifications()->update(['read_at' => now()]);

            return response()->json(['message' => 'Todas las notificaciones marcadas como leídas.'], 200);
        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ocurrió un error al marcar las notificaciones.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
