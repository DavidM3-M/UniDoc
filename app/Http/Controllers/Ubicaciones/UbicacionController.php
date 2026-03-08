<?php

namespace App\Http\Controllers\Ubicaciones;

// Creamos un controlador para manejar la ubicación
use Illuminate\Http\Request;
use App\Models\Ubicacion\Municipio;
use App\Models\Ubicacion\Departamento;
use App\Models\Ubicacion\Pais;
use Illuminate\Support\Facades\Validator;

class UbicacionController
{
    // Cargar datos de ubicación desde un archivo CSV
    public function uploadCsv(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'paises_csv' => 'nullable|mimes:csv,txt|max:2048',
            'departamentos_csv' => 'nullable|mimes:csv,txt|max:2048',
            'municipios_csv' => 'nullable|mimes:csv,txt|max:2048',
        ]);

        // Si la validación falla, se devuelve un mensaje de error
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        // Procesar los archivos CSV
        if ($request->hasFile('paises_csv')) {
            $this->procesarPaises($request->file('paises_csv'));
        }
        // Procesar los archivos CSV
        if ($request->hasFile('departamentos_csv')) {
            $this->procesarDepartamentos($request->file('departamentos_csv'));
        }
        // Procesar los archivos CSV
        if ($request->hasFile('municipios_csv')) {
            $this->procesarMunicipios($request->file('municipios_csv'));
        }
        // Devolver respuesta
        return response()->json(['message' => 'Datos cargados correctamente'], 201);
    }

    // Procesar archivo CSV de paises
    private function procesarPaises($file)
    {
        // Abrir el archivo CSV
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle, 1000, ';'); // Ignorar encabezado
        // Leer el archivo CSV
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            if (count($data) < 2) continue;
            Pais::updateOrCreate(
                ['id' => (int) $data[0]],
                ['nombre' => strip_tags(trim($data[1]))]
            );
        }
        // Cerrar el archivo CSV
        fclose($handle);
    }

    // Procesar archivo CSV de departamentos
    private function procesarDepartamentos($file)
    {
        // Abrir el archivo CSV
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle, 1000, ';'); // Ignorar encabezado
        // Leer el archivo CSV
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            if (count($data) < 3) continue;
            Departamento::updateOrCreate(
                ['id' => (int) $data[0]],
                [
                    'nombre'   => strip_tags(trim($data[1])),
                    'pais_id'  => (int) $data[2]
                ]
            );
        }
        // Cerrar el archivo CSV
        fclose($handle);
    }

    // Procesar archivo CSV de municipios
    private function procesarMunicipios($file)
    {
        // Abrir el archivo CSV
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle, 1000, ';'); // Ignorar encabezado
        // Leer el archivo CSV
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            if (count($data) < 3) continue;
            Municipio::updateOrCreate(
                ['id' => (int) $data[0]],
                [
                    'nombre'          => strip_tags(trim($data[2])),
                    'departamento_id' => (int) $data[1]
                ]
            );
        }
        // Cerrar el archivo CSV
        fclose($handle);
    }

    // Obtener paises
    public function obtenerPaises()
    {
        return response()->json(Pais::all(), 200);
    }
    // Obtener departamentos
    public function obtenerDepartamentos()
    {
        return response()->json(Departamento::all(), 200);
    }
    // Obtener municipios
    public function obtenerMunicipios()
    {
        return response()->json(Municipio::all(), 200);
    }

    
    // Obtener departamentos por país
    public function obtenerDepartamentosPorPais($pais_id)
    {
        // Buscar los departamentos por país
        $departamentos = Departamento::where('pais_id', $pais_id)->get();
        // Devolver respuesta
        return response()->json($departamentos, 200);
    }


    // Obtener municipios por departamento
    public function obtenerMunicipiosPorDepartamento($departamento_id)
    {
        // Buscar los municipios por departamento
        $municipios = Municipio::where('departamento_id', $departamento_id)->get();
        // Devolver respuesta
        return response()->json($municipios, 200);
    }


    // Obtener información completa de ubicación por municipio_id
    public function obtenerUbicacionPorMunicipio($municipio_id)
    {
        // Buscar el municipio con su departamento y país
        $municipio = Municipio::with('departamentoMunicipio.paisDepartamento')->find($municipio_id);

        if (!$municipio) {
            return response()->json(['error' => 'Municipio no encontrado'], 404);
        }

        return response()->json([
            'municipio_id' => $municipio->id_municipio,
            'municipio_nombre' => $municipio->nombre,
            'departamento_id' => $municipio->departamentoMunicipio?->id_departamento,
            'departamento_nombre' => $municipio->departamentoMunicipio?->nombre,
            'pais_id' => $municipio->departamentoMunicipio?->paisDepartamento?->id_pais,
            'pais_nombre' => $municipio->departamentoMunicipio?->paisDepartamento?->nombre,
        ], 200);
    }
}
