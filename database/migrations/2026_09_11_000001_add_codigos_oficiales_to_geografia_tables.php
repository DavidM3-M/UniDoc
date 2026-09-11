<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el código oficial de cada país, departamento y municipio.
 *
 * Hasta ahora estas tablas solo tenían el nombre, y eso obliga a cruzar por texto con cualquier
 * sistema externo: el SNIES, la DIAN o el propio archivo del DANE. Cruzar por texto falla con las
 * tildes, con «Bogotá D.C.» frente a «Bogotá, D.C.» y con los nombres que el DANE ha ido cambiando
 * —«Cali» pasó a ser «Santiago de Cali»—. El código no cambia aunque el nombre sí.
 *
 * Las columnas son anulables a propósito: «Sin Departamento» es un centinela que usan los
 * formularios cuando la persona es del extranjero y no le corresponde ningún código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paises', function (Blueprint $tabla) {
            // Alfa-2 de la norma ISO 3166-1. Es la única que cubre las 249 entradas: alfa-3 y el
            // código numérico solo existen para los 193 estados miembros de la ONU, y dejarían en
            // blanco a Hong Kong, Puerto Rico o Groenlandia, que sí importan para un título
            // obtenido en el extranjero.
            $tabla->char('codigo_alfa2', 2)->nullable()->unique()->after('nombre');
        });

        Schema::table('departamentos', function (Blueprint $tabla) {
            $tabla->char('codigo_divipola', 2)->nullable()->after('nombre');
        });

        Schema::table('municipios', function (Blueprint $tabla) {
            $tabla->char('codigo_divipola', 5)->nullable()->after('nombre');

            // El DANE distingue municipio, área no municipalizada e isla. Los 18 territorios no
            // municipalizados del Amazonas, Guainía y Vaupés no son municipios, pero sí son el
            // lugar de nacimiento o residencia de alguien, así que tienen que aparecer en la lista
            // y a la vez poder distinguirse.
            $tabla->string('tipo', 40)->nullable()->after('codigo_divipola');

            $tabla->index('codigo_divipola');
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $tabla) {
            $tabla->dropIndex(['codigo_divipola']);
            $tabla->dropColumn(['codigo_divipola', 'tipo']);
        });

        Schema::table('departamentos', function (Blueprint $tabla) {
            $tabla->dropColumn('codigo_divipola');
        });

        Schema::table('paises', function (Blueprint $tabla) {
            $tabla->dropUnique(['codigo_alfa2']);
            $tabla->dropColumn('codigo_alfa2');
        });
    }
};
