<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalises role-name strings stored in JSON columns so they match the
 * Spatie role names used throughout the application (no accent marks).
 *
 * Before: 'Vicerrectoría', 'Rectoría'
 * After : 'Vicerrectoria', 'Rectoria'
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeConvocatoriaAvalesEstablecidos('Vicerrectoría', 'Vicerrectoria');
        $this->normalizeConvocatoriaAvalesEstablecidos('Rectoría', 'Rectoria');

        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrectoria' WHERE aval = 'Vicerrectoría'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rectoria' WHERE aval = 'Rectoría'");
    }

    public function down(): void
    {
        $this->normalizeConvocatoriaAvalesEstablecidos('Vicerrectoria', 'Vicerrectoría');
        $this->normalizeConvocatoriaAvalesEstablecidos('Rectoria', 'Rectoría');

        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrectoría' WHERE aval = 'Vicerrectoria'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rectoría' WHERE aval = 'Rectoria'");
    }

    private function normalizeConvocatoriaAvalesEstablecidos(string $search, string $replace): void
    {
        $records = DB::table('convocatorias')
            ->select('id_convocatoria', 'avales_establecidos')
            ->whereNotNull('avales_establecidos')
            ->get();

        foreach ($records as $record) {
            $decoded = json_decode($record->avales_establecidos, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                continue;
            }

            $updated = $this->recursiveReplace($decoded, $search, $replace);
            if ($updated !== $decoded) {
                DB::table('convocatorias')
                    ->where('id_convocatoria', $record->id_convocatoria)
                    ->update(['avales_establecidos' => json_encode($updated, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function recursiveReplace(array $value, string $search, string $replace): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->recursiveReplace($item, $search, $replace);
            } elseif (is_string($item)) {
                $value[$key] = str_replace($search, $replace, $item);
            }
        }

        return $value;
    }
};
