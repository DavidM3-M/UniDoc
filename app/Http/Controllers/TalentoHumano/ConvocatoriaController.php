<?php

namespace App\Http\Controllers\TalentoHumano;


use App\Http\Requests\RequestTalentoHumano\RequestConvocatoria\ActualizarConvocatoriaRequest;
use App\Http\Requests\RequestTalentoHumano\RequestConvocatoria\CrearConvocatoriaRequest;
use App\Models\TalentoHumano\Convocatoria;
use Illuminate\Support\Facades\DB;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ConvocatoriaController
{
    protected $archivoService;

    /**
     * Constructor del controlador de convocatorias.
     *
     * Inyecta el servicio `ArchivoService`, utilizado para gestionar operaciones de almacenamiento,
     * actualización y eliminación de archivos relacionados con las convocatorias.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos asociados a las convocatorias.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Crear una nueva convocatoria.
     *
     * Este método permite registrar una nueva convocatoria en el sistema.
     * La operación se ejecuta dentro de una transacción para garantizar la integridad de los datos.
     * Si se adjunta un archivo (como los términos o reglamentos de la convocatoria), este se almacena
     * mediante el servicio `ArchivoService` y se asocia al registro de la convocatoria.
     * En caso de producirse un error durante la operación, se captura la excepción y se retorna
     * una respuesta con el mensaje correspondiente.
     *
     * @param CrearConvocatoriaRequest $request Solicitud validada con los datos de la convocatoria y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearConvocatoria(CrearConvocatoriaRequest $request)
    {
        try {
            DB::transaction(function () use ($request) { // Inicio de la transacción

                $datosConvocatoria = $request->validated(); // Validamos los datos de la solicitud
                
                // Removemos el campo 'archivo' si existe, ya que no es un campo de BD
                if (isset($datosConvocatoria['archivo'])) {
                    unset($datosConvocatoria['archivo']);
                }
                
                $convocatoria = Convocatoria::create($datosConvocatoria); // Creamos la convocatoria en la base de datos

                if ($request->hasFile('archivo')) { // Verificamos si se ha subido un archivo

                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $convocatoria, 'Convocatorias'); // Guardamos el archivo asociado a la convocatoria
                }
            });

            return response()->json([ // Retornamos una respuesta JSON
                'mensaje' => 'Convocatoria creada exitosamente',
            ], 201);
        } catch (\Exception $e) { // manejamos cualquier excepción que ocurra durante la transacción
            return response()->json([
                'mensaje' => 'Error al crear la convocatoria',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una convocatoria existente.
     *
     * Este método permite modificar los datos de una convocatoria previamente registrada, identificada por su ID.
     * La operación se realiza dentro de una transacción para asegurar la integridad de los datos.
     * Si se adjunta un nuevo archivo (como una versión actualizada del documento de convocatoria),
     * este se reemplaza utilizando el servicio `ArchivoService`.
     * En caso de que la convocatoria no exista o se produzca un error durante la operación,
     * se captura la excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param ActualizarConvocatoriaRequest $request Solicitud validada con los nuevos datos de la convocatoria y archivo opcional.
     * @param int $id ID de la convocatoria que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarConvocatoria(ActualizarConvocatoriaRequest $request, $id)
    {
        try {
            DB::transaction(function () use ($request, $id) { // Inicio de la transacción
                $convocatoria = Convocatoria::findOrFail($id); // Buscamos la convocatoria por su ID
                
                $datosActualizacion = $request->validated(); 
                
                // Removemos el campo 'archivo' si existe, ya que no es un campo de BD
                if (isset($datosActualizacion['archivo'])) {
                    unset($datosActualizacion['archivo']);
                }
                
                $convocatoria->update($datosActualizacion); // Actualizamos la convocatoria con los datos validados

                if ($request->hasFile('archivo')) { // Verificamos si se ha subido un archivo
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $convocatoria, 'Convocatorias'); // Actualizamos el archivo asociado a la convocatoria
                }
            });

            return response()->json([ // Retornamos una respuesta JSON
                'mensaje' => 'Convocatoria actualizada exitosamente',
            ], 200);
        } catch (\Exception $e) { // manejamos cualquier excepción que
            return response()->json([
                'mensaje' => 'Error al actualizar la convocatoria',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una convocatoria existente.
     *
     * Este método permite eliminar una convocatoria del sistema, identificada por su ID.
     * Antes de eliminar el registro, se eliminan los archivos asociados utilizando el servicio `ArchivoService`.
     * Toda la operación se realiza dentro de una transacción para asegurar la consistencia de los datos.
     * En caso de que la convocatoria no exista o se produzca un error durante la eliminación,
     * se captura una excepción y se retorna una respuesta adecuada.
     *
     * @param int $id ID de la convocatoria que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarConvocatoria($id)
    {
        try {
            $convocatoria = Convocatoria::findOrFail($id); // Buscamos la convocatoria por su ID
            DB::transaction(function () use ($convocatoria) { // Inicio de la transacción
                $convocatoria->postulacionesConvocatoria()->delete();
                $this->archivoService->eliminarArchivoDocumento($convocatoria); // Eliminamos el archivo asociado a la convocatoria
                $convocatoria->delete(); // Eliminamos la convocatoria de la base de datos
            });

            return response()->json(['mensaje' => 'Convocatoria eliminada exitosamente'], 200); // Retornamos una respuesta JSON indicando que la convocatoria fue eliminada exitosamente

        } catch (\Exception $e) { // manejamos cualquier excepción que ocurra durante la transacción
            return response()->json([
                'mensaje' => 'Error al eliminar la convocatoria',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las convocatorias registradas con sus documentos asociados.
     *
     * Este método recupera todas las convocatorias disponibles en el sistema, incluyendo los documentos
     * relacionados mediante la relación `documentosConvocatoria`. Las convocatorias se ordenan por su
     * fecha de creación en orden descendente. Para cada documento, se genera la URL pública del archivo
     * utilizando el helper `asset()`. Si no se encuentran convocatorias, se lanza una excepción con código 404.
     * En caso de error, se captura la excepción y se retorna una respuesta adecuada.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de convocatorias o mensaje de error.
     */
    /**
 * Obtener todas las convocatorias registradas.
 */
public function obtenerConvocatorias()
{
    try {
        // Obtener convocatorias CON relaciones cargadas
        $convocatorias = Convocatoria::with(['tipoCargo', 'facultad', 'perfilProfesional', 'experienciaRequerida', 'documentosConvocatoria'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($convocatorias->isEmpty()) {
            return response()->json([
                'mensaje' => 'No se encontraron convocatorias',
                'convocatorias' => []
            ], 200);
        }

        // Transformar datos para asegurar que todos los campos existan
        $convocatoriasTransformadas = $convocatorias->map(function ($conv) {
            // Obtener documentos de la convocatoria
            $documentos = $conv->documentosConvocatoria->map(function ($doc) {
                return [
                    'id_documento' => $doc->id_documento,
                    'archivo' => $doc->archivo,
                    'estado' => $doc->estado ?? '',
                    'url' => asset('storage/' . $doc->archivo),
                ];
            })->toArray();

            return [
                'id_convocatoria' => $conv->id_convocatoria,
                'numero_convocatoria' => $conv->numero_convocatoria ?? 'CONV-' . $conv->id_convocatoria,
                'nombre_convocatoria' => $conv->nombre_convocatoria,
                'tipo' => $conv->tipo,
                'periodo_academico' => $conv->periodo_academico ?? 'No especificado',
                'tipo_cargo_id' => $conv->tipo_cargo_id,
                'tipo_cargo_otro' => $conv->tipo_cargo_otro ?? '',
                'cargo_solicitado' => $conv->tipo_cargo_id ? $conv->tipo_cargo_id : ($conv->tipo_cargo_otro ?? ''),
                'perfil_profesional_id' => $conv->perfil_profesional_id,
                'perfil_profesional_outro' => $conv->perfil_profesional_outro ?? '',
                'perfil_profesional' => $conv->perfil_profesional_outro ?? ($conv->perfilProfesional ? ($conv->perfilProfesional->nombre_perfil ?? 'No especificado') : 'No especificado'),
                'facultad_id' => $conv->facultad_id,
                'facultad_outro' => $conv->facultad_outro ?? '',
                'facultad' => $conv->facultad_otro ?? ($conv->facultad ? $conv->facultad->nombre_facultad : 'No especificado'),
                'cursos' => $conv->cursos ?? 'No especificado',
                'tipo_vinculacion' => $conv->tipo_vinculacion ?? 'No especificado',
                'personas_requeridas' => $conv->personas_requeridas ?? 1,
                'fecha_publicacion' => $conv->fecha_publicacion,
                'fecha_cierre' => $conv->fecha_cierre,
                'fecha_inicio_contrato' => $conv->fecha_inicio_contrato,
                'experiencia_requerida' => $conv->experiencia_requerida_fecha ? ($conv->experiencia_requerida_fecha instanceof \Illuminate\Support\Carbon ? $conv->experiencia_requerida_fecha->toDateString() : $conv->experiencia_requerida_fecha) : ($conv->experienciaRequerida ? $conv->experienciaRequerida->descripcion_experiencia : ''),
                'anos_experiencia_requerida' => $conv->anos_experiencia_requerida,
                'tipo_experiencia_requerida' => $conv->tipo_experiencia_requerida,
                'cantidad_experiencia' => $conv->cantidad_experiencia,
                'unidad_experiencia' => $conv->unidad_experiencia,
                'referencia_experiencia' => $conv->referencia_experiencia,
                'experiencia_completa' => $this->formatearExperienciaCompleta($conv),
                'requisitos_experiencia' => $conv->requisitos_experiencia ?? [],
                'requisitos_idiomas' => $conv->requisitos_idiomas ?? [],
                'requisitos_adicionales' => $conv->requisitos_adicionales ?? [],
                'solicitante' => $conv->solicitante ?? 'Talento Humano',
                'avales_establecidos' => $conv->avales_establecidos ?? [],
                'descripcion' => $conv->descripcion,
                'estado_convocatoria' => $conv->estado_convocatoria,
                'created_at' => $conv->created_at,
                'updated_at' => $conv->updated_at,
                'documentos_convocatoria' => $documentos,
            ];
        });

        return response()->json(['convocatorias' => $convocatoriasTransformadas], 200);

    } catch (\Exception $e) {
        Log::error('Error al obtener convocatorias: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());

        return response()->json([
            'mensaje' => 'Error al obtener las convocatorias',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}

    /**
     * Obtener una convocatoria específica por su ID.
     *
     * Este método busca una convocatoria mediante su ID y carga los documentos asociados a ella
     * utilizando la relación `documentosConvocatoria`. Para cada documento que tenga un archivo,
     * se genera una URL pública utilizando el helper `asset()`. Si la convocatoria no existe,
     * se lanza una excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @param int $id ID de la convocatoria que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la información de la convocatoria o mensaje de error.
     */
    public function obtenerConvocatoriaPorId($id)
{
    try {
        // Buscar la convocatoria CON relaciones
        $convocatoria = Convocatoria::with(['tipoCargo', 'facultad', 'perfilProfesional', 'experienciaRequerida', 'documentosConvocatoria'])->find($id);

        if (!$convocatoria) {
            return response()->json([
                'mensaje' => 'Convocatoria no encontrada',
                'error' => 'La convocatoria solicitada no existe'
            ], 404);
        }

        // Obtener documentos de la convocatoria
        $documentos = $convocatoria->documentosConvocatoria->map(function ($doc) {
            return [
                'id_documento' => $doc->id_documento,
                'archivo' => $doc->archivo,
                'estado' => $doc->estado ?? '',
                'url' => asset('storage/' . $doc->archivo),
            ];
        })->toArray();

        // Transformar datos para asegurar que todos los campos existan
        $convocatoriaTransformada = [
            'id_convocatoria' => $convocatoria->id_convocatoria,
            'numero_convocatoria' => $convocatoria->numero_convocatoria ?? 'CONV-' . $convocatoria->id_convocatoria,
            'nombre_convocatoria' => $convocatoria->nombre_convocatoria,
            'tipo' => $convocatoria->tipo,
            'periodo_academico' => $convocatoria->periodo_academico ?? '',
            
            // Cargo Solicitado: retorna ID si existe en BD, si no retorna el texto personalizado
            'tipo_cargo_id' => $convocatoria->tipo_cargo_id,
            'tipo_cargo_otro' => $convocatoria->tipo_cargo_otro ?? '',
            'cargo_solicitado' => $convocatoria->tipo_cargo_id ? $convocatoria->tipo_cargo_id : ($convocatoria->tipo_cargo_otro ?? ''),
            
            'facultad_id' => $convocatoria->facultad_id,
            'facultad_otro' => $convocatoria->facultad_otro ?? '',
            'facultad' => $convocatoria->facultad_otro ?? ($convocatoria->facultad ? $convocatoria->facultad->nombre_facultad : ''),
            'cursos' => $convocatoria->cursos ?? '',
            'tipo_vinculacion' => $convocatoria->tipo_vinculacion ?? '',
            'personas_requeridas' => $convocatoria->personas_requeridas ?? 1,
            'fecha_publicacion' => $convocatoria->fecha_publicacion,
            'fecha_cierre' => $convocatoria->fecha_cierre,
            'fecha_inicio_contrato' => $convocatoria->fecha_inicio_contrato,
            'perfil_profesional_id' => $convocatoria->perfil_profesional_id,
            'perfil_profesional_outro' => $convocatoria->perfil_profesional_outro ?? '',
            'perfil_profesional' => $convocatoria->perfil_profesional_outro ?? ($convocatoria->perfilProfesional ? ($convocatoria->perfilProfesional->nombre_perfil ?? '') : ''),
            'experiencia_requerida_id' => $convocatoria->experiencia_requerida_id,
            'experiencia_requerida_fecha' => $convocatoria->experiencia_requerida_fecha ? ($convocatoria->experiencia_requerida_fecha instanceof \Illuminate\Support\Carbon ? $convocatoria->experiencia_requerida_fecha->toDateString() : $convocatoria->experiencia_requerida_fecha) : null,
            'experiencia_requerida' => $convocatoria->experiencia_requerida_fecha ? ($convocatoria->experiencia_requerida_fecha instanceof \Illuminate\Support\Carbon ? $convocatoria->experiencia_requerida_fecha->toDateString() : $convocatoria->experiencia_requerida_fecha) : ($convocatoria->experienciaRequerida ? $convocatoria->experienciaRequerida->descripcion_experiencia : ''),
            'anos_experiencia_requerida' => $convocatoria->anos_experiencia_requerida,
            'tipo_experiencia_requerida' => $convocatoria->tipo_experiencia_requerida,
            'cantidad_experiencia' => $convocatoria->cantidad_experiencia,
            'unidad_experiencia' => $convocatoria->unidad_experiencia,
            'referencia_experiencia' => $convocatoria->referencia_experiencia,
            'experiencia_completa' => $this->formatearExperienciaCompleta($convocatoria),
            'requisitos_experiencia' => $convocatoria->requisitos_experiencia ?? [],
            'requisitos_idiomas' => $convocatoria->requisitos_idiomas ?? [],
            'requisitos_adicionales' => $convocatoria->requisitos_adicionales ?? [],
            'solicitante' => $convocatoria->solicitante ?? '',
            'avales_establecidos' => $convocatoria->avales_establecidos ?? [],
            'descripcion' => $convocatoria->descripcion ?? '',
            'estado_convocatoria' => $convocatoria->estado_convocatoria,
            'created_at' => $convocatoria->created_at,
            'updated_at' => $convocatoria->updated_at,
            // Documentos cargados desde la relación
            'documentos_convocatoria' => $documentos,
        ];

        return response()->json(['convocatoria' => $convocatoriaTransformada], 200);

    } catch (\Exception $e) {
        Log::error('Error al obtener convocatoria por ID: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());

        return response()->json([
            'mensaje' => 'Error al obtener la convocatoria',
            'error' => $e->getMessage()
        ], 500);
    }
}
    //logica exportar excel (Brayan Cuellar)
    /**
 * Exportar todas las convocatorias a un archivo Excel.
 *
 * Este método genera un archivo Excel con todas las convocatorias registradas en el sistema,
 * incluyendo información detallada como: Estado, Nombre, Tipo, Fecha de publicación,
 * Fecha de cierre y Descripción. El archivo se genera con estilos profesionales similares
 * al formato de Novedad 2025.
 *
 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse Descarga directa del archivo Excel.
 */
public function exportarConvocatoriasExcel()
{
    try {
        // Obtener todas las convocatorias ordenadas por fecha de creación
        $convocatorias = Convocatoria::orderBy('created_at', 'desc')->get();

        // Crear un nuevo Spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configurar propiedades del documento
        $spreadsheet->getProperties()
            ->setCreator('Sistema UniDoc')
            ->setTitle('Reporte de Convocatorias')
            ->setSubject('Convocatorias')
            ->setDescription('Listado de convocatorias del sistema');

        // ENCABEZADO PRINCIPAL
        $sheet->setCellValue('A1', 'REPORTE DE CONVOCATORIAS');
        $sheet->mergeCells('A1:F1');

        // Estilo del encabezado principal
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // INFORMACIÓN ADICIONAL
        $fechaGeneracion = date('d/m/Y H:i:s');
        $sheet->setCellValue('A2', 'Fecha de generación:');
        $sheet->setCellValue('B2', $fechaGeneracion);
        $sheet->setCellValue('A3', 'Total de convocatorias:');
        $sheet->setCellValue('B3', $convocatorias->count());

        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        // ENCABEZADOS DE COLUMNAS
        $headers = [
            'A5' => 'ESTADO',
            'B5' => 'NOMBRE DE CONVOCATORIA',
            'C5' => 'TIPO',
            'D5' => 'FECHA DE PUBLICACIÓN',
            'E5' => 'FECHA DE CIERRE',
            'F5' => 'DESCRIPCIÓN',
        ];

        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        // Estilo de los encabezados
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E75B5'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(30);

        // DATOS DE LAS CONVOCATORIAS
        $row = 6;
        foreach ($convocatorias as $convocatoria) {
            $sheet->setCellValue('A' . $row, $convocatoria->estado_convocatoria);
            $sheet->setCellValue('B' . $row, $convocatoria->nombre_convocatoria);
            $sheet->setCellValue('C' . $row, $convocatoria->tipo);
            $sheet->setCellValue('D' . $row, date('d/m/Y', strtotime($convocatoria->fecha_publicacion)));
            $sheet->setCellValue('E' . $row, date('d/m/Y', strtotime($convocatoria->fecha_cierre)));
            $sheet->setCellValue('F' . $row, $convocatoria->descripcion ?? 'N/A');

            // Estilo alternado de filas
            $fillColor = ($row % 2 == 0) ? 'E7F3FF' : 'FFFFFF';
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ]);

            // Color de estado según su valor
            $estadoColor = match(strtolower($convocatoria->estado_convocatoria)) {
                'abierta', 'activa' => '92D050', // Verde
                'cerrada', 'finalizada' => 'FF6B6B', // Rojo
                'en proceso', 'proceso' => 'FFC000', // Amarillo
                default => 'FFFFFF', // Blanco por defecto
            };

            $sheet->getStyle('A' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $estadoColor],
                ],
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ]);

            $row++;
        }

        // AJUSTAR ANCHOS DE COLUMNAS
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(50);

        // AUTOAJUSTAR ALTURA DE FILAS CON CONTENIDO
        for ($i = 6; $i < $row; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(-1);
        }

        // Crear el archivo Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'Convocatorias_' . date('Y-m-d_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        return response()->json([
            'mensaje' => 'Error al exportar convocatorias a Excel',
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * Obtener convocatoria por ID (para Aspirantes y Docentes)
 *
 * @param int $id_convocatoria ID de la convocatoria
 * @return \Illuminate\Http\JsonResponse
 */
public function obtenerConvocatoriaPublicaPorId($id_convocatoria)
{
    try {
        Log::info("Intentando obtener convocatoria con ID: " . $id_convocatoria);

        // Buscar la convocatoria CON relaciones
        $convocatoria = Convocatoria::with(['tipoCargo', 'facultad', 'perfilProfesional', 'experienciaRequerida', 'documentosConvocatoria'])->where('id_convocatoria', $id_convocatoria)->first();

        if (!$convocatoria) {
            Log::warning("Convocatoria no encontrada: " . $id_convocatoria);
            return response()->json([
                'mensaje' => 'Convocatoria no encontrada',
                'error' => 'La convocatoria solicitada no existe'
            ], 404);
        }

        Log::info("Convocatoria encontrada: " . $convocatoria->nombre_convocatoria);

        // Obtener documentos de la convocatoria
        $documentos = $convocatoria->documentosConvocatoria->map(function ($doc) {
            return [
                'id_documento' => $doc->id_documento,
                'archivo' => $doc->archivo,
                'estado' => $doc->estado ?? '',
                'url' => asset('storage/' . $doc->archivo),
            ];
        })->toArray();

        // Transformar datos para asegurar compatibilidad
        $convocatoriaTransformada = [
            'id_convocatoria' => $convocatoria->id_convocatoria,
            'numero_convocatoria' => $convocatoria->numero_convocatoria ?? 'CONV-' . $convocatoria->id_convocatoria,
            'nombre_convocatoria' => $convocatoria->nombre_convocatoria,
            'tipo' => $convocatoria->tipo,
            'periodo_academico' => $convocatoria->periodo_academico ?? 'No especificado',
            'tipo_cargo_id' => $convocatoria->tipo_cargo_id,
            'tipo_cargo_otro' => $convocatoria->tipo_cargo_otro ?? '',
            'cargo_solicitado' => $convocatoria->tipo_cargo_id ? $convocatoria->tipo_cargo_id : ($convocatoria->tipo_cargo_otro ?? ''),
            'facultad_id' => $convocatoria->facultad_id,
            'facultad_otro' => $convocatoria->facultad_otro ?? '',
            'facultad' => $convocatoria->facultad_otro ?? ($convocatoria->facultad ? $convocatoria->facultad->nombre_facultad : 'No especificado'),
            'cursos' => $convocatoria->cursos ?? 'No especificado',
            'tipo_vinculacion' => $convocatoria->tipo_vinculacion ?? 'No especificado',
            'personas_requeridas' => $convocatoria->personas_requeridas ?? 1,
            'fecha_publicacion' => $convocatoria->fecha_publicacion,
            'fecha_cierre' => $convocatoria->fecha_cierre,
            'fecha_inicio_contrato' => $convocatoria->fecha_inicio_contrato,
            'perfil_profesional_id' => $convocatoria->perfil_profesional_id,
            'perfil_profesional_outro' => $convocatoria->perfil_profesional_outro ?? '',
            'perfil_profesional' => $convocatoria->perfil_profesional_outro ?? ($convocatoria->perfilProfesional ? ($convocatoria->perfilProfesional->nombre_perfil ?? '') : ''),
            'experiencia_requerida_id' => $convocatoria->experiencia_requerida_id,
            'experiencia_requerida_fecha' => $convocatoria->experiencia_requerida_fecha ? ($convocatoria->experiencia_requerida_fecha instanceof \Illuminate\Support\Carbon ? $convocatoria->experiencia_requerida_fecha->toDateString() : $convocatoria->experiencia_requerida_fecha) : null,
            'experiencia_requerida' => $convocatoria->experiencia_requerida_fecha ? ($convocatoria->experiencia_requerida_fecha instanceof \Illuminate\Support\Carbon ? $convocatoria->experiencia_requerida_fecha->toDateString() : $convocatoria->experiencia_requerida_fecha) : ($convocatoria->experienciaRequerida ? $convocatoria->experienciaRequerida->descripcion_experiencia : ''),
            'anos_experiencia_requerida' => $convocatoria->anos_experiencia_requerida,
            'tipo_experiencia_requerida' => $convocatoria->tipo_experiencia_requerida,
            'cantidad_experiencia' => $convocatoria->cantidad_experiencia,
            'unidad_experiencia' => $convocatoria->unidad_experiencia,
            'referencia_experiencia' => $convocatoria->referencia_experiencia,
            'requisitos_experiencia' => $convocatoria->requisitos_experiencia ?? [],
            'requisitos_idiomas' => $convocatoria->requisitos_idiomas ?? [],
            'requisitos_adicionales' => $convocatoria->requisitos_adicionales ?? [],
            'solicitante' => $convocatoria->solicitante ?? 'Talento Humano',
            'avales_establecidos' => $convocatoria->avales_establecidos ?? [],
            'descripcion' => $convocatoria->descripcion,
            'estado_convocatoria' => $convocatoria->estado_convocatoria,
            'documentos_convocatoria' => $documentos,
        ];

        return response()->json(['convocatoria' => $convocatoriaTransformada], 200);

    } catch (\Exception $e) {
        Log::error("Error al obtener convocatoria: " . $e->getMessage());
        Log::error("Stack trace: " . $e->getTraceAsString());

        return response()->json([
            'mensaje' => 'Error al obtener la convocatoria',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}

    /**
     * Obtener opciones para tipos de cargo
     */
    public function obtenerTiposCargo()
    {
        try {
            $tiposCargo = \App\Models\TipoCargo::select('id_tipo_cargo', 'nombre_tipo_cargo', 'es_administrativo')->get();
            return response()->json(['tipos_cargo' => $tiposCargo], 200);
        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error al obtener tipos de cargo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener opciones para facultades
     */
    public function obtenerFacultades()
    {
        try {
            $facultades = \App\Models\Facultad::select('id_facultad', 'nombre_facultad')->get();
            return response()->json(['facultades' => $facultades], 200);
        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error al obtener facultades',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener los tipos de convocatoria disponibles.
     *
     * Este método retorna una lista de tipos únicos de convocatorias existentes en la base de datos.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de tipos de convocatoria.
     */
    public function obtenerTiposConvocatoria()
    {
        try {
            $tipos = Convocatoria::select('tipo')->distinct()->pluck('tipo');
            return response()->json(['tipos' => $tipos], 200);
        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error al obtener tipos de convocatoria',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatear la experiencia completa (predefinida + personalizada)
     */
    private function formatearExperienciaCompleta($convocatoria)
    {
        $experiencia = '';

        if ($convocatoria->experienciaRequerida) {
            $experiencia .= $convocatoria->experienciaRequerida->descripcion_experiencia;
        }

        if ($convocatoria->tipo_experiencia_requerida) {
            if ($experiencia) {
                $experiencia .= ' + ';
            }
            $experiencia .= $convocatoria->anos_experiencia_requerida . ' años de ' . $convocatoria->tipo_experiencia_requerida;
        }

        return $experiencia ?: 'No especificada';
    }
}
