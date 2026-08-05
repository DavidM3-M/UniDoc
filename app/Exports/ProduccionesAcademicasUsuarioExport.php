<?php

namespace App\Exports;

use App\Models\Aspirante\ProduccionAcademica;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;


class ProduccionesAcademicasUsuarioExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithEvents
{
    /**
     * Construye la colección de datos que será exportada a Excel.
     *
     * Esta función obtiene todas las producciones académicas registradas por usuarios con el rol "Aspirante".
     * Utiliza la relación `usuarioProduccionAcademica` para acceder a la información personal del usuario
     * asociado a cada producción académica. Luego, aplica un `map()` para transformar los datos en un
     * arreglo de filas compatibles con el formato de exportación.
     *
     * Se incluyen campos como nombre completo, ámbito de divulgación, título, número de autores,
     * medio de divulgación y fecha de divulgación. Esta información será mostrada en la hoja de Excel titulada "Producciones académicas".
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return ProduccionAcademica::with([
            'usuarioProduccionAcademica',
        ])
            ->whereHas(
                'usuarioProduccionAcademica.roles',
                function ($query) {
                    $query->where('name', 'Aspirante');
                }
            )
            ->get()
            ->map(
                function ($produccionAcademica) {
                    return [
                        'numero_identificacion' => $produccionAcademica->usuarioProduccionAcademica->numero_identificacion,
                        'email' => $produccionAcademica->usuarioProduccionAcademica->email,
                        'ambito_divulgacion' => $produccionAcademica->ambito_divulgacion,
                        'titulo' => $produccionAcademica->titulo,
                        'numero_autores' => $produccionAcademica->numero_autores,
                        'medio_divulgacion' => $produccionAcademica->medio_divulgacion,
                        'fecha_divulgacion' => $produccionAcademica->fecha_divulgacion,

                    ];
                }
            );
    }
    public function headings(): array
    {
        return [
            'Número Identificación',
            'Email',
            'Ámbito de divulgación',
            'Título',
            'Número de autores',
            'Medio de divulgación',
            'Fecha de divulgación',
        ];
    }

    /**
     * Define el título de la hoja de Excel.
     *
     * Este método devuelve el título que se mostrará en la pestaña de la hoja de Excel.
     * En este caso, se establece como "Producciones académicas".
     *
     * @return string
     */
    public function title(): string
    {
        return 'Producciones académicas';
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
     * Define los estilos que se aplicarán a la hoja de Excel.
     *
     * Este método aplica estilos a la primera fila de la hoja, que contiene los encabezados
     * de las columnas. Se establece un fondo azul, texto en negrita y centrado,
     * y bordes alrededor de cada celda.
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
