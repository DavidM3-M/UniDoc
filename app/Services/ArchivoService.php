<?php

namespace App\Services;

use App\Constants\ConstDocumentos\EstadoDocumentos;
use App\Jobs\EnviarNotificacionJob;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\Log;
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

        $documento = Documento::create([
            'archivo' => str_replace('public/', '', $rutaArchivo),
            'estado'  => 'pendiente',
            'documentable_id' => $modelo->getKey(),
            'documentable_type' => get_class($modelo),
        ]);

        $this->avisarAlRevisor($modelo);

        return $documento;
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

        // Reemplazar el archivo devuelve el registro a la bandeja, asi que para el revisor es
        // trabajo nuevo igual que un alta: se avisa en los dos caminos.
        $this->avisarAlRevisor($modelo);

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

    /**
     * Avisa al revisor que corresponde de que hay trabajo nuevo esperando.
     *
     * El reparto no es configurable a proposito: la produccion academica la avala el Evaluador de
     * Produccion y todo lo demas —estudios, idiomas, experiencia— lo revisa Apoyo Profesoral. Es la
     * misma division que ya aplica `VerificacionDocumentosController`, que devuelve 403 si Apoyo
     * Profesoral intenta decidir sobre una produccion.
     *
     * Todo el metodo es best-effort: si algo falla aqui se registra y se sigue. Una subida no puede
     * quedarse a medias porque el aviso al revisor no se pudo encolar; el documento ya esta guardado
     * y aparece en la bandeja igual.
     */
    private function avisarAlRevisor($modelo): void
    {
        try {
            $duenio = $modelo->user_id ? User::find($modelo->user_id) : null;

            if (!$duenio) {
                return;
            }

            $esProduccion = str_contains(get_class($modelo), 'ProduccionAcademica');
            $rolRevisor   = $esProduccion ? 'Evaluador Produccion' : 'Apoyo Profesoral';

            $docente = trim(($duenio->primer_nombre ?? '') . ' ' . ($duenio->primer_apellido ?? ''));
            $docente = $docente !== '' ? $docente : ($duenio->email ?? 'Un docente');

            [$categoria, $descripcion] = $this->describir($modelo, $esProduccion);

            foreach (User::role($rolRevisor)->get() as $revisor) {
                EnviarNotificacionJob::dispatch(
                    // El instante entra en la clave porque volver a subir el mismo registro es un
                    // hecho nuevo que el revisor tiene que ver, no un duplicado que haya que callar.
                    'documento.subido:' . $modelo->getKey() . ':' . get_class($modelo) . ':' . $revisor->id . ':' . now()->timestamp,
                    'documento.subido',
                    'documentoSubido',
                    [$docente, $categoria, $descripcion, $esProduccion],
                    $revisor->id,
                )->afterCommit();
            }
        } catch (\Throwable $e) {
            Log::error('No se pudo avisar al revisor de una subida: ' . $e->getMessage());
        }
    }

    /**
     * Nombre legible de la categoria y del registro concreto.
     *
     * Cada modelo guarda su titulo en una columna distinta, y sin esto el correo diria «subio un
     * documento» sin decir cual, que es justo el problema que tenia el aviso viejo de rechazo.
     */
    private function describir($modelo, bool $esProduccion): array
    {
        if ($esProduccion) {
            return ['Producción académica', $modelo->titulo ?? 'Sin título'];
        }

        $clase = class_basename($modelo);

        return match ($clase) {
            'Estudio'     => ['Estudio', $modelo->titulo_estudio ?? 'Sin título'],
            'Idioma'      => ['Idioma', trim(($modelo->idioma ?? '') . ' · ' . ($modelo->institucion_idioma ?? ''))],
            'Experiencia' => ['Experiencia', trim(($modelo->cargo ?? '') . ' · ' . ($modelo->institucion_experiencia ?? ''))],
            default       => [$clase, 'Registro ' . $modelo->getKey()],
        };
    }
}
