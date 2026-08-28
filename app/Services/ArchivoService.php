<?php

namespace App\Services;

use App\Constants\ConstDocumentos\EstadoDocumentos;
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
     * Devolver a revisión los documentos de un registro que acaba de editarse.
     *
     * El aval lo dio un revisor sobre unos datos concretos (el título de un estudio, las fechas
     * de una experiencia, el puntaje de un examen de idioma). Si el docente los cambia después,
     * esa decisión ya no dice nada sobre lo que hay guardado ahora, así que el documento vuelve
     * a la bandeja como `pendiente`.
     *
     * Se limpian también el motivo y la firma del revisor: dejar el motivo de un rechazo viejo
     * junto a un estado `pendiente` haría que la interfaz mostrara un reproche que ya no aplica,
     * y `Documento::esDecisionHistorica()` lee justamente `revisado_por` para distinguir «nadie
     * lo ha revisado» de «se revisó antes de la trazabilidad».
     *
     * Se actualizan todos los documentos del registro —no solo los ya decididos— porque una
     * producción académica puede tener varios archivos y deben quedar en el mismo estado.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $modelo Registro cuyos documentos se reabren.
     * @return int Cantidad de documentos devueltos a revisión.
     */
    public function reabrirRevisionDocumentos($modelo): int
    {
        return Documento::where('documentable_id', $modelo->getKey())
            ->where('documentable_type', get_class($modelo))
            ->update([
                'estado'         => EstadoDocumentos::PENDIENTE,
                'motivo_rechazo' => null,
                'revisado_por'   => null,
                'revisado_en'    => null,
            ]);
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
