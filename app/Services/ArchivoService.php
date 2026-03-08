<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Models\Aspirante\Documento;
use Illuminate\Support\Str;

class ArchivoService
{
    /**
     * Guardar archivo y registrar documento.
     */
    public function guardarArchivoDocumento($archivo, $modelo, $carpeta)
    {
        $extension = $archivo->getClientOriginalExtension(); // la extensión ya fue validada por la regla `mimes`
        $nombreArchivo = Str::uuid() . '.' . $extension;     // nombre aleatorio, sin datos del cliente
        $rutaArchivo = $archivo->storeAs("documentos/{$carpeta}", $nombreArchivo, 'public');

        return Documento::create([
            'archivo' => str_replace('public/', '', $rutaArchivo),
            'estado'  => 'pendiente',
            'documentable_id' => $modelo->getKey(),
            'documentable_type' => get_class($modelo),
        ]);
    }

    /**
     * Actualizar archivo existente o crear nuevo si no existe.
     */
    public function actualizarArchivoDocumento($archivo, $modelo, $carpeta)
    {
        $documento = Documento::where('documentable_id', $modelo->getKey())
            ->where('documentable_type', get_class($modelo))
            ->first();

        $extension = $archivo->getClientOriginalExtension(); // validada por `mimes`
        $nombreArchivo = Str::uuid() . '.' . $extension;     // nombre aleatorio
        $rutaArchivo = $archivo->storeAs("documentos/{$carpeta}", $nombreArchivo, 'public');

        if ($documento) {
            Storage::disk('public')->delete($documento->archivo);
            $documento->update([
                'archivo' => str_replace('public/', '', $rutaArchivo),
                'estado'  => 'pendiente',
            ]);
        } else {
            $documento = Documento::create([
                'archivo' => str_replace('public/', '', $rutaArchivo),
                'estado'  => 'pendiente',
                'documentable_id' => $modelo->getKey(),
                'documentable_type' => get_class($modelo),
            ]);
        }

        return $documento;
    }

    /**
     * Eliminar archivo y su registro asociado.
     */
    public function eliminarArchivoDocumento($modelo)
    {
        $documento = Documento::where('documentable_id', $modelo->getKey())
            ->where('documentable_type', get_class($modelo))
            ->first();

        if ($documento) {
            Storage::disk('public')->delete($documento->archivo);
            return $documento->delete();
        }

        return false;
    }
}
