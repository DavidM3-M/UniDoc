<?php

namespace App\Http\Controllers\ApoyoProfesoral;

use App\Services\CertificadoDocenteService;
use App\Http\Requests\RequestSecretaria\CrearCertificadosMasivosRequest;
use App\Http\Requests\RequestSecretaria\ActualizarCertificadoRequest;
use App\Models\Usuario\User;
use App\Models\Aspirante\Estudio;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ArchivoService;
use Illuminate\Http\File;



class GenerarCertificadosController
{

    protected $certificadoService;
    protected $archivoService;

    public function __construct(CertificadoDocenteService $certificadoService, ArchivoService $archivoService)
    {
        $this->certificadoService = $certificadoService;
        $this->archivoService = $archivoService;
    }

    public function listarDocentes()
    {
        try {
        // Inicia una consulta al modelo User, utilizando el método role('Docente') para filtrar únicamente
        // aquellos usuarios que tengan asignado el rol 'Docente'. Este método es proporcionado por el paquete
        // Spatie Laravel Permission y facilita la consulta de usuarios por roles.
            $docentes = User::role('Docente')
            // El método select permite especificar las columnas que se desean recuperar de la base de datos.
            // Aquí se seleccionan los siguientes campos:
            // - 'id': el identificador único del usuario.
            // - DB::raw("CONCAT(...) AS nombre_completo"): se utiliza una expresión SQL cruda para concatenar
            //   los nombres y apellidos del docente en un solo campo llamado 'nombre_completo'. Esto facilita
            //   la presentación del nombre completo en la respuesta.
            // - 'email': el correo electrónico del docente.
            // - 'numero_identificacion': el número de identificación del docente.
           
                ->select(
                    'id',
                    DB::raw("CONCAT(primer_nombre, ' ', segundo_nombre, ' ', primer_apellido, ' ', segundo_apellido) AS nombre_completo"),
                    'email',
                    'numero_identificacion'
                )
            // El método get ejecuta la consulta y recupera todos los resultados como una colección de objetos User.
                ->get();
        // Se retorna una respuesta JSON con los datos obtenidos.
        // El array incluye:
        // - 'data': contiene la colección de docentes recuperados.
        // - 'message': si la colección está vacía, se envía un mensaje indicando que no hay docentes registrados;
        //   en caso contrario, se envía una cadena vacía.
        // El segundo parámetro es el código de estado HTTP, en este caso 200 (OK).
            return response()->json([
                'data' => $docentes,
                'message' => $docentes->isEmpty() ? 'No hay docentes registrados.' : ''
            ], 200);
        } catch (\Exception $e) {
        // Si ocurre cualquier excepción durante la ejecución del bloque try, se captura aquí.
        // Se retorna una respuesta JSON indicando que ocurrió un error.
        // El array incluye:
        // - 'message': un mensaje genérico para el usuario.
        // - 'error': el mensaje específico de la excepción, útil para depuración.
        // El código de estado HTTP es 500 (Internal Server Error).
            return response()->json([
                'message' => 'Error al obtener los docentes.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function crearCertificadosMasivos(CrearCertificadosMasivosRequest $request)
    {
        foreach ($request->docentes as $docenteId) {
            // Verificar que el usuario tenga rol docente
            $docente = User::whereHas('roles', function ($q) {
                $q->where('name', 'Docente');
            })->find($docenteId);

            if (!$docente) {
                Log::warning("El usuario con ID $docenteId no tiene rol docente. Se omite.");
                continue;
            }

            // Generar el certificado PDF
            $pdfFile = $this->certificadoService->generarPDF([
                'docente_id'         => $docente->id,
                'nombre_docente'     => $docente->primer_nombre . ' ' . $docente->segundo_nombre . ' ' . $docente->primer_apellido . ' ' . $docente->segundo_apellido,
                'titulo_certificado' => $request->titulo_estudio,
                'fecha'              => Carbon::parse($request->fecha_fin)->translatedFormat('d \d\e F \d\e Y'),
            ]);

            // Crear estudio
            $estudio = Estudio::create([
                'user_id'                  => $docente->id,
                'tipo_estudio'             => $request->tipo_estudio,
                'graduado'                 => $request->graduado,
                'titulo_estudio'           => $request->titulo_estudio,
                'institucion'              => $request->institucion,
                'fecha_inicio'             => $request->fecha_inicio,
                'fecha_fin'                => $request->fecha_fin,
                'fecha_graduacion'         => $request->fecha_graduacion ?? $request->fecha_inicio,
                'titulo_convalidado'       => $request->titulo_convalidado,
                'fecha_convalidacion'      => $request->fecha_convalidacion,
                'resolucion_convalidacion' => $request->resolucion_convalidacion,
                'posible_fecha_graduacion' => $request->posible_fecha_graduacion,
                'es_certificado'           => true,
            ]);

            // Guardar documento asociado (polimórfico)
            $documento = $this->archivoService->guardarArchivoDocumento($pdfFile, $estudio, 'Estudios');
            $documento->update(['estado' => 'aprobado']);

            // Eliminar archivo temporal
           unlink($pdfFile->getRealPath());
        }

        return response()->json([
            'status'  => 'success',
            'mensaje' => 'Certificados generados y estudios registrados correctamente.'
        ]);
    }

    /**
     * Actualizar un certificado generado por Apoyo Profesoral.
     *
     * Solo opera sobre registros de estudio marcados como certificado (`es_certificado = true`),
     * de modo que este endpoint no pueda modificar los estudios reales de un docente. Tras
     * actualizar los datos, se regenera el PDF del certificado para que su contenido visible
     * quede consistente con la nueva información, reemplazando el documento asociado.
     *
     * @param ActualizarCertificadoRequest $request Datos validados del certificado.
     * @param int $id ID del estudio-certificado a actualizar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarCertificado(ActualizarCertificadoRequest $request, $id)
    {
        try {
            // Solo se permiten certificados generados (es_certificado = true), no estudios reales del docente.
            $estudio = Estudio::where('id_estudio', $id)
                ->where('es_certificado', true)
                ->firstOrFail();

            DB::transaction(function () use ($request, $estudio) {
                $estudio->update($request->validated()); // Actualiza los campos enviados

                // Regenerar el PDF con los datos ya actualizados
                $docente = $estudio->usuarioEstudio;
                $pdfFile = $this->certificadoService->generarPDF([
                    'docente_id'         => $docente->id,
                    'nombre_docente'     => $docente->primer_nombre . ' ' . $docente->segundo_nombre . ' ' . $docente->primer_apellido . ' ' . $docente->segundo_apellido,
                    'titulo_certificado' => $estudio->titulo_estudio,
                    'fecha'              => Carbon::parse($estudio->fecha_fin ?? $estudio->fecha_inicio)->translatedFormat('d \d\e F \d\e Y'),
                ]);

                // Reemplaza el documento anterior y lo deja aprobado (igual que en la creación)
                $documento = $this->archivoService->actualizarArchivoDocumento($pdfFile, $estudio, 'Estudios');
                $documento->update(['estado' => 'aprobado']);

                unlink($pdfFile->getRealPath()); // Elimina el PDF temporal
            });

            return response()->json([
                'status'  => 'success',
                'mensaje' => 'Certificado actualizado correctamente.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Certificado no encontrado.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el certificado.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un certificado generado por Apoyo Profesoral.
     *
     * Solo opera sobre registros marcados como certificado (`es_certificado = true`); elimina
     * el documento (PDF) asociado y luego el registro de estudio, dentro de una transacción.
     *
     * @param int $id ID del estudio-certificado a eliminar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminarCertificado($id)
    {
        try {
            $estudio = Estudio::where('id_estudio', $id)
                ->where('es_certificado', true)
                ->firstOrFail();

            DB::transaction(function () use ($estudio) {
                $this->archivoService->eliminarArchivoDocumento($estudio); // Borra el PDF y su registro
                $estudio->delete(); // Borra el estudio-certificado
            });

            return response()->json(['mensaje' => 'Certificado eliminado correctamente.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Certificado no encontrado.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el certificado.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
