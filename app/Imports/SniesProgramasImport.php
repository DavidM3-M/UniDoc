<?php

namespace App\Imports;

use App\Models\InstitucionSnies;
use App\Models\NivelFormacionAcademica;
use App\Models\ProgramaFormacionEducativa;
use App\Models\SniesImportacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Procesa la hoja "Programas" del Excel del SNIES en una sola pasada (sin `WithChunkReading`):
 * se usa junto con `SniesOfertaProgramasImport` (`WithMultipleSheets`), que restringe la lectura
 * a esa única hoja del workbook.
 *
 * Se probó `WithChunkReading` en dos variantes:
 * - 500/1.000 filas por chunk: estable, pero cada chunk paga de nuevo el costo de PhpSpreadsheet
 *   de reabrir el archivo y reanalizar las cadenas compartidas del workbook → ~10 min reales.
 * - Combinado con `WithMultipleSheets`: esa combinación específica reventó el proceso por
 *   memoria (el worker se reiniciaba solo, sin excepción capturable).
 *
 * Esta versión evita ambos problemas: una sola lectura (un solo costo de PhpSpreadsheet, no
 * repetido) restringida a una sola hoja (no carga las ~46.000 filas de "Cobertura"), y trocea el
 * `Collection` ya en memoria — sin volver a tocar el archivo — para mantener las mismas
 * escrituras en lote por grupos de 1.000 filas.
 */
class SniesProgramasImport implements ToCollection, WithHeadingRow
{
    private const TAMANO_LOTE = 1000;

    /** [nivel_academico|nivel_formacion en minúsculas => id_nivel_formacion_academica] */
    private array $cacheNiveles = [];
    private bool $nivelesCargados = false;

    public function __construct(private readonly int $importacionId)
    {
    }

    public function collection(Collection $filas): void
    {
        foreach ($filas->chunk(self::TAMANO_LOTE) as $lote) {
            $this->procesarLote($lote);
        }
    }

    private function procesarLote(Collection $filas): void
    {
        $this->cargarNivelesSiHaceFalta();
        $nivelesCreados = 0;

        // Paso 1: normalizar y descartar filas incompletas (sin institución, programa o nivel).
        $filasValidas = [];
        foreach ($filas as $fila) {
            $nombreInstitucion = trim((string) ($fila['nombre_institucion'] ?? ''));
            $nombrePrograma = trim((string) ($fila['nombre_del_programa'] ?? ''));

            if ($nombreInstitucion === '' || $nombrePrograma === '') {
                continue;
            }

            $nivelId = $this->resolverNivelId(
                trim((string) ($fila['nivel_academico'] ?? '')),
                trim((string) ($fila['nivel_de_formacion'] ?? '')),
                $nivelesCreados
            );

            if (!$nivelId) {
                continue;
            }

            $filasValidas[] = [
                'codigo_institucion' => trim((string) ($fila['codigo_institucion'] ?? '')) ?: null,
                'codigo_institucion_padre' => trim((string) ($fila['codigo_institucion_padre'] ?? '')) ?: null,
                'nombre_institucion' => $nombreInstitucion,
                'estado_institucion' => $fila['estado_institucion'] ?? null,
                'caracter_academico' => $fila['caracter_academico'] ?? null,
                'sector' => $fila['sector'] ?? null,
                'codigo_snies_programa' => trim((string) ($fila['codigo_snies_del_programa'] ?? '')) ?: null,
                'nombre_programa' => $nombrePrograma,
                'titulo_otorgado' => $fila['titulo_otorgado'] ?? null,
                'estado_programa' => $fila['estado_programa'] ?? 'Activo',
                'modalidad' => $fila['modalidad'] ?? null,
                'nivel_formacion_academica_id' => $nivelId,
            ];
        }

        if (empty($filasValidas)) {
            return;
        }

        // Un solo commit por lote (1.000 filas) en vez de uno por cada upsert/select individual.
        [$creados, $actualizados] = DB::transaction(function () use ($filasValidas) {
            $idsInstitucion = $this->upsertInstituciones($filasValidas);

            return $this->upsertProgramas($filasValidas, $idsInstitucion);
        });

        $importacion = SniesImportacion::find($this->importacionId);
        if ($importacion) {
            $importacion->increment('filas_procesadas', $filas->count());
            $importacion->increment('programas_creados', $creados);
            $importacion->increment('programas_actualizados', $actualizados);
            $importacion->increment('niveles_creados', $nivelesCreados);
        }
    }

    private function cargarNivelesSiHaceFalta(): void
    {
        if ($this->nivelesCargados) {
            return;
        }

        foreach (NivelFormacionAcademica::all() as $nivel) {
            $this->cacheNiveles[$this->claveNivel($nivel->nivel_academico, $nivel->nivel_formacion)] =
                $nivel->id_nivel_formacion_academica;
        }

        $this->nivelesCargados = true;
    }

    private function claveNivel(string $academico, string $formacion): string
    {
        return mb_strtolower(trim($academico)) . '|' . mb_strtolower(trim($formacion));
    }

