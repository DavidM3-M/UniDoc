<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Http\UploadedFile;

class CertificadoDocenteService
{
    /**
     * Genera un certificado en PDF como archivo subible.
     *
     * @param array $data
     * @return UploadedFile
     */
    public function generarPDF(array $data): UploadedFile
    {
        $pdf = new Fpdi();

        // Ruta de la plantilla
        $templatePath = storage_path('app/plantillas/plantilla_certificado.pdf');

        // Cargar plantilla y obtener dimensiones
        $pdf->setSourceFile($templatePath);
        $tplIdx = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplIdx);

        // Añadir página con el mismo tamaño de la plantilla
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

        // Aplicar la plantilla como fondo
        $pdf->useTemplate($tplIdx);


        // Título del certificado
        $pdf->SetFont('Times', '', 20);
        $pdf->SetTextColor(6, 61, 134);
        $pdf->SetXY(105, 80);
        $pdf->Cell(90, 10, strtoupper($data['titulo_certificado']), 0, 0, 'C');

        // Nombre del docente
        $pdf->SetFont('Times', '', 35); // Se cambiará por fuente decorativa si la agregas
        $pdf->SetXY(95, 110);
        $pdf->Cell(105, 10, $data['nombre_docente'], 0, 0, 'C');

        // Fecha
        $pdf->SetFont('Times', '', 10);
        $pdf->SetXY(104, 150);
        $pdf->Cell(90, 10, $data['fecha'], 0, 0, 'C');

        $nombreArchivo = 'certificado_' . $data['docente_id'] . '_' . time() . '.pdf';
        $rutaTemporal = storage_path('app/temp/' . $nombreArchivo);

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0777, true);
        }

        $pdf->Output($rutaTemporal, 'F');

        return new UploadedFile(
            $rutaTemporal,
            $nombreArchivo,
            'application/pdf',
            null,
            true
        );
    }
}
