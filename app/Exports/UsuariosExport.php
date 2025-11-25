<?php

namespace App\Exports;

use App\Models\Usuario\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;


class UsuariosExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithEvents
{
    /**
     * Construye la colección de datos que será exportada a Excel.
     *
     * Esta función obtiene todos los usuarios registrados con el rol "Aspirante".
     * Luego, aplica un `map()` para transformar los datos en un
     * arreglo de filas compatibles con el formato de exportación.
     *
     * Se incluyen campos como nombre completo, tipo de identificación, estado civil,
     * género, fecha de nacimiento y correo electrónico. Esta información será mostrada
     * en la hoja de Excel titulada "Usuarios".
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        //modifique esto (Brayan Cuellar)
        return User::with('roles')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'primer_nombre' => $user->primer_nombre,
                'segundo_nombre' => $user->segundo_nombre,
                'primer_apellido' => $user->primer_apellido,
                'segundo_apellido' => $user->segundo_apellido,
                'tipo_identificacion' => $user->tipo_identificacion,
                'numero_identificacion' => $user->numero_identificacion,
                'estado_civil' => $user->estado_civil,
                'genero' => $user->genero,
                'fecha_nacimiento' => $user->fecha_nacimiento,
                'correo' => $user->email,
                'rol' => $user->roles->first()?->name ?? 'Sin rol',
            ];
        });
    }

    public function headings(): array
    {
        //modifique esto (Brayan Cuellar)
        return [
            'ID',
            'Primer Nombre',
            'Segundo Nombre',
            'Primer Apellido',
            'Segundo Apellido',
            'Tipo de Identificacion',
            'Numero de Identificacion',
            'Estado Civil',
            'Genero',
            'fecha Nacimiento',
            'Correo',
            // solo añadi el esto (Brayan Cuellar)
            'Rol Actual',
        ];
    }

    /**
     * Define el título de la hoja de Excel.
     *
     * @return string
     */
    public function title(): string
    {
        return 'Usuarios';
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
