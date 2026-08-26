<?php

namespace App\Services;

use App\Constants\ConstDocumentos\EstadoDocumentos;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aplica la decisión de aval sobre una producción académica completa.
 *
 * El estado vive en `documentos`, que es polimórfica, y una producción puede tener más de un
 * archivo. Hasta ahora la decisión se tomaba archivo por archivo, y eso permitía dejar una
 * producción con un documento aprobado y otro rechazado: un estado incoherente que
 * `MotorEscalafonDocenteService` resuelve a favor del aprobado —le basta uno— mientras el
 * expediente muestra lo contrario.
 *
 * Este servicio decide siempre sobre el conjunto, dentro de una transacción, y firma cada
 * documento con el evaluador que lo hizo. No cambia el modelo de datos: lo que cambia es que
 * ningún camino de la aplicación puede volver a dejar los documentos de una producción en
 * estados distintos.
 */
class AvalProduccionService
{
    /**
     * Aprueba todos los documentos de la producción.
     *
     * @return int Cuántos documentos quedaron aprobados.
     */
    public function avalar(ProduccionAcademica $produccion, User $evaluador): int
    {
        return $this->aplicar($produccion, EstadoDocumentos::APROBADO, $evaluador, null);
    }

    /**
     * Rechaza todos los documentos de la producción y avisa al docente.
     *
     * Es también el camino de la reversión de un aval: revertir es rechazar algo que ya estaba
     * aprobado, y el docente necesita enterarse igual —o más, porque su puntaje de escalafón baja.
     *
     * @return int Cuántos documentos quedaron rechazados.
     */
    public function rechazar(ProduccionAcademica $produccion, User $evaluador, string $motivo): int
    {
        $afectados = $this->aplicar($produccion, EstadoDocumentos::RECHAZADO, $evaluador, $motivo);

        $this->notificarRechazo($produccion, $evaluador, $motivo);

        return $afectados;
    }

    /**
     * Escribe el estado en todos los documentos de la producción.
     *
     * La transacción es lo que hace que la incoherencia sea imposible: si el segundo documento
     * falla al guardar, el primero tampoco queda escrito.
     */
    private function aplicar(
        ProduccionAcademica $produccion,
        string $estado,
        User $evaluador,
        ?string $motivo
    ): int {
        return DB::transaction(function () use ($produccion, $estado, $evaluador, $motivo) {
            // Se relee la relación dentro de la transacción para no decidir sobre una colección
            // cargada antes, que podría no incluir un documento subido mientras tanto.
            $documentos = $produccion->documentosProduccionAcademica()->get();

            foreach ($documentos as $documento) {
                $documento->registrarRevision($estado, $evaluador->id, $motivo);
            }

            return $documentos->count();
        });
    }

    /**
     * Avisa al docente por el mismo camino que ya usan los rechazos de Apoyo Profesoral.
     *
     * Un fallo del correo no puede tumbar la decisión: el estado ya está escrito y confirmado en
     * base de datos cuando esto corre. Se registra en el log y se sigue.
     */
    private function notificarRechazo(ProduccionAcademica $produccion, User $evaluador, string $motivo): void
    {
        try {
            $docente = $produccion->usuarioProduccionAcademica;

            if (!$docente) {
                return;
            }

            $rol = $evaluador->getRoleNames()->first();

            NotificacionController::documentoRechazado($docente, $motivo, $rol);
        } catch (\Exception $e) {
            Log::error(
                "Error al notificar el rechazo de la producción {$produccion->id_produccion_academica}: " . $e->getMessage()
            );
        }
    }
}
