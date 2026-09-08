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

    /**
     * Avisa al docente de que Apoyo Profesoral le otorgó un escalón del escalafón.
     *
     * El ascenso ya no ocurre solo cuando el docente consulta su puntaje: lo ejecuta una persona,
     * así que el docente no tiene forma de enterarse si no se le avisa. Ver `AscensoEscalafonService`.
     */
    public static function escalonOtorgado(User $usuario, string $escalon, ?string $rol = null): void
    {
        // El rol viene del ejecutor y no está escrito a mano porque el ascenso dejó de ser exclusivo
        // de Apoyo Profesoral: el Administrador ejecuta el mismo acto desde `/admin/escalafon`.
        $quien   = $rol ?? 'La Universidad';
        $asunto  = "Has ascendido a {$escalon} – UniDoc";
        $mensaje = "{$quien} registró tu ascenso en el escalafón docente. Tu nueva categoría es {$escalon}. "
                 . 'Ten en cuenta que el conteo de antigüedad y el puntaje de producción académica '
                 . 'vuelven a empezar desde esta categoría.';

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, ['Nueva categoría' => $escalon])
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar el ascenso a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Avisa al docente de que se deshizo un acto del escalafón y en qué categoría queda.
     *
     * `$escalonRestituido` es null cuando lo revertido era su ingreso: en ese caso el docente sale
     * del escalafón por completo.
     */
    public static function escalonRevertido(
        User $usuario,
        ?string $escalonRevertido,
        ?string $escalonRestituido,
        string $motivo,
        ?string $rol = null
    ): void {
        $porQuien = $rol ? " por {$rol}" : '';
        $asunto   = 'Se revirtió un cambio en tu escalafón – UniDoc';
        $mensaje  = "Se revirtió{$porQuien} tu categoría " . ($escalonRevertido ?? 'de escalafón') . '. '
                  . ($escalonRestituido
                        ? "Quedas nuevamente como {$escalonRestituido}."
                        : 'Quedas fuera del escalafón hasta que se registre tu categoría de nuevo.');

        $detalles = ['Motivo' => $motivo];
        if ($escalonRestituido) {
            $detalles['Categoría vigente'] = $escalonRestituido;
        }
        if ($rol) {
            $detalles['Revertido por'] = $rol;
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar la reversión de escalafón a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Avisa al docente de que se corrigió un tramo de su historial de escalafón.
     *
     * No es un ascenso ni una reversión, así que no puede reutilizar ninguno de los dos mensajes:
     * felicitarle por un ascenso que no ha habido, o decirle que se le quitó una categoría, serían
     * las dos igual de falsas. Es una corrección administrativa del registro.
     *
     * Se avisa aunque solo cambien las fechas: `desde` es el origen del conteo de antigüedad y el
     * inicio de la ventana de producción académica que puntúa, así que moverlo le cambia al docente
     * la respuesta a "cuándo puedo ascender" sin tocarle la categoría.
     *
     * @param array $antes   Retrato del tramo antes de la corrección (escalon, desde, hasta).
     * @param array $despues Retrato después.
     */
    public static function escalonCorregido(
        User $usuario,
        array $antes,
        array $despues,
        string $motivo,
        ?string $rol = null
    ): void {
        $quien  = $rol ?? 'La Universidad';
        $asunto = 'Se corrigió un registro de tu escalafón – UniDoc';

        // Solo se enumera lo que cambió de verdad: una corrección de fecha no debe abrirse con una
        // frase sobre la categoría, que es lo primero que el docente busca en el correo.
        $cambios = [];

        if (($antes['escalon'] ?? null) !== ($despues['escalon'] ?? null)) {
            $cambios['Categoría'] = ($antes['escalon'] ?? 'sin escalón') . ' → ' . ($despues['escalon'] ?? 'sin escalón');
        }
        if (($antes['desde'] ?? null) !== ($despues['desde'] ?? null)) {
            $cambios['Desde'] = ($antes['desde'] ?? '—') . ' → ' . ($despues['desde'] ?? '—');
        }
        if (($antes['hasta'] ?? null) !== ($despues['hasta'] ?? null)) {
            $cambios['Hasta'] = ($antes['hasta'] ?? 'vigente') . ' → ' . ($despues['hasta'] ?? 'vigente');
        }

        $mensaje = "{$quien} corrigió un registro de tu historial en el escalafón docente. "
                 . (isset($cambios['Categoría'])
                        ? 'Tu categoría cambió con esta corrección. '
                        : 'Tu categoría no cambia, pero sí el periodo con el que se cuenta tu antigüedad. ')
                 . 'Puedes revisar el detalle en tu estado de escalafón.';

        $detalles = array_merge($cambios, ['Motivo' => $motivo]);

        if ($rol) {
            $detalles['Corregido por'] = $rol;
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar la corrección de escalafón a {$usuario->email}: " . $e->getMessage());
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
    /**
     * Avisa a un rol operativo de que su cola de revisión bloquea ascensos ante un cierre próximo.
     *
     * Lo comparten Apoyo Profesoral y el Evaluador de Producción porque el problema es el mismo:
     * lo que no alcancen a revisar antes de la fecha de cierre no cuenta para el periodo, aunque el
     * docente lo haya subido a tiempo. Cambia qué se revisa —documentos en un caso, producción
     * académica en el otro— no el mensaje.
     *
     * Es un aviso agregado a propósito: un correo con el conteo, no uno por cada registro pendiente.
     */
    public static function colaPendienteAnteCierre(
        User $usuario,
        string $nombrePeriodo,
        string $fechaCierre,
        int $diasRestantes,
        int $pendientes,
        array $detalles = []
    ): void {
        $asunto  = "{$pendientes} pendientes antes del cierre del {$fechaCierre} – UniDoc";
        $mensaje = "Quedan {$diasRestantes} días para el cierre del periodo «{$nombrePeriodo}». "
                 . "Hay {$pendientes} registros esperando tu revisión. Lo que siga sin revisar el "
                 . "{$fechaCierre} no cuenta para este periodo, aunque el docente lo haya subido a tiempo.";

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al avisar de la cola pendiente a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * Recuerda al docente que el periodo de ascenso está por cerrar.
     *
     * El cierre es el corte con el que se congela su expediente: lo que quede pendiente de revisión
     * ese día no cuenta, y el siguiente periodo puede tardar un año. Es el único correo del sistema
     * capaz de cambiar el resultado de alguien mientras todavía hay tiempo de reaccionar.
     */
    public static function cierrePeriodoProximo(
        User $usuario,
        string $nombrePeriodo,
        string $fechaCierre,
        int $diasRestantes
    ): void {
        $asunto  = "Faltan {$diasRestantes} días para el cierre del periodo de ascenso – UniDoc";
        $mensaje = "El periodo «{$nombrePeriodo}» cierra el {$fechaCierre}. Tienes hasta esa fecha para "
                 . 'completar tu expediente: los estudios, idiomas, experiencia y producción académica '
                 . 'que registres y te sean aprobados antes del cierre son los que se tendrán en cuenta. '
                 . 'Lo que quede pendiente de revisión ese día no cuenta para este periodo.';

        $detalles = [
            'Periodo'   => $nombrePeriodo,
            'Cierra el' => $fechaCierre,
            'Faltan'    => "{$diasRestantes} días",
        ];

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al recordar el cierre del periodo a {$usuario->email}: " . $e->getMessage());
        }
    }
    /**
     * C2 — Avisa al docente de que entró al escalafón.
     *
     * Era el silencio más grande del módulo: un docente ingresaba —por su contratación de planta o
     * porque el Administrador lo registró— y nunca se enteraba. Desde ese día empiezan a contar su
     * antigüedad y la ventana de producción que puntúa, así que es la fecha desde la que se mide
     * todo lo demás.
     *
     * `$motivo` solo viene en el ingreso manual: ahí hubo una decisión humana y el docente tiene
     * derecho a leer por qué. El automático no lo lleva porque no lo decidió nadie.
     */
    public static function ingresoAlEscalafon(
        User $usuario,
        string $escalon,
        string $desde,
        ?string $siguiente = null,
        ?string $motivo = null,
        ?string $rol = null
    ): void {
        $manual = $motivo !== null;

        $asunto  = $manual
            ? 'Se registró tu ingreso al escalafón docente – UniDoc'
            : 'Ingresaste al escalafón docente – UniDoc';

        $mensaje = $manual
            ? ($rol ?? 'La Universidad') . " registró tu ingreso al escalafón docente en la categoría {$escalon}, "
              . "con fecha del {$desde}. Desde esa fecha cuenta tu antigüedad en la categoría. Si algo no "
              . 'corresponde con tu situación real, comunícate con Apoyo Profesoral.'
            : "Tu contratación de planta te acredita como docente de planta y con ella ingresaste al escalafón "
              . "en la categoría {$escalon}. Desde esta fecha empiezan a contar tu antigüedad y el puntaje de "
              . 'producción académica que se tendrán en cuenta para tu próximo ascenso.';

        $detalles = ['Categoría' => $escalon, 'Desde' => $desde];

        if ($siguiente) {
            $detalles['Siguiente categoría'] = $siguiente;
        }
        if ($manual) {
            $detalles['Motivo'] = $motivo;
            $detalles['Registrado por'] = $rol ?? 'La Universidad';
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar el ingreso al escalafón a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * C10 — Avisa al docente de que se le retiró el aval de una producción académica.
     *
     * Existe porque hasta ahora este caso reutilizaba `documentoRechazado()`, cuyo texto le pide
     * «ingresar un nuevo documento válido». Eso no aplica: no le rechazaron algo que subió, le
     * quitaron un aval que ya tenía, y su puntaje de producción bajó. La instrucción era incorrecta
     * y el docente no podía saber qué hacer con ella.
     */
    public static function avalProduccionRevertido(
        User $usuario,
        string $titulo,
        int $puntajePerdido,
        string $motivo,
        ?string $rol = null
    ): void {
        $asunto  = 'Se retiró el aval de una de tus producciones – UniDoc';
        $mensaje = ($rol ?? 'La Universidad') . " retiró el aval de tu producción académica «{$titulo}». "
                 . "Con ello tu puntaje de producción baja en {$puntajePerdido} puntos, lo que puede afectar "
                 . 'tu elegibilidad para el próximo ascenso. Abajo está el motivo.';

        $detalles = [
            'Producción'      => $titulo,
            'Puntaje retirado' => "{$puntajePerdido} puntos",
            'Motivo'          => $motivo,
        ];

        if ($rol) {
            $detalles['Revertido por'] = $rol;
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar la reversión de aval a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * C13 — Avisa a quien registró un ascenso de que otra persona lo deshizo.
     *
     * Es el acto más delicado del módulo: deshace una decisión firmada por alguien. Hasta ahora
     * Apoyo Profesoral podía otorgar un ascenso y que el Administrador lo revirtiera sin que
     * quedara ningún aviso para el primero, que seguiría creyendo que su decisión sigue en pie.
     *
     * Es informativo y no pide nada: el docente ya fue notificado por `escalonRevertido()`.
     */
    public static function tramoRevertidoAuditoria(
        User $usuario,
        string $nombreDocente,
        ?string $escalonRevertido,
        ?string $escalonRestituido,
        string $motivo,
        ?string $rol = null
    ): void {
        $asunto  = 'Se revirtió un ascenso que registraste – UniDoc';
        $mensaje = ($rol ?? 'Otro funcionario') . " revirtió el ascenso a "
                 . ($escalonRevertido ?? 'una categoría') . " de {$nombreDocente}, que tú habías registrado. "
                 . 'El docente ya fue notificado. Este aviso es informativo y no requiere ninguna acción de tu parte.';

        $detalles = [
            'Docente'            => $nombreDocente,
            'Categoría revertida' => $escalonRevertido ?? 'Ingreso al escalafón',
            'Categoría vigente ahora' => $escalonRestituido ?? 'Fuera del escalafón',
            'Motivo'             => $motivo,
        ];

        if ($rol) {
            $detalles['Revertido por'] = $rol;
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar la auditoría de reversión a {$usuario->email}: " . $e->getMessage());
        }
    }
    /**
     * C3 — Anuncia al docente que se abrio un periodo de ascenso.
     *
     * Es el unico correo del sistema que va al padron completo, y se justifica porque abrir un
     * periodo es la senal de «tienes hasta esta fecha para completar tu expediente». En una
     * plataforma que se usa una vez al ano, quien no se entera pierde el ciclo entero.
     *
     * Si el periodo nace con poco margen, el mensaje lo dice: el recordatorio de 30 dias no llegara
     * a dispararse nunca y esta es la unica advertencia que ese docente va a recibir con tiempo.
     */
    public static function periodoAscensoAbierto(
        User $usuario,
        string $nombrePeriodo,
        string $fechaCierre,
        int $diasDePlazo
    ): void {
        $asunto = "Abierto el periodo de ascenso {$nombrePeriodo} – UniDoc";

        $urgencia = $diasDePlazo <= 30
            ? " Ten en cuenta que el plazo es corto: solo quedan {$diasDePlazo} dias."
            : '';

        $mensaje = "Se abrio el periodo de ascenso «{$nombrePeriodo}», que cierra el {$fechaCierre}. "
                 . 'Tienes hasta esa fecha para completar tu expediente: los estudios, idiomas, experiencia '
                 . 'y produccion academica que registres y te sean aprobados antes del cierre son los que se '
                 . 'tendran en cuenta. Lo que quede pendiente de revision ese dia no cuenta para este periodo.'
                 . $urgencia;

        $detalles = [
            'Periodo'   => $nombrePeriodo,
            'Cierra el' => $fechaCierre,
            'Plazo'     => "{$diasDePlazo} dias",
        ];

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al anunciar el periodo de ascenso a {$usuario->email}: " . $e->getMessage());
        }
    }

    /**
     * C11 — Avisa a Apoyo Profesoral de que el periodo cerro y hay elegibles esperando decision.
     *
     * Es un aviso agregado a proposito: un correo con el conteo, no uno por cada docente elegible.
     * La diferencia entre un mensaje y cuarenta y cinco.
     *
     * El expediente de cada docente queda congelado con corte a la fecha de cierre, asi que este
     * correo marca el momento en que empieza el trabajo de decidir.
     */
    public static function periodoCerradoConElegibles(
        User $usuario,
        string $nombrePeriodo,
        string $fechaCierre,
        int $elegibles,
        array $porCategoria = [],
        int $noElegibles = 0
    ): void {
        $asunto = "Cerro {$nombrePeriodo}: {$elegibles} docentes elegibles – UniDoc";

        $mensaje = "El periodo «{$nombrePeriodo}» cerro el {$fechaCierre} y ya se pueden ejecutar los "
                 . 'ascensos. El expediente de cada docente quedo congelado con corte a esa fecha: lo que '
                 . "suban despues cuenta para el periodo siguiente. Hay {$elegibles} docentes elegibles "
                 . 'esperando decision en la bandeja de ascensos.';

        $detalles = [
            'Periodo'   => "{$nombrePeriodo}, cerrado el {$fechaCierre}",
            'Elegibles' => "{$elegibles} docentes",
        ];

        if ($porCategoria) {
            $detalles['Por categoria'] = collect($porCategoria)
                ->map(fn ($n, $cat) => "{$n} a {$cat}")
                ->implode(' · ');
        }

        if ($noElegibles > 0) {
            $detalles['No elegibles'] = "{$noElegibles} docentes";
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al avisar del cierre del periodo a {$usuario->email}: " . $e->getMessage());
        }
    }
    /**
     * C1 — Un solo correo con todo lo que se movio hoy en el expediente del docente.
     *
     * Sustituye seis avisos que antes iban por separado —documento aprobado, documento rechazado,
     * produccion avalada, produccion rechazada, evaluacion asignada y evaluacion modificada—. En
     * una plataforma que se usa una vez al ano, esos seis llegaban el mismo martes.
     *
     * El asunto lleva delante lo que exige accion: si hay rechazos se nombran primero y con su
     * numero, porque son lo unico que el docente tiene que corregir antes del cierre.
     *
     * @param array $aprobados  Cada uno: ['categoria' => ..., 'descripcion' => ...]
     * @param array $rechazados Cada uno: ['categoria' => ..., 'descripcion' => ..., 'motivo' => ...]
     */
    public static function resumenDiarioExpediente(
        User $usuario,
        array $aprobados,
        array $rechazados,
        ?string $nombrePeriodo = null,
        ?string $fechaCierre = null,
        ?int $diasRestantes = null
    ): void {
        $nA = count($aprobados);
        $nR = count($rechazados);
        $total = $nA + $nR;

        if ($total === 0) {
            return;
        }

        // El asunto se construye para que se lea en la bandeja sin abrirlo.
        if ($nR > 0) {
            $asunto = $nA > 0
                ? "{$nR} " . ($nR === 1 ? 'documento rechazado' : 'documentos rechazados')
                  . " y {$nA} " . ($nA === 1 ? 'aprobado' : 'aprobados') . ' en tu expediente – UniDoc'
                : "{$nR} " . ($nR === 1 ? 'documento rechazado' : 'documentos rechazados') . ' en tu expediente – UniDoc';
        } else {
            $asunto = "{$nA} " . ($nA === 1 ? 'registro aprobado' : 'registros aprobados') . ' en tu expediente – UniDoc';
        }

        $mensaje = "Hoy se revisaron {$total} " . ($total === 1 ? 'registro' : 'registros') . ' de tu expediente. ';

        if ($nR > 0) {
            $mensaje .= ($nR === 1 ? 'Uno fue rechazado y necesita que lo corrijas. ' : "{$nR} fueron rechazados y necesitan que los corrijas. ");
        }

        if ($nA > 0) {
            $mensaje .= ($nA === 1 ? 'El otro quedo aprobado y ya cuenta ' : 'Los demas quedaron aprobados y ya cuentan ')
                      . 'para tu evaluacion. ';
        }

        if ($nombrePeriodo && $fechaCierre) {
            $mensaje .= "Tienes hasta el {$fechaCierre}, cuando cierra el periodo «{$nombrePeriodo}».";
        }

        // Los rechazos van primero: son lo unico accionable. Cada linea dice de que registro se
        // habla, que es exactamente lo que faltaba en el correo generico anterior.
        $detalles = [];

        foreach ($rechazados as $i => $r) {
            $clave = $nR === 1 ? 'Rechazado' : 'Rechazado ' . ($i + 1);
            $detalles[$clave] = "{$r['categoria']} — {$r['descripcion']}"
                              . (!empty($r['motivo']) ? "\nMotivo: {$r['motivo']}" : '');
        }

        foreach ($aprobados as $i => $a) {
            $clave = $nA === 1 ? 'Aprobado' : 'Aprobado ' . ($i + 1);
            $detalles[$clave] = "{$a['categoria']} — {$a['descripcion']}";
        }

        if ($fechaCierre && $diasRestantes !== null) {
            $detalles['Cierra el'] = "{$fechaCierre} — faltan {$diasRestantes} dias";
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al enviar el resumen del expediente a {$usuario->email}: " . $e->getMessage());
        }
    }
    /**
     * C7 — Le dice al docente que no ascendio y exactamente que le falto.
     *
     * Sale solo a quien estuvo cerca. A quien le faltaban cuatro criterios este correo no le aporta
     * nada y se lee como una mala noticia masiva; a quien le faltaba uno le dice donde poner el
     * esfuerzo del proximo ciclo, que en una plataforma anual es un ano de diferencia.
     *
     * El desglose ya lo calcula `MotorEscalafonDocenteService::evaluarAscenso()`: hasta ahora solo
     * se veia si el docente entraba a mirar la pantalla.
     *
     * @param array $faltantes Cada uno: ['criterio' => ..., 'detalle' => ...]
     */
    public static function ascensoNoAlcanzado(
        User $usuario,
        string $nombrePeriodo,
        string $fechaCierre,
        string $escalonVigente,
        ?string $escalonObjetivo,
        array $faltantes,
        array $cumplidos = []
    ): void {
        $asunto  = "Resultado del periodo de ascenso {$nombrePeriodo} – UniDoc";

        $mensaje = "El periodo «{$nombrePeriodo}» cerro el {$fechaCierre} y tu expediente no alcanzo los "
                 . 'requisitos para la categoria ' . ($escalonObjetivo ?? 'siguiente') . ", asi que continuas "
                 . "como {$escalonVigente}. Abajo esta el detalle de lo que falto, medido con corte a la fecha "
                 . 'de cierre. Lo que registres desde ahora cuenta para el siguiente periodo.';

        $detalles = [];

        foreach ($faltantes as $f) {
            $detalles[$f['criterio']] = $f['detalle'];
        }

        // Los cumplidos van despues y marcados: el correo no puede leerse como si nada sirviera.
        foreach ($cumplidos as $c) {
            $detalles[$c] = 'Cumplido';
        }

        try {
            Mail::to($usuario->email)->send(
                new NotificacionMail($asunto, $mensaje, $usuario->primer_nombre, $detalles)
            );
            $usuario->notify(new NotificacionGeneral($mensaje));
        } catch (\Exception $e) {
            Log::error("Error al notificar el resultado del periodo a {$usuario->email}: " . $e->getMessage());
        }
    }
}