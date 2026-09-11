<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Baja a la base de datos la invariante "un docente tiene como mucho un tramo de escalafón abierto".
 *
 * La migración original decía que esa condición no se podía expresar con un índice único «como la
 * condición es "null y no revertido", no hay índice único que la exprese» y la dejaba al cuidado de
 * `AscensoEscalafonService`, que era el único escritor de la tabla y trabajaba con `lockForUpdate()`.
 *
 * Lo primero es inexacto: PostgreSQL tiene índices únicos **parciales**, y el predicado es
 * exactamente el del scope `HistorialEscalonDocente::vigentes()`. Lo segundo dejó de ser cierto con
 * el ingreso manual y la corrección de tramos del Administrador: ahora hay tres caminos de
 * escritura, y uno de ellos —`corregirTramo()`— puede poner `hasta = null` sobre un tramo ya
 * cerrado, una operación que ninguno de los actos anteriores hacía sobre un tramo arbitrario.
 *
 * Importa porque el modo de fallo es silencioso, no ruidoso. `MotorEscalafonDocenteService::
 * tramoVigente()` resuelve con `first()` sobre una relación ordenada por `desde` ascendente: con dos
 * tramos abiertos elegiría **el más antiguo**, es decir el escalón inferior. El docente quedaría
 * degradado y con la ventana de producción académica contada desde una fecha vieja, sin que nada
 * fallara.
 *
 * Las validaciones en PHP se mantienen: son las que devuelven un 409 con un mensaje que explica qué
 * tramo estorba. Este índice es la red de abajo, la que convierte una carrera entre dos peticiones
 * en un error en vez de en un expediente corrupto.
 *
 * Los tres escritores existentes ya respetan el orden correcto y no lo violan ni de forma
 * transitoria: `ascender()` cierra el tramo abierto antes de crear el nuevo, `revertir()` marca
 * `revertido_en` —lo que ya lo saca del predicado— antes de reabrir el anterior, e `ingresar()` solo
 * crea cuando no hay ninguno abierto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX historial_escalon_tramo_abierto_unico
                ON historial_escalon_docente (user_id)
                WHERE hasta IS NULL AND revertido_en IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS historial_escalon_tramo_abierto_unico');
    }
};
