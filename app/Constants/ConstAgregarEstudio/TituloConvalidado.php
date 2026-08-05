<?php

namespace App\Constants\ConstAgregarEstudio;
// Esta clase define constantes relacionadas con la **convalidación de un título académico**.
// La convalidación es el proceso mediante el cual un título obtenido en el extranjero
// es reconocido oficialmente por el país para tener validez legal.

class TituloConvalidado
{
    // Constante que indica que el título **sí fue convalidado**
    const SI = 'Si';
    // Constante que indica que el título **no fue convalidado**
    const NO = 'No';

    // Método estático que retorna un array con todas las opciones posibles (Sí y No).
    // Este método se utiliza, por ejemplo, para cargar listas desplegables (selects),
    // validar formularios o mostrar las opciones al usuario.
    public static function all(): array
    {
        return [
            self::SI,
            self::NO,
        ];
    }
}