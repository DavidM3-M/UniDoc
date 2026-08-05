<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ConvertidorPDFService
{
    protected $ghostscriptPath;
    protected $carpetaDestino;

    public function __construct()
    {
        $this->ghostscriptPath = '"C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe"'; // Ruta de Ghostscript
        $this->carpetaDestino = public_path('storage/Documentos_Compartidos');
    }

    /**
     * Convierte el PDF solo si es necesario
     */
    public function convertir($rutaOriginal)
    {
        if (!file_exists($this->carpetaDestino)) {
            mkdir($this->carpetaDestino, 0777, true);
        }

        $nombreConvertido = basename($rutaOriginal, '.pdf') . '_convertido.pdf';
        $rutaConvertida = $this->carpetaDestino . '/' . $nombreConvertido;

        // Si ya existe, no convertir de nuevo
        if (file_exists($rutaConvertida)) {
            return $rutaConvertida;
        }

        // Primero, revisar la versión del PDF
        $versionPDF = $this->obtenerVersionPDF($rutaOriginal);

        // Si ya es 1.4 o menor, no convertir: usar el original
        if (floatval($versionPDF) <= 1.4) {
            return $rutaOriginal;
        }

        // Convertir usando Ghostscript
        $rutaOriginalEscapada = '"' . $rutaOriginal . '"';
        $rutaConvertidaEscapada = '"' . $rutaConvertida . '"';

        $comando = $this->ghostscriptPath . ' ' .
            '-sDEVICE=pdfwrite ' .
            '-dCompatibilityLevel=1.4 ' .
            '-dPDFSETTINGS=/prepress ' .
            '-dNOPAUSE ' .
            '-dQUIET ' .
            '-dBATCH ' .
            '-sOutputFile=' . $rutaConvertidaEscapada . ' ' . $rutaOriginalEscapada;

        $process = Process::fromShellCommandline($comando, null, [
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $rutaConvertida;
    }

    /**
     * Detecta la versión del PDF leyendo directamente el primer contenido
     */
    private function obtenerVersionPDF($rutaArchivo)
    {
        $handle = fopen($rutaArchivo, 'rb');

        if ($handle) {
            $line = fgets($handle);
            fclose($handle);

            if (preg_match('/%PDF-(\d\.\d)/', $line, $matches)) {
                return $matches[1]; // Retorna la versión como "1.4", "1.5", etc.
            }
        }

        // Si no puede leer la versión, asumir lo peor
        return '1.7';
    }
}
