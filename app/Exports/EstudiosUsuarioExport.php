<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Aspirante\Estudio;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class EstudiosUsuarioExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithEvents
{
    /**
     * Construye la colección de datos que será exportada a Excel.
     *
     * Esta función obtiene todos los estudios registrados por usuarios con el rol "Aspirante".
     * Utiliza la relación `usuarioEstudio` para acceder a la información personal del usuario
     * asociado a cada estudio. Luego, aplica un `map()` para transformar los datos en un
     * arreglo de filas compatibles con el formato de exportación.
     *
     * Se incluyen campos como nombre completo, tipo de estudio, institución, fechas de inicio y fin,
     * convalidación, y más. Esta información será mostrada en la hoja de Excel titulada "Estudios".
     *
     * @return \Illuminate\Support\Collection
     */

    public function collection()
    {
        return Estudio::with([
            'usuarioEstudio'
        ])
            ->whereHas(
                'usuarioEstudio.roles',
                function ($query) {
                    $query->where('name', 'Aspirante');
                }
            )
            ->get()
            ->map(function ($estudio) {
                return [
                    'numero_identificacion' => $estudio->usuarioEstudio->numero_identificacion,
                    'Email' => $estudio->usuarioEstudio->email,
                    'tipo_estudio' => $estudio->tipo_estudio,
                    'Grauado' => $estudio->graduado,
                    'institucion' => $estudio->institucion,
                    'fecha_graduacion' => $estudio->fecha_graduacion,
                    'titulo_convalidado' => $estudio->titulo_convalidado,
                    'fecha_convalidacion' => $estudio->fecha_convalidacion,
                    'resolucion_convalidacion' => $estudio->resolucion_convalidacion,
                    'posible_fecha_graduacion' => $estudio->posible_fecha_graduacion,
                    'titulo_estudio' => $estudio->titulo_estudio,
                    'fecha_inicio' => $estudio->fecha_inicio,
                    'fecha_fin' => $estudio->fecha_fin,
                ];
            });
    }
    public function headings(): array
    {
        return [
            'Número de Identificación',
            'Email',
            'Tipo de Estudio',
            'Graduado',
            'Institucion',
            'Fecha de Graduacion',
            'Titulo Convalidado',
            'Fecha de Convalidacion',
            'Resolucion de Convalidacion',
            'Posible Fecha de Graduacion',
            'Titulo de Estudio',
            'Fecha de Inicio',
            'Fecha de Fin'
        ];
    }

    /**
     * Define el título de la hoja de Excel.
     *
     * Este método establece el título de la hoja que se generará en el archivo Excel.
     * En este caso, se define como "Estudios".
     *
     * @return string
     */
    public function title(): string
    {
        return 'Estudios';
    }

    /**
     * Define los eventos que se ejecutarán después de la creación de la hoja.
     *
     * En este caso, se utiliza el evento AfterSheet para congelar la primera fila
     * de la hoja de Excel, lo que permite que los encabezados permanezcan visibles
     * mientras se desplaza por el resto de los datos.
     *
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
     * Se establece un fondo azul, texto en negrita y color blanco, alineación centrada y bordes delgados.
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
