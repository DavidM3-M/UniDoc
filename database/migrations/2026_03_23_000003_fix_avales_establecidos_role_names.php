<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalises role-name strings stored in JSON columns so they match the
 * Spatie role names used throughout the application (no accent marks).
 *
 * Before: 'Vicerrector├¡a', 'Rector├¡a'
 * After : 'Vicerrectoria', 'Rectoria'
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeConvocatoriaAvalesEstablecidos('Vicerrector├¡a', 'Vicerrectoria');
        $this->normalizeConvocatoriaAvalesEstablecidos('Rector├¡a', 'Rectoria');

        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrectoria' WHERE aval = 'Vicerrector├¡a'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rectoria' WHERE aval = 'Rector├¡a'");
    }

    public function down(): void
    {
        $this->normalizeConvocatoriaAvalesEstablecidos('Vicerrectoria', 'Vicerrector├¡a');
        $this->normalizeConvocatoriaAvalesEstablecidos('Rectoria', 'Rector├¡a');

        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrector├¡a' WHERE aval = 'Vicerrectoria'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rector├¡a' WHERE aval = 'Rectoria'");
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
