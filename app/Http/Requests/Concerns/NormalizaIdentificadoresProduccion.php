<?php

namespace App\Http\Requests\Concerns;

/**
 * Reglas y limpieza de los tres identificadores con los que se verifica una producción académica:
 * `doi`, `issn_isbn` y `url_publicacion`.
 *
 * Vive en un trait porque `CrearProduccionAcademicaRequest` y `ActualizarProduccionAcademicaRequest`
 * necesitan exactamente lo mismo salvo el `sometimes`, y tener las expresiones regulares escritas
 * dos veces garantiza que tarde o temprano se desincronicen.
 *
 * Los tres campos son opcionales a propósito. Un libro o una ponencia institucional no tienen DOI,
 * y las producciones ya registradas no tienen forma de rellenarlos; exigirlos dejaría fuera casos
 * legítimos. El filtro «sin enlace de consulta» de la bandeja del evaluador mide cuántas van
 * quedando sin identificadores, que es el dato que hace falta para decidir si alguno se endurece.
 */
trait NormalizaIdentificadoresProduccion
{
    /**
     * Prefijo de un DOI: siempre "10." seguido del registrante y una barra.
     * Cubre desde 10.1000 hasta los registrantes largos que ya existen.
     */
    private const PATRON_DOI = 'regex:/^10\.\d{4,9}\/\S+$/';

    /**
     * ISSN (8 caracteres, el último puede ser X) o ISBN (10 o 13 dígitos), con o sin guiones.
     * Se valida la forma, no el dígito verificador: un ISSN mal tecleado se detecta al abrir el
     * enlace, y rechazar aquí por checksum frustraría al docente sin ganar nada verificable.
     */
    private const PATRON_ISSN_ISBN = 'regex:/^[0-9]{4}-?[0-9]{3}[0-9Xx]$|^(97[89]-?)?[0-9]{1,5}-?[0-9]+-?[0-9]+-?[0-9Xx]$/';

    /**
     * Reglas de los tres campos.
     *
     * @param bool $parcial `true` en actualización, donde el campo puede no venir en el cuerpo.
     * @return array<string, array<int, string>>
     */
    protected function reglasIdentificadores(bool $parcial = false): array
    {
        $presencia = $parcial ? ['sometimes'] : [];

        return [
            'doi' => array_merge($presencia, [
                'nullable',
                'string',
                'max:255',
                self::PATRON_DOI,
            ]),
            'issn_isbn' => array_merge($presencia, [
                'nullable',
                'string',
                'max:32',
                self::PATRON_ISSN_ISBN,
            ]),
            'url_publicacion' => array_merge($presencia, [
                'nullable',
                'string',
                'max:500',
                // Sin esquema explícito, `url` acepta "javascript:..." y "data:...", que la ficha
                // del evaluador renderiza como un enlace pulsable.
                'url:http,https',
            ]),
        ];
    }

    /**
     * Mensajes en español para los tres patrones: el mensaje por defecto de `regex` ("el formato
     * no es válido") no le dice al docente qué se espera que escriba.
     *
     * @return array<string, string>
     */
    protected function mensajesIdentificadores(): array
    {
        return [
            'doi.regex'             => 'El DOI debe tener la forma 10.xxxx/identificador. Puede pegar el enlace completo de doi.org.',
            'issn_isbn.regex'       => 'El ISSN debe tener 8 caracteres (por ejemplo 2145-9088) y el ISBN 10 o 13 dígitos.',
            'url_publicacion.url'   => 'El enlace debe empezar por http:// o https://.',
        ];
    }

    /**
     * Deja los tres campos en su forma canónica antes de validarlos.
     *
     * El docente casi siempre pega el DOI como la URL completa que le da la revista
     * (`https://doi.org/10.21500/rces.2026.4187`). Guardar esa URL rompería Crossref, que espera
     * el identificador desnudo, y obligaría a recortarla en cada consumidor. Se recorta una vez,
     * acá, y `EnlacesConsultaService` reconstruye la URL cuando la necesita.
     *
     * Un campo que llega vacío se convierte a null en vez de guardar cadena vacía: `filled()`, que
     * es lo que usan el filtro «sin enlace» y el servicio de enlaces, trata "" como ausente, pero
     * `''` y `null` distintos en base de datos harían que dos producciones igual de incompletas
     * se comportaran distinto.
     */
    protected function normalizarIdentificadores(): array
    {
        $normalizado = [];

        if ($this->has('doi')) {
            $doi = trim((string) $this->input('doi'));
            // Quita el resolvedor (doi.org, dx.doi.org) o el prefijo "doi:" en cualquier
            // combinación de mayúsculas, y deja lo que empieza en "10.".
            $doi = preg_replace('#^\s*(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $doi) ?? $doi;
            $normalizado['doi'] = $doi === '' ? null : $doi;
        }

        if ($this->has('issn_isbn')) {
            // La X final del dígito verificador se escribe en mayúscula por convención.
            $issn = strtoupper(trim((string) $this->input('issn_isbn')));
            $normalizado['issn_isbn'] = $issn === '' ? null : $issn;
        }

        if ($this->has('url_publicacion')) {
            $url = trim((string) $this->input('url_publicacion'));
            $normalizado['url_publicacion'] = $url === '' ? null : $url;
        }

        return $normalizado;
    }
}
