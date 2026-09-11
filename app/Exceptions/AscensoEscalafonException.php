<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un acto de escalafón que no se puede ejecutar por el estado del expediente: el docente no cumple
 * los requisitos, ya tiene un tramo abierto, el periodo todavía no ha cerrado, el tramo ya estaba
 * revertido...
 *
 * Existe para separar estos casos —que son una respuesta legítima del negocio y viajan como 409—
 * de los errores de verdad, que siguen cayendo en el `catch (\Exception)` genérico y responden 500.
 * Sin ella, `AscensoEscalafonService` tendría que devolver códigos y el controlador adivinar.
 *
 * El `contexto` lleva lo que el mensaje no puede resumir sin volverse ilegible. Cuando el ascenso se
 * rechaza por requisitos, el motor ya calculó para cada uno qué se pedía y qué tiene el docente
 * (`faltantes`) y contra qué fecha se midió (`fecha_corte`); sin este canal esa información moría en
 * el servicio y la pantalla solo recibía una lista de nombres de criterio.
 */
class AscensoEscalafonException extends RuntimeException
{
    public function __construct(string $message, private readonly array $contexto = [])
    {
        parent::__construct($message);
    }

    /** Datos de apoyo para la respuesta; vacío en los rechazos que el mensaje ya explica entero. */
    public function contexto(): array
    {
        return $this->contexto;
    }
}
