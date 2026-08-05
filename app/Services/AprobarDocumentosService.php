<?php

namespace App\Services;
// Define el namespace del servicio, organizando el código y evitando conflictos de nombres.

use App\Models\Usuario\User;
// Importa el modelo `User` para interactuar con los datos del usuario.

class AprobarDocumentosService
// Define la clase `AprobarDocumentosService`, que contiene la lógica para aprobar documentos de un usuario.

{
    public function aprobarDocumentosDeUsuario(User $user): string
    // Define un método público que aprueba los documentos de un usuario y retorna un mensaje.

    {
        // Relaciones con modelos intermedios (excluyendo Producción Académica)
        $relaciones = [
            'estudiosUsuario'             => 'documentosEstudio',
            'experienciasUsuario'         => 'documentosExperiencia',
            'idiomasUsuario'              => 'documentosIdioma',
            'epsUsuario'                  => 'documentosEps',
            'informacionContactoUsuario'  => 'documentosInformacionContacto',
            'rutUsuario'                  => 'documentosRut',
            'fotoPerfilUsuario'           => 'documentosFotoPerfil',
        ];

        // Aprobar documentos en modelos intermedios
        foreach ($relaciones as $relacion => $metodoDocumentos) {
        // Itera sobre cada relación y su método asociado.

            $user->loadMissing($relacion);
             // Carga la relación si aún no ha sido cargada.

            $relacionData = $user->{$relacion};

            if (is_null($relacionData)) {
                continue;
            }
            // Si la relación no tiene datos, pasa a la siguiente iteración.
            $modelos = is_iterable($relacionData) ? $relacionData : [$relacionData];
            // Si los datos de la relación son iterables, los usa directamente; de lo contrario, los convierte en un arreglo.
            foreach ($modelos as $modelo) {
            // Itera sobre cada modelo relacionado.
                if (method_exists($modelo, $metodoDocumentos)) {
            // Verifica si el modelo tiene el método para obtener documentos.
                    $modelo->{$metodoDocumentos}()
                        ->where('estado', 'pendiente')
                        ->update(['estado' => 'aprobado']);
                    // Obtiene los documentos con estado "pendiente" y actualiza su estado a "aprobado".

                }
            }
        }

        // Aprobar documentos polimórficos directamente del usuario
        $user->documentosUser()
            ->where('estado', 'pendiente')
            ->update(['estado' => 'aprobado']);
        // Obtiene los documentos polimórficos del usuario con estado "pendiente" y actualiza su estado a "aprobado".
        return 'Los documentos han sido aprobados correctamente.';
        // Retorna un mensaje indicando que los documentos han sido aprobados.

    }
}
