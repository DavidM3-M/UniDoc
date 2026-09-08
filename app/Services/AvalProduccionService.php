<?php

namespace App\Services;

use App\Constants\ConstDocumentos\EstadoDocumentos;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Models\Aspirante\ProduccionAcademica;
use App\Models\MovimientoExpediente;
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
        $afectados = $this->aplicar($produccion, EstadoDocumentos::APROBADO, $evaluador, null);

        // El aval era el unico acto del modulo que no avisaba nada. Se registra como movimiento
        // para que entre en el resumen diario del docente junto al resto de su expediente, en vez
        // de mandar un correo suelto por cada produccion avalada.
        MovimientoExpediente::registrar(
            userId: $produccion->user_id,
            accion: MovimientoExpediente::APROBADO,
            categoria: 'Produccion academica',
            descripcion: (string) $produccion->titulo,
            motivo: null,
            rol: $evaluador->getRoleNames()->first(),
        );

        return $afectados;
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
        // Se mira ANTES de aplicar: despues del cambio ya no habria forma de saber si lo que se
        // deshizo era un aval vigente o si la produccion nunca llego a estar aprobada.
        $eraAvalada = $produccion->estadoAval() === EstadoDocumentos::APROBADO;

        $afectados = $this->aplicar($produccion, EstadoDocumentos::RECHAZADO, $evaluador, $motivo);

        $this->notificarRechazo($produccion, $evaluador, $motivo, $eraAvalada);

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
    private function notificarRechazo(
        ProduccionAcademica $produccion,
        User $evaluador,
        string $motivo,
        bool $eraAvalada = false
    ): void {
        try {
            $docente = $produccion->usuarioProduccionAcademica;

            if (!$docente) {
                return;
            }

            $rol = $evaluador->getRoleNames()->first();

            // Retirar un aval y rechazar algo que nunca lo tuvo no son lo mismo para el docente, y
            // hasta ahora los dos casos usaban el mismo texto: el de `documentoRechazado()`, que le
            // pide «ingresar un nuevo documento valido». En una reversion esa instruccion no aplica
            // —no le rechazaron nada que subiera, le quitaron puntaje que ya tenia— y lo dejaba sin
            // saber que hacer.
            if ($eraAvalada) {
                NotificacionController::avalProduccionRevertido(
                    $docente,
                    $produccion->titulo,
                    (int) ($produccion->ambitoDivulgacionProduccionAcademica?->puntaje ?? 0),
                    $motivo,
                    $rol
                );

                return;
            }

            // Rechazo de algo que nunca estuvo avalado: entra al resumen diario como un movimiento
            // mas, con el titulo de la produccion. El correo generico de `documentoRechazado()` no
            // decia cual era.
            MovimientoExpediente::registrar(
                userId: $docente->id,
                accion: MovimientoExpediente::RECHAZADO,
                categoria: 'Produccion academica',
                descripcion: (string) $produccion->titulo,
                motivo: $motivo,
                rol: $rol,
            );
        } catch (\Exception $e) {
            Log::error(
                "Error al notificar el rechazo de la producción {$produccion->id_produccion_academica}: " . $e->getMessage()
            );
        }
    }
}
