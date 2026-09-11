<?php

namespace App\Services;

use App\Models\Aspirante\ProduccionAcademica;

/**
 * Convierte los identificadores de una producción académica en los enlaces donde el Evaluador de
 * Producción puede consultarla.
 *
 * El problema que resuelve: `produccion_academicas` guarda `medio_divulgacion` como texto libre
 * ("Revista UNAM", escrito a mano). Con eso, verificar que un artículo existe obliga a copiar el
 * título a un buscador. Acá el docente aporta tres datos cortos —`doi`, `issn_isbn`,
 * `url_publicacion`— y el sistema devuelve seis enlaces ya construidos.
 *
 * Dos reglas de diseño que conviene no perder:
 *
 * 1. **Nada se guarda en base de datos.** Los enlaces se calculan en cada consulta. Cuando
 *    Minciencias o Scimago cambien de dirección, se corrige acá y todas las producciones —incluidas
 *    las de 2019— quedan arregladas. Persistirlos habría dejado miles de URLs muertas.
 *
 * 2. **Directo y derivado no son lo mismo, y la respuesta lo dice.** Un enlace `directo` resuelve
 *    un dato que el docente afirmó; uno `derivado` es una búsqueda que arma el sistema y puede no
 *    acertar. Si la interfaz los mezclara, el evaluador acabaría tomando un resultado de Google
 *    Scholar por una verificación.
 *
 * Un enlace que no se puede construir viaja igual, con `disponible: false` y su motivo, en vez de
 * omitirse: el evaluador necesita ver qué le falta al registro, no solo lo que tiene.
 */
class EnlacesConsultaService
{
    /**
     * Plantillas de las fuentes derivadas. Se listan juntas a propósito: son las URL que hay que
     * revisar cuando alguno de estos servicios cambie de dirección.
     */
    private const RESOLVEDOR_DOI = 'https://doi.org/';
    private const CROSSREF       = 'https://search.crossref.org/search/works?q=%s&from_ui=yes';
    private const SCHOLAR        = 'https://scholar.google.com/scholar?q=%s';
    private const SCIMAGO        = 'https://www.scimagojr.com/journalsearch.php?q=%s';

    /**
     * Publindex no acepta parámetros de búsqueda: es una aplicación de una sola página y el ISSN
     * hay que escribirlo en su buscador. Se incluye igual porque es la clasificación que pesa en
     * Colombia, y el `criterio` que acompaña al enlace le dice al evaluador qué copiar.
     */
    private const PUBLINDEX = 'https://scienti.minciencias.gov.co/publindex/#/revistasPublindex';

    /**
     * Construye los seis enlaces de una producción.
     *
     * @return array<int, array{fuente: string, tipo: string, url: ?string, disponible: bool, criterio: ?string, motivo: ?string}>
     */
    public function para(ProduccionAcademica $produccion): array
    {
        return [
            $this->doi($produccion),
            $this->sitioPublicacion($produccion),
            $this->crossref($produccion),
            $this->scholar($produccion),
            $this->scimago($produccion),
            $this->publindex($produccion),
        ];
    }

    /**
     * ¿Hay al menos un enlace directo? Es lo que responde el filtro «sin enlace de consulta» de la
     * bandeja: una producción sin DOI ni URL solo se puede buscar por título.
     */
    public function tieneEnlaceDirecto(ProduccionAcademica $produccion): bool
    {
        return filled($produccion->doi) || filled($produccion->url_publicacion);
    }

    // -----------------------------------------------------------------
    // Enlaces directos: resuelven un dato que aportó el docente
    // -----------------------------------------------------------------

    private function doi(ProduccionAcademica $produccion): array
    {
        if (blank($produccion->doi)) {
            return $this->noDisponible('doi.org', 'directo', 'Sin DOI registrado');
        }

        // El DOI se guarda desnudo (ver NormalizaIdentificadoresProduccion). Las barras del
        // sufijo son parte del identificador y no se codifican; sí el resto, porque hay DOI
        // legítimos con paréntesis y espacios.
        $ruta = implode('/', array_map('rawurlencode', explode('/', $produccion->doi)));

        return $this->disponible(
            fuente: 'doi.org',
            tipo: 'directo',
            url: self::RESOLVEDOR_DOI . $ruta,
            criterio: $produccion->doi,
        );
    }

    private function sitioPublicacion(ProduccionAcademica $produccion): array
    {
        if (blank($produccion->url_publicacion)) {
            return $this->noDisponible('Sitio de la publicación', 'directo', 'Sin URL registrada');
        }

        return $this->disponible(
            fuente: 'Sitio de la publicación',
            tipo: 'directo',
            url: $produccion->url_publicacion,
            criterio: parse_url($produccion->url_publicacion, PHP_URL_HOST) ?: 'enlace del docente',
        );
    }

