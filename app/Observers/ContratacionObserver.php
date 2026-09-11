<?php

namespace App\Observers;

use App\Models\TalentoHumano\Contratacion;
use App\Services\AscensoEscalafonService;
use Illuminate\Support\Facades\Log;

/**
 * Mete al docente al escalafón en cuanto su contratación lo acredita como de planta.
 *
 * El ingreso al escalafón dejó de ser un acto administrativo. Antes lo registraba Apoyo Profesoral
 * en un formulario propio, y eso abría un hueco que ninguna pantalla mostraba: un docente de planta
 * al que nadie diera de alta simplemente no existía para el escalafón —no era elegible para ningún
 * ascenso— sin que apareciera en ninguna bandeja de pendientes. Ser de planta y estar en el
 * escalafón son la misma cosa, así que el dato que lo decide, la contratación, es también el que lo
 * dispara.
 *
 * Escucha `created` y `updated`, no solo el alta: un contrato de cátedra que se corrige a planta, o
 * uno vencido que se renueva, dejan al docente en la misma situación que uno nuevo. Quien decide si
 * procede es el servicio (ver `AscensoEscalafonService::inicioComoPlanta()`), no este observer: aquí
 * no se mira el contrato que llegó sino que se le pregunta por el docente entero, porque lo normal
 * tras una convocatoria de ascenso es tener varias contrataciones y la que acredita la planta puede
 * ser otra distinta de la que se acaba de tocar.
 *
 * No escucha `deleted`. Borrar el contrato no saca a nadie del escalafón: para eso está la
 * reversión, que sí es un acto firmado y con motivo. Un tramo que desaparece solo se llevaría por
 * delante la antigüedad acumulada sin dejar rastro de por qué.
 */
class ContratacionObserver
{
    public function __construct(private AscensoEscalafonService $ascensos)
    {
    }

    public function created(Contratacion $contratacion): void
    {
        $this->sincronizarEscalafon($contratacion);
    }

    public function updated(Contratacion $contratacion): void
    {
        $this->sincronizarEscalafon($contratacion);
    }

    /**
     * El escalafón no puede tumbar la contratación.
     *
     * Registrar el contrato es el acto principal y el que tiene consecuencias legales; el ingreso al
     * escalafón cuelga de él. Si algo falla aquí —el catálogo de escalones sin configurar, un fallo
     * al recalcular la caché de puntajes— se deja el rastro en el log y la contratación sigue su
     * curso. El ingreso volverá a intentarse en la próxima edición del contrato.
     */
    private function sincronizarEscalafon(Contratacion $contratacion): void
    {
        $docente = $contratacion->usuarioContratacion;

        if (!$docente) {
            return;
        }

        try {
            $this->ascensos->ingresar($docente);
        } catch (\Throwable $e) {
            Log::error(
                "Error al ingresar automáticamente al escalafón al docente {$docente->id} "
                . "tras la contratación {$contratacion->id_contratacion}: " . $e->getMessage()
            );
        }
    }
}
