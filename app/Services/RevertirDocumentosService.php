<?php

namespace App\Services;

use App\Models\Usuario\User;

class RevertirDocumentosService
{
    public function revertirDocumentosDeUsuario(User $user): string
        // Retorna un mensaje indicando que los documentos han sido aprobados.
    {
        $relaciones = [
            'estudiosUsuario'             => 'documentosEstudio',
            'experienciasUsuario'         => 'documentosExperiencia',
            'idiomasUsuario'              => 'documentosIdioma',
            'produccionAcademicaUsuario'  => 'documentosProduccionAcademica',
            'epsUsuario'                  => 'documentosEps',
            'informacionContactoUsuario'  => 'documentosInformacionContacto',
            'rutUsuario'                  => 'documentosRut',
            'fotoPerfilUsuario'           => 'documentosFotoPerfil',
        ];
        // Define un arreglo que mapea las relaciones del usuario con los métodos correspondientes para obtener documentos.
        foreach ($relaciones as $relacion => $metodoDocumentos) {
        // Itera sobre cada relación y su método asociado.
            $user->loadMissing($relacion);
        // Carga la relación si aún no ha sido cargada.
            $relacionData = $user->{$relacion};
        // Obtiene los datos de la relación.
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
                        ->where('estado', 'aprobado')
                        ->update(['estado' => 'pendiente']);
        // Obtiene los documentos con estado "aprobado" y actualiza su estado a "pendiente".
                }
            }
        }

        // Revertir documentos directamente en el usuario
        $user->documentosUser()
            ->where('estado', 'aprobado')
            ->update(['estado' => 'pendiente']);
        // Obtiene los documentos polimórficos del usuario con estado "aprobado" y actualiza su estado a "pendiente".

        return 'Los documentos han sido devueltos al estado pendiente correctamente.';
        // Retorna un mensaje indicando que los documentos han sido devueltos al estado pendiente.
    }
}