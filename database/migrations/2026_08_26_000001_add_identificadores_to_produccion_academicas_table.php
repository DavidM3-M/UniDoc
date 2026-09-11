<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificadores de consulta de la producción académica.
 *
 * Hasta ahora lo único que decía dónde estaba publicada una producción era `medio_divulgacion`,
 * un texto libre que escribe el docente ("Revista UNAM"). Con eso, verificar que la publicación
 * existe obliga a copiar el título a un buscador y adivinar.
 *
 * Estas tres columnas son la materia prima de `EnlacesConsultaService`, que las convierte en los
 * enlaces a doi.org, Crossref, Google Scholar, Scimago y Publindex que ve el Evaluador de
 * Producción. Ninguna es obligatoria: las producciones ya registradas no tienen forma de
 * rellenarlas, y hay productos legítimos —libros, capítulos, eventos institucionales— que no
 * tienen DOI. El filtro «sin enlace de consulta» de la bandeja existe para medir cuántas van
 * quedando así antes de decidir si alguna se vuelve obligatoria.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('produccion_academicas', function (Blueprint $table) {
            // Se guarda el DOI desnudo ("10.21500/rces.2026.4187"), no la URL completa:
            // el resolvedor lo antepone y así el mismo dato sirve para Crossref.
            $table->string('doi', 255)->nullable()->after('medio_divulgacion');

            // ISSN (revistas) o ISBN (libros) en el mismo campo: nunca coexisten en una
            // producción y separarlos obligaría a dos columnas casi siempre vacías.
            $table->string('issn_isbn', 32)->nullable()->after('doi');

            // 500 y no 255: los enlaces de repositorios universitarios con parámetros de
            // sesión superan con facilidad los 255 caracteres.
            $table->string('url_publicacion', 500)->nullable()->after('issn_isbn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produccion_academicas', function (Blueprint $table) {
            $table->dropColumn(['doi', 'issn_isbn', 'url_publicacion']);
        });
    }
};
