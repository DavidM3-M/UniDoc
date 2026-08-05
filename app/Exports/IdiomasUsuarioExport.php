<?php

namespace App\Exports;

use App\Models\Aspirante\Idioma;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class IdiomasUsuarioExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithEvents
{

    /**
     * Construye la colección de datos que será exportada a Excel.
     *
     * Esta función obtiene todos los idiomas registrados por usuarios con el rol "Aspirante".
     * Utiliza la relación `usuarioidioma` para acceder a la información personal del usuario
     * asociado a cada idioma. Luego, aplica un `map()` para transformar los datos en un
     * arreglo de filas compatibles con el formato de exportación.
     *
     * Se incluyen campos como nombre completo, idioma, institución, fecha de certificado y nivel.
     * Esta información será mostrada en la hoja de Excel titulada "Idiomas".
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Idioma::with([
            'usuarioidioma'
        ])
            ->whereHas(
                'usuarioidioma.roles',
                function ($query) {
                    $query->where('name', 'Aspirante');
                }
            )
            ->get()
            ->map(function ($idioma) {
                return [
                    'numero_identificacion' => $idioma->usuarioidioma->numero_identificacion,
                    'email' => $idioma->usuarioidioma->email,
                    'idioma' => $idioma->idioma,
                    'institucion_idioma' => $idioma->institucion_idioma,
                    'fecha_certificado' => $idioma->fecha_certificado,
                    'nivel' => $idioma->nivel,
                ];
            });
    }
    public function headings(): array
    {
        return [
            'Número Identificación',
            'Email',
            'Idioma',
            'Institución Idioma',
            'Fecha Certificado',
            'Nivel',
        ];
    }

    /**
     * Define el título de la hoja de Excel.
     *
     * Este método retorna el título que se mostrará en la pestaña de la hoja de Excel.
     * En este caso, se establece como "Idiomas".
     *
     * @return string
     */
    public function title(): string
    {
        return 'Idiomas';
    }
    /**
     * Define los estilos de la hoja de Excel.
     *
     * Este método aplica estilos a la hoja de Excel, como el formato de la primera fila
     * (encabezados) y el congelamiento del panel superior.
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }

    /**
     * Aplica estilos a la hoja de Excel.
     *
     * Esta función aplica estilos a la primera fila de la hoja de Excel, que contiene los encabezados.
     * Se establece un fondo azul, texto en negrita y color blanco, alineación centrada y bordes.
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Rango de la primera fila (A1 hasta la última columna)
        $lastColumn = $sheet->getHighestColumn();
        $headerRange = "A1:{$lastColumn}1";

        // Aplica estilos
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        return [];
    }
}
