<?php

namespace App\Services;

use App\Models\Usuario\User;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ConvertidorPDFService;

class GeneradorHojaDeVidaPDFService
{
    protected $convertidorPDFService;

    public function __construct(ConvertidorPDFService $convertidorPDFService)
    {
        $this->convertidorPDFService = $convertidorPDFService;
    }

    // Función pública para generar la hoja de vida en PDF de un usuario a partir de su ID
    public function generar($idUsuario)
    {
        try {
            // Buscar el usuario con todas sus relaciones cargadas
            $usuario = User::with([
                'documentosUser',
                'fotoPerfilUsuario.documentosFotoPerfil',
                'informacionContactoUsuario.documentosInformacionContacto',
                'estudiosUsuario.documentosEstudio',
                'experienciasUsuario.documentosExperiencia',
                'epsUsuario.documentosEps',
                'idiomasUsuario.documentosIdioma',
                'rutUsuario.documentosRut',
                'produccionAcademicaUsuario.documentosProduccionAcademica',
                'aptitudesUsuario',
            ])->findOrFail($idUsuario); // Error si no encuentra el usuario

            // Crear una nueva instancia del PDF usando FPDI
            $pdf = new Fpdi();
            $pdf->AddPage(); // Agrega una nueva página al PDF
            $pdf->SetFont('Helvetica', '', 12); // Establece la fuente a usar en el PDF

            // --- Foto de perfil ---
            // Imprime la foto de perfil del usuario en el PDF
            $this->imprimirFotoPerfil($pdf, $usuario);

            $pdf->Ln(70); // Deja un espacio de 70 unidades después de la foto

            // --- Información personal ---
            // Imprime los datos personales del usuario
            $this->imprimirInformacionPersonal($pdf, $usuario);

            // --- Aptitudes ---
            // Imprime las aptitudes del usuario
            $this->imprimirAptitudes($pdf, $usuario->aptitudesUsuario);

            // --- Documento de identificación ---
            // Procesa los documentos de identificación (pueden ser varios)
            $this->procesarRelacionMultiple($pdf, $usuario->documentosUser, 'documentosUser', 'Documento de Identificación');

            // --- EPS ---
            // Imprime la información de la EPS del usuario
            $this->imprimirEPS($pdf, $usuario->epsUsuario);

            // --- Información de contacto ---
            // Procesa los documentos de contacto (es una relación simple)
            $this->procesarRelacionSimple($pdf, $usuario->informacionContactoUsuario, 'documentosInformacionContacto', 'Información de Contacto');

            // --- RUT ---
            // Procesa los documentos de RUT (también relación simple)
            $this->procesarRelacionSimple($pdf, $usuario->rutUsuario, 'documentosRut', 'RUT');

            // --- Idiomas ---
            // Imprime los idiomas que maneja el usuario
            $this->imprimirIdiomas($pdf, $usuario->idiomasUsuario);

            // --- Estudios ---
            // Imprime los estudios del usuario
            $this->imprimirEstudios($pdf, $usuario->estudiosUsuario);

            // --- Experiencias ---
            // Imprime la experiencia laboral del usuario
            $this->imprimirExperiencias($pdf, $usuario->experienciasUsuario);

            // --- Producción Académica ---
            // Imprime la producción académica del usuario
            $this->imprimirProduccionAcademica($pdf, $usuario->produccionAcademicaUsuario);

            $nombreArchivo = 'hoja_de_vida_' . $idUsuario . '_' . time() . '.pdf';
            $rutaTemporal = storage_path('app/temp/' . $nombreArchivo);

            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }

            $pdf->Output($rutaTemporal, 'F');

            return response()->streamDownload(function () use ($rutaTemporal) {
                echo file_get_contents($rutaTemporal);
                unlink($rutaTemporal);
            }, $nombreArchivo, [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al generar el PDF.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ------------------------
    // Métodos auxiliares
    // ------------------------


    // Imprime una línea centrada en el PDF, con etiqueta en negrita y valor en normal
    private function imprimirLineaCentrada($pdf, $etiqueta, $valor)
    {
        $textoCompleto = $etiqueta . ' ' . $valor; // Junta etiqueta y valor en un solo string
        $anchoTexto = $pdf->GetStringWidth($textoCompleto); // Calcula el ancho total del texto
        $paginaAncho = $pdf->GetPageWidth(); // Obtiene el ancho de la página
        $x = ($paginaAncho - $anchoTexto) / 2; // Calcula la posición X para centrar el texto
        $pdf->SetX($x);  // Mueve el cursor a esa posición X
        $pdf->SetFont('Helvetica', 'B', 12); // Fuente en negrita para la etiqueta
        $pdf->Write(10, $etiqueta . ' '); // Escribe la etiqueta
        $pdf->SetFont('Helvetica', '', 12); // Fuente normal para el valor
        $pdf->Write(10, $valor); // Escribe el valor
        $pdf->Ln(12); // Salta una línea (espacio después del par etiqueta-valor)
    }

    // Inserta la foto de perfil del usuario en el PDF, centrada en la página
    private function imprimirFotoPerfil($pdf, $usuario)
    {
        if ($usuario->fotoPerfilUsuario && $usuario->fotoPerfilUsuario->documentosFotoPerfil) { // Verifica si el usuario tiene una foto de perfil
            $fotoRelativePath = $usuario->fotoPerfilUsuario->documentosFotoPerfil->first()->archivo ?? null; // toma el primer archivo de la foto de perfil
            if ($fotoRelativePath) {
                $fotoStoragePath = public_path('storage/' . $fotoRelativePath); // obtiene la ruta completa de la foto
                if (file_exists($fotoStoragePath)) { // Verifica si el archivo existe
                    $pageWidth = $pdf->GetPageWidth(); // Obtiene el ancho de la página
                    $imageWidth = 50; // Ancho deseado de la imagen
                    $x = ($pageWidth - $imageWidth) / 2; // Calcula la posición  centrada de la imagen
                    $pdf->Image($fotoStoragePath, $x, 20, $imageWidth, 50, 'JPG'); // Inserta la imagen en el PDF
                }
            }
        }
    }

    // Imprime la sección de información personal del usuario, usando lineas centradas
    private function imprimirInformacionPersonal($pdf, $usuario)
    {
        $this->imprimirLineaCentrada($pdf, 'Nombre:', $usuario->primer_nombre . ' ' . $usuario->segundo_nombre);
        $this->imprimirLineaCentrada($pdf, 'Apellido:', $usuario->primer_apellido . ' ' . $usuario->segundo_apellido);
        $this->imprimirLineaCentrada($pdf, 'Tipo de identificación:', $usuario->tipo_identificacion);
        $this->imprimirLineaCentrada($pdf, 'Número de identificación:', $usuario->numero_identificacion);
        $this->imprimirLineaCentrada($pdf, 'Género:', $usuario->genero);
        $this->imprimirLineaCentrada($pdf, 'Correo:', $usuario->email);
        $this->imprimirLineaCentrada($pdf, 'Teléfono:', $usuario->telefono);
    }

    // Imprime la sección de aptitudes del usuario (lista simple)
    private function imprimirAptitudes($pdf, $aptitudes)
    {
        if ($aptitudes && $aptitudes->count() > 0) { // Verifica que existan aptitudes
            $pdf->AddPage(); // Agrega una nueva página
            $pdf->SetFont('Helvetica', 'B', 14); // Título en negrita
            $pdf->Cell(0, 10, 'Aptitudes', 0, 1, 'C'); // Imprime el título 'Aptitudes' centrado
            $pdf->SetFont('Helvetica', '', 12); // Cambia a fuente normal para listar
            foreach ($aptitudes as $aptitud) {
                $pdf->Cell(0, 10, '- ' . $aptitud->nombre_aptitud, 0, 1); // Imprime cada aptitud
            }
        }
    }

    // Imprime la sección de EPS con etiquetas en negrita y valores en normal
    private function imprimirEPS($pdf, $epsUsuario)
    {
        if ($epsUsuario) { // Verifica que exista información de EPS
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'EPS', 0, 1, 'C');
            $pdf->Ln(5); // Salto de línea

            // Define los datos de la EPS en un array
            $datos = [
                'Nombre EPS:' => $epsUsuario->nombre_eps,
                'Tipo Afiliación:' => $epsUsuario->tipo_afiliacion,
                'Estado Afiliación:' => $epsUsuario->estado_afiliacion,
                'Fecha Afiliación Efectiva:' => $epsUsuario->fecha_afiliacion_efectiva,
                'Fecha Finalización Afiliación:' => $epsUsuario->fecha_finalizacion_afiliacion ?? 'No aplica',
                'Tipo Afiliado:' => $epsUsuario->tipo_afiliado,
                'Número Afiliado:' => $epsUsuario->numero_afiliado ?? 'No aplica',
            ];
            // Recorre el array y lo imprime en el PDF
            foreach ($datos as $etiqueta => $valor) {
                $pdf->SetFont('Helvetica', 'B', 12); // Título en negrita
                $pdf->Write(8, $etiqueta . ' ');
                $pdf->SetFont('Helvetica', '', 12); // Valor en normal
                $pdf->Write(8, $valor);
                $pdf->Ln(10); // Salto de línea
            }
            // documentos asociados a la EPS
            $this->procesarRelacionSimple($pdf, $epsUsuario, 'documentosEps', 'EPS');
        }
    }

    // Imprime la sección de idiomas del usuario
    private function imprimirIdiomas($pdf, $idiomas)
    {
        if ($idiomas && $idiomas->count() > 0) {  // Verifica que existan idiomas
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Idiomas', 0, 1, 'C');
            $pdf->Ln(5);

            // Recorre cada idioma y lo imprime en el PDF
            // Define los datos del idioma en un array
            foreach ($idiomas as $idioma) {
                $datos = [
                    'Idioma:' => $idioma->idioma,
                    'Institución:' => $idioma->institucion_idioma,
                    'Fecha Certificado:' => $idioma->fecha_certificado,
                    'Nivel:' => $idioma->nivel,
                ];

                // Recorre el array y lo imprime en el PDF
                foreach ($datos as $etiqueta => $valor) {
                    $pdf->SetFont('Helvetica', 'B', 12); // Etiqueta en negrita
                    $pdf->Write(8, $etiqueta . ' ');
                    $pdf->SetFont('Helvetica', '', 12); // Valor normal
                    $pdf->Write(8, $valor);
                    $pdf->Ln(10);
                }

                $pdf->Ln(5); // Pequeño espacio después de cada idioma

                // Documentos asociados al idioma
                $this->procesarDocumentoRelacion($idioma->documentosIdioma, $pdf, 'Idioma');
            }
        }
    }

    // Imprime la sección de estudios del usuario
    private function imprimirEstudios($pdf, $estudios)
    {
        if ($estudios && $estudios->count() > 0) { // Verifica que existan estudios
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Estudios', 0, 1, 'C');
            $pdf->Ln(5);

            // Recorre cada estudio y lo imprime en el PDF
            // Define los datos del estudio en un array
            foreach ($estudios as $estudio) {
                $datos = [
                    'Tipo Estudio:' => $estudio->tipo_estudio,
                    'Institución:' => $estudio->institucion,
                    'Título:' => $estudio->titulo_estudio ?? 'No aplica',
                    'Graduado:' => $estudio->graduado,
                    'Fecha Inicio:' => $estudio->fecha_inicio,
                    'Fecha Fin:' => $estudio->fecha_fin ?? 'No aplica',
                ];

                // Recorre el array y lo imprime en el PDF
                foreach ($datos as $etiqueta => $valor) {
                    $pdf->SetFont('Helvetica', 'B', 12); // Etiqueta en negrita
                    $pdf->Write(8, $etiqueta . ' ');
                    $pdf->SetFont('Helvetica', '', 12); // Valor normal
                    $pdf->Write(8, $valor);
                    $pdf->Ln(10);
                }

                $pdf->Ln(5); // Espacio adicional entre estudios

                // Documentos asociados al estudio
                $this->procesarDocumentoRelacion($estudio->documentosEstudio, $pdf, 'Estudio');
            }
        }
    }

    // Imprime la sección de experiencias laborales del usuario
    private function imprimirExperiencias($pdf, $experiencias)
    {
        if ($experiencias && $experiencias->count() > 0) { // Verifica que existan experiencias
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Experiencia Laboral', 0, 1, 'C');
            $pdf->Ln(5);

            // Recorre cada experiencia y la imprime en el PDF
            // Define los datos de la experiencia en un array
            foreach ($experiencias as $experiencia) {
                $datos = [
                    'Institución:' => $experiencia->institucion_experiencia,
                    'Cargo:' => $experiencia->cargo,
                    'Fecha Inicio:' => $experiencia->fecha_inicio,
                    'Fecha Fin:' => $experiencia->fecha_finalizacion ?? 'Actualmente',
                ];

                // Recorre el array y lo imprime en el PDF
                foreach ($datos as $etiqueta => $valor) {
                    $pdf->SetFont('Helvetica', 'B', 12); // Etiqueta en negrita
                    $pdf->Write(8, $etiqueta . ' ');
                    $pdf->SetFont('Helvetica', '', 12); // Valor en normal
                    $pdf->Write(8, $valor);
                    $pdf->Ln(10);
                }

                $pdf->Ln(5); // Espacio después de cada experiencia

                // Documentos asociados a la experiencia
                $this->procesarDocumentoRelacion($experiencia->documentosExperiencia, $pdf, 'Experiencia');
            }
        }
    }

    // Imprime la sección de producción académica del usuario
    private function imprimirProduccionAcademica($pdf, $producciones)
    {
        if ($producciones && $producciones->count() > 0) { // Verifica que existan producciones académicas
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Producción Académica', 0, 1, 'C');
            $pdf->Ln(5);

            // Recorre cada producción académica y la imprime en el PDF
            // Define los datos de la producción académica en un array
            foreach ($producciones as $produccion) {
                $datos = [
                    'Título:' => $produccion->titulo,
                    'Fecha Divulgación:' => $produccion->fecha_divulgacion,
                ];

                // Recorre el array y lo imprime en el PDF
                foreach ($datos as $etiqueta => $valor) {
                    $pdf->SetFont('Helvetica', 'B', 12); // Etiqueta en negrita
                    $pdf->Write(8, $etiqueta . ' ');
                    $pdf->SetFont('Helvetica', '', 12); // Valor normal
                    $pdf->Write(8, $valor);
                    $pdf->Ln(10);
                }

                $pdf->Ln(5); // Espacio después de cada producción

                // Documentos asociados a la producción académica
                $this->procesarDocumentoRelacion($produccion->documentosProduccionAcademica, $pdf, 'Producción Académica');
            }
        }
    }



    // Estas funciones se encargan de mirar que tipo de relación tiene el modelo
    // para depues saber a que  proceso de documento llamar, separando si es una relación simple o múltiple


    // procesar una relación simple, cuando el modelo tiene un solo documento
    private function procesarRelacionSimple($pdf, $modelo, $campoDocumentos, $titulo)
    {
        if ($modelo && $modelo->$campoDocumentos) { // Verifica si el modelo y su campo de documentos existen
            foreach ($modelo->$campoDocumentos as $documento) { // Recorre todos los documentos asociados a ese modelo
                $this->procesarDocumento($documento, $pdf, $titulo);  // Procesa cada documento individualmente
            }
        }
    }

    // procesar una relación múltiple, cuando el modelo tiene varios documentos
    private function procesarRelacionMultiple($pdf, $modelos, $campoDocumentos, $titulo)
    {
        if ($modelos) { // Verifica que exista la colección de modelos
            foreach ($modelos as $modelo) { // Recorre cada modelo en la colección
                if ($modelo->$campoDocumentos) { // Si el modelo tiene documentos asociados
                    foreach ($modelo->$campoDocumentos as $documento) { // Recorre todos los documentos de ese modelo
                        $this->procesarDocumento($documento, $pdf, $titulo);  // Procesa cada documento individualmente
                    }
                }
            }
        }
    }

    // Estas funciones procesan documentos de una relación simple o múltiple
    // dependiendo del tipo de relación que tenga el modelo

    // Función para procesar directamente una colección de documentos (sin importar de qué modelo vienen)
    private function procesarDocumentoRelacion($documentos, $pdf, $tipoDocumento)
    {
        if ($documentos) { // Verifica si hay documentos para procesar
            foreach ($documentos as $documento) {  // Recorre cada documento de la colección
                $this->procesarDocumento($documento, $pdf, $tipoDocumento); // Procesa cada documento individualmente
            }
        }
    }

    // Función para procesar un documento individual
    private function procesarDocumento($documento, $pdf, $tipoDocumento)
    {
        $pdfPath = public_path('storage/' . $documento->archivo); // Construye la ruta completa del documento en el sistema de archivos

        if (file_exists($pdfPath)) { // Verifica si el archivo existe
            try {
                $pdfConvertido = $this->convertidorPDFService->convertir($pdfPath); // Usa un servicio para convertir el documento (por si se necesita transformar el PDF)
                $pageCount = $pdf->setSourceFile($pdfConvertido);  // Cuenta cuántas páginas tiene el PDF convertido

                for ($page = 1; $page <= $pageCount; $page++) { // Recorre cada página del documento
                    $tplIdx = $pdf->importPage($page); // Importa la página actual como plantilla
                    $pdf->AddPage(); // Crea una nueva página en el PDF final
                    $pdf->useTemplate($tplIdx, 10, 10, 190);  // Inserta la plantilla importada en la página nueva
                }

                // Elimina el archivo temporal convertido si es diferente del archivo original
                if (realpath($pdfConvertido) !== realpath($pdfPath)) {
                    @unlink($pdfConvertido);
                }
            } catch (\Exception $e) {
                // Si ocurre un error procesando el documento, crea una página en blanco e informa del error
                $pdf->AddPage();
                $pdf->SetFont('Helvetica', '', 8);
                $pdf->MultiCell(0, 10, 'Error al procesar ' . $tipoDocumento . ': ' . $e->getMessage(), 0, 'L');
                Log::error('Error al procesar ' . $tipoDocumento . ': ' . $e->getMessage()); // Registra el error en el log
            }
        } else {
            // Si no encuentra el archivo, agrega una página nueva y notifica que no encontró el documento
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(0, 10, 'Documento no encontrado: ' . $pdfPath, 0, 1);
        }
    }
}