    private function resolverNivelId(string $academico, string $formacion, int &$nivelesCreados): ?int
    {
        if ($academico === '' || $formacion === '') {
            return null;
        }

        $clave = $this->claveNivel($academico, $formacion);

        if (isset($this->cacheNiveles[$clave])) {
            return $this->cacheNiveles[$clave];
        }

        // Nivel nuevo, no visto en lotes anteriores de este mismo import: se crea y se cachea
        // para el resto del archivo (evita volver a intentar crearlo en cada lote siguiente).
        $nivel = NivelFormacionAcademica::create([
            'nivel_academico' => $academico,
            'nivel_formacion' => $formacion,
            'activo' => true,
        ]);

        $this->cacheNiveles[$clave] = $nivel->id_nivel_formacion_academica;
        $nivelesCreados++;

        return $nivel->id_nivel_formacion_academica;
    }

    /**
     * Upsert de instituciones del lote (deduplicadas) y devuelve un mapa
     * "codigo_institucion o nombre_institucion" => id_institucion para armar los programas.
     *
     * @param array<int, array<string, mixed>> $filasValidas
     * @return array<string, int>
     */
    private function upsertInstituciones(array $filasValidas): array
    {
        $conCodigo = [];
        $sinCodigo = [];

        foreach ($filasValidas as $fila) {
            if ($fila['codigo_institucion']) {
                $conCodigo[$fila['codigo_institucion']] = $fila;
            } else {
                $sinCodigo[$fila['nombre_institucion']] = $fila;
            }
        }

        if (!empty($conCodigo)) {
            $filasUpsert = array_map(fn (array $f) => [
                'codigo_institucion' => $f['codigo_institucion'],
                'codigo_institucion_padre' => $f['codigo_institucion_padre'],
                'nombre_institucion' => $f['nombre_institucion'],
                'estado_institucion' => $f['estado_institucion'],
                'caracter_academico' => $f['caracter_academico'],
                'sector' => $f['sector'],
                'created_at' => now(),
                'updated_at' => now(),
            ], array_values($conCodigo));

            InstitucionSnies::upsert(
                $filasUpsert,
                ['codigo_institucion'],
                ['codigo_institucion_padre', 'nombre_institucion', 'estado_institucion', 'caracter_academico', 'sector', 'updated_at']
            );
        }

        // Alta manual / sin código SNIES: rara en un archivo real, se deja como firstOrCreate
        // individual (no vale la pena un upsert por nombre, que no es una restricción única real).
        foreach (array_keys($sinCodigo) as $nombre) {
            InstitucionSnies::firstOrCreate(['nombre_institucion' => $nombre]);
        }

        $ids = [];

        if (!empty($conCodigo)) {
            InstitucionSnies::whereIn('codigo_institucion', array_keys($conCodigo))
                ->pluck('id_institucion', 'codigo_institucion')
                ->each(function ($id, $codigo) use (&$ids) {
                    $ids['codigo:' . $codigo] = $id;
                });
        }

        if (!empty($sinCodigo)) {
            InstitucionSnies::whereIn('nombre_institucion', array_keys($sinCodigo))
                ->pluck('id_institucion', 'nombre_institucion')
                ->each(function ($id, $nombre) use (&$ids) {
                    $ids['nombre:' . $nombre] = $id;
                });
        }

        return $ids;
    }

    /**
     * Upsert de programas del lote. Devuelve [creados, actualizados].
     *
     * @param array<int, array<string, mixed>> $filasValidas
     * @param array<string, int> $idsInstitucion
     * @return array{0: int, 1: int}
     */
    private function upsertProgramas(array $filasValidas, array $idsInstitucion): array
    {
        $codigosPrograma = array_values(array_unique(array_filter(array_column($filasValidas, 'codigo_snies_programa'))));

        $existentes = $codigosPrograma
            ? ProgramaFormacionEducativa::whereIn('codigo_snies_programa', $codigosPrograma)
                ->pluck('codigo_snies_programa')->flip()
            : collect();

        $creados = 0;
        $actualizados = 0;
        $filasUpsert = [];

        foreach ($filasValidas as $fila) {
            $clave = $fila['codigo_institucion'] ? 'codigo:' . $fila['codigo_institucion'] : 'nombre:' . $fila['nombre_institucion'];
            $institucionId = $idsInstitucion[$clave] ?? null;

            if (!$institucionId) {
                continue;
            }

            if ($fila['codigo_snies_programa'] && isset($existentes[$fila['codigo_snies_programa']])) {
                $actualizados++;
            } else {
                $creados++;
            }

            $fila = [
                'codigo_snies_programa' => $fila['codigo_snies_programa'],
                'institucion_id' => $institucionId,
                'nivel_formacion_academica_id' => $fila['nivel_formacion_academica_id'],
                'nombre_programa' => $fila['nombre_programa'],
                'titulo_otorgado' => $fila['titulo_otorgado'],
                'estado_programa' => $fila['estado_programa'],
                'modalidad' => $fila['modalidad'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Postgres revienta el upsert si el mismo código aparece dos veces en el mismo lote
            // ("ON CONFLICT DO UPDATE command cannot affect row a second time"). Poco común pero
            // posible si el Excel trae una fila duplicada; se deja la última versión.
            if ($fila['codigo_snies_programa']) {
                $filasUpsert[$fila['codigo_snies_programa']] = $fila;
            } else {
                $filasUpsert[] = $fila;
            }
        }

        if (!empty($filasUpsert)) {
            $filasUpsert = array_values($filasUpsert);
            ProgramaFormacionEducativa::upsert(
                $filasUpsert,
                ['codigo_snies_programa'],
                ['institucion_id', 'nivel_formacion_academica_id', 'nombre_programa', 'titulo_otorgado', 'estado_programa', 'modalidad', 'updated_at']
            );
        }

        return [$creados, $actualizados];
    }
}
