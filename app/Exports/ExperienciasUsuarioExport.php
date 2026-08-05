<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Aspirante\Experiencia;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ExperienciasUsuarioExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithEvents
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
        return Experiencia::with([
            'usuarioExperiencia'
        ])
            ->whereHas(
                'usuarioExperiencia.roles',
                function ($query) {
                    $query->where('name', 'Aspirante');
                }
            )
            ->get()
            ->map(function ($experiencia) {
                return [
                    'numero_identificacion' => $experiencia->usuarioExperiencia->numero_identificacion,
                    'Email' => $experiencia->usuarioExperiencia->email,
                    'tipo_experiencia' => $experiencia->tipo_experiencia,
                    'institucion' => $experiencia->institucion_experiencia,
                    'cargo' => $experiencia->cargo,
                    'trabajo_actual' => $experiencia->trabajo_actual,
                    'intesidad_horaria' => $experiencia->intensidad_horaria,
                    'fecha_inicio' => $experiencia->fecha_inicio,
                    'fecha-finalizacion' => $experiencia->fecha_finalizacion,
                    'fecha_expedicion_certificado' => $experiencia->fecha_expedicion_certificado,
                ];
            });
    }
    public function headings(): array
    {
        return [
            'Numero de Identificacion',
            'Email',
            'Tipo de Experiencia',
            'Institucion',
            'Cargo',
            'Trabajo Actual',
            'Intensidad Horaria',
            'Fecha de Inicio',
            'Fecha de Finalizacion',
            'Fecha de Expedicion del Certificado'
        ];
    }
    /**
     * Define el título de la hoja de Excel.
     *
     * Este método devuelve el título que se mostrará en la pestaña de la hoja de Excel.
     * En este caso, se establece como "Estudios".
     *
     * @return string
     */
    public function title(): string
    {
        return 'Experiencias';
    }

    /**
     * Define los eventos que se ejecutarán después de que la hoja de Excel haya sido creada.
     *
     * En este caso, se utiliza el evento `AfterSheet` para aplicar un estilo de congelación
     * a la primera fila de la hoja, lo que permite que los encabezados permanezcan visibles
     * mientras se desplaza por el resto de la hoja.
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
