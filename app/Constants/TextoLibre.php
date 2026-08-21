<?php

namespace App\Constants;

/**
 * Expresión regular para los campos de texto libre que guardan nombres propios: instituciones,
 * títulos, programas, resoluciones.
 *
 * Bloquea lo que no queremos (emojis y caracteres de control) en vez de listar lo permitido.
 * La versión anterior hacía lo contrario —solo letras, números, espacios y guiones— y con eso
 * rechazaba datos reales del catálogo oficial: un docente que elegía "DOCTORADO EN
 * BIOINGENIERIA" del SNIES recibía "el formato no es válido" porque el título otorgado es
 * "DOCTOR/A/E EN BIOINGENIERIA" y la barra no estaba permitida.
 *
 * Los nombres reales de instituciones y programas del SNIES contienen
 * `" # & ' ( ) * + , - . / : ; ? @ _ ¿ –`, así que cualquier lista de caracteres permitidos se
 * queda corta apenas llega un dato nuevo.
 *
 * Categorías bloqueadas: `Cc` control, `Cf` formato (ZWJ y similares), `Co` uso privado,
 * `Cs` sustitutos, `So` símbolos varios — que es donde viven los emojis.
 *
 * No lleva comas ni barras, así que se puede seguir usando dentro de las reglas de validación
 * escritas como string separado por `|` (Laravel corta los parámetros por coma).
 */
class TextoLibre
{
    public const SIN_EMOJIS = 'regex:/^[^\p{Cc}\p{Cf}\p{Co}\p{Cs}\p{So}]+$/u';
}