    // -----------------------------------------------------------------
    // Enlaces derivados: búsquedas que arma el sistema
    // -----------------------------------------------------------------

    /**
     * Crossref confirma los metadatos: autores, revista y fecha real de publicación. Con DOI da
     * una coincidencia exacta; sin DOI cae a búsqueda por título, que ya es una aproximación.
     */
    private function crossref(ProduccionAcademica $produccion): array
    {
        if (filled($produccion->doi)) {
            return $this->disponible(
                fuente: 'Crossref',
                tipo: 'derivado',
                url: sprintf(self::CROSSREF, rawurlencode($produccion->doi)),
                criterio: 'por DOI',
            );
        }

        if (blank($produccion->titulo)) {
            return $this->noDisponible('Crossref', 'derivado', 'Sin DOI ni título');
        }

        return $this->disponible(
            fuente: 'Crossref',
            tipo: 'derivado',
            url: sprintf(self::CROSSREF, rawurlencode($produccion->titulo)),
            criterio: 'por título',
        );
    }

    /**
     * La red de seguridad: es la única fuente que sirve igual para artículos, libros, capítulos y
     * ponencias, y solo necesita el título, que siempre está.
     */
    private function scholar(ProduccionAcademica $produccion): array
    {
        if (blank($produccion->titulo)) {
            return $this->noDisponible('Google Scholar', 'derivado', 'Sin título');
        }

        // Comillas dobles para que Scholar busque la frase exacta y no cada palabra suelta.
        return $this->disponible(
            fuente: 'Google Scholar',
            tipo: 'derivado',
            url: sprintf(self::SCHOLAR, rawurlencode('"' . $produccion->titulo . '"')),
            criterio: 'por título exacto',
        );
    }

    /**
     * Scimago da el cuartil de la revista, que es contra lo que el evaluador contrasta el ámbito
     * de divulgación declarado: si el docente registró "Revista indexada A1" y Scimago la ubica en
     * Q4, hay algo que revisar antes de otorgar 10 puntos.
     */
    private function scimago(ProduccionAcademica $produccion): array
    {
        if (filled($produccion->issn_isbn)) {
            // El buscador de Scimago espera el ISSN sin guiones.
            $issn = str_replace('-', '', $produccion->issn_isbn);

            return $this->disponible(
                fuente: 'Scimago',
                tipo: 'derivado',
                url: sprintf(self::SCIMAGO, rawurlencode($issn)),
                criterio: 'por ISSN ' . $produccion->issn_isbn,
            );
        }

        if (blank($produccion->medio_divulgacion)) {
            return $this->noDisponible('Scimago', 'derivado', 'Sin ISSN ni medio de divulgación');
        }

        // Buscar por el nombre escrito a mano acierta menos que por ISSN, pero para una revista
        // conocida suele bastar, y el `criterio` deja claro que es una aproximación.
        return $this->disponible(
            fuente: 'Scimago',
            tipo: 'derivado',
            url: sprintf(self::SCIMAGO, rawurlencode($produccion->medio_divulgacion)),
            criterio: 'por nombre del medio',
        );
    }

    /**
     * Publindex es la clasificación de Minciencias, la que rige en Colombia. Su buscador no admite
     * parámetros, así que el enlace abre el directorio y el criterio indica qué ISSN copiar.
     */
    private function publindex(ProduccionAcademica $produccion): array
    {
        if (blank($produccion->issn_isbn)) {
            return $this->noDisponible('Publindex', 'derivado', 'Sin ISSN registrado');
        }

        return $this->disponible(
            fuente: 'Publindex',
            tipo: 'derivado',
            url: self::PUBLINDEX,
            criterio: 'busque el ISSN ' . $produccion->issn_isbn,
        );
    }

    // -----------------------------------------------------------------
    // Constructores de la forma de respuesta
    // -----------------------------------------------------------------

    private function disponible(string $fuente, string $tipo, string $url, ?string $criterio = null): array
    {
        return [
            'fuente'     => $fuente,
            'tipo'       => $tipo,
            'url'        => $url,
            'disponible' => true,
            'criterio'   => $criterio,
            'motivo'     => null,
        ];
    }

    private function noDisponible(string $fuente, string $tipo, string $motivo): array
    {
        return [
            'fuente'     => $fuente,
            'tipo'       => $tipo,
            'url'        => null,
            'disponible' => false,
            'criterio'   => null,
            'motivo'     => $motivo,
        ];
    }
}
