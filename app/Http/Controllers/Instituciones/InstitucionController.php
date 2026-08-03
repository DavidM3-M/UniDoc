<?php

namespace App\Http\Controllers\Instituciones;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstitucionController
{
    // Catálogo oficial de Instituciones de Educación Superior (MEN) publicado en datos.gov.co
    private const DATASET_URL = 'https://www.datos.gov.co/api/v3/views/n5yy-8nav/query.json';
    private const CACHE_KEY = 'instituciones_educativas_men';
    private const CACHE_DIAS = 7;

    // Método para obtener el catálogo de instituciones (con caché de 7 días)
    public function obtenerInstituciones()
    {
        $instituciones = Cache::get(self::CACHE_KEY);

        if ($instituciones === null) {
            $instituciones = $this->consultarCatalogoRemoto();

            // Solo cacheamos si la consulta tuvo éxito, para no "congelar" un fallo transitorio
            if (!empty($instituciones)) {
                Cache::put(self::CACHE_KEY, $instituciones, now()->addDays(self::CACHE_DIAS));
            }
        }

        return response()->json(['instituciones' => $instituciones ?? []]);
    }

    private function consultarCatalogoRemoto(): array
    {
        try {
            $response = Http::timeout(45)->get(self::DATASET_URL);

            if (!$response->successful()) {
                Log::warning('Catálogo de instituciones (datos.gov.co) respondió con error HTTP ' . $response->status());
                return [];
            }

            return collect($response->json())
                ->filter(fn ($fila) => ($fila['estado'] ?? null) === 'Activa en la Fecha de Actualizacion')
                ->map(fn ($fila) => [
                    'nombre' => $fila['nombre_instituci_n'] ?? null,
                    'departamento' => $fila['departamento_domicilio'] ?? null,
                    'municipio' => $fila['municipio_domicilio'] ?? null,
                    'sector' => $fila['sector'] ?? null,
                ])
                ->filter(fn ($fila) => !empty($fila['nombre']))
                ->unique('nombre')
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('No se pudo consultar el catálogo de instituciones (datos.gov.co): ' . $e->getMessage());
            return [];
        }
    }
}
