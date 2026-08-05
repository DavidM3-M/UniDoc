<?php

namespace App\Http\Controllers\IA;

use App\Http\Controllers\Controller;
use App\Models\Usuario\User;
use App\Models\TalentoHumano\Postulacion;
use App\Services\PuntajeAspiranteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AspiranteIAController extends Controller
{
    private string $baseUrl = 'https://api.groq.com/openai/v1';
    private string $model   = 'llama-3.3-70b-versatile';

    public function __construct(private readonly PuntajeAspiranteService $puntajeService) {}

    /**
     * Consulta libre sobre un aspirante individual o todos los de una convocatoria.
     * POST /api/ia/aspirante/consultar
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'pregunta'        => 'required|string|max:2000',
            'user_id'         => 'nullable|integer',
            'convocatoria_id' => 'nullable|integer',
            'buscar'          => 'nullable|string|max:300',
            'historial'       => 'nullable|array|max:20',
            'historial.*.role'    => 'required|string|in:user,assistant',
            'historial.*.content' => 'required|string|max:4000',
        ]);

        $apiKey = config('services.grok.api_key');
        if (!$apiKey) {
            return response()->json(['message' => 'API key de IA no configurada. Agrega GROK_API_KEY al .env'], 500);
        }

        $contexto    = '';
        $modoBusqueda = false;

        if ($request->user_id) {
            $contexto = $this->buildAspiranteContext((int) $request->user_id);
        } elseif ($request->convocatoria_id) {
            $contexto = $this->buildConvocatoriaContext((int) $request->convocatoria_id);
        } elseif ($request->buscar) {
            // Búsqueda explícita por nombre/cédula desde el frontend
            $contexto    = $this->buildBusquedaContext($request->buscar);
            $modoBusqueda = true;
        } else {
            // Sin contexto fijo: intentar extraer términos de la pregunta y buscar en BD
            $terminos = $this->extractSearchTerms($request->pregunta);
            if (!empty($terminos)) {
                $contexto    = $this->buildBusquedaContext(implode(' ', $terminos));
                $modoBusqueda = true;
            }
        }

        $sinContexto = !$contexto;

        $systemPrompt =
            "Eres el asistente de UniDoc, un colega digital que ayuda al equipo de Talento Humano, " .
            "Coordinadores, Vicerrectoría y Rectoría a revisar perfiles de aspirantes y sus documentos. " .
            "Tu tono es cercano, natural y directo — como el de alguien que conoce bien el sistema y " .
            "conversa con confianza, sin sonar a robot ni a informe oficial. " .
            "Usa frases cortas, evita la jerga burocrática y, cuando tengas varios datos, " .
            "preséntalos de forma visual (listas, separadores) pero sin exagerar el formato. " .
            "Responde siempre en español.\n\n" .

            "REGLAS QUE NO PUEDES ROMPER:\n" .
            "1. Solo habla de datos que aparezcan en la sección 'INFORMACIÓN DISPONIBLE'. " .
            "2. Nunca inventes, supongas ni rellenes huecos con información propia. " .
            "3. Si algo no está en los datos, díselo al usuario con naturalidad, sin dramas. " .
            "4. Para ordenar aspirantes usa únicamente el campo 'Puntaje total' del sistema.\n" .
            "5. DOCUMENTOS: Cuando el contexto incluya líneas con el formato '📄 [Nombre](URL)', " .
            "DEBES incluirlas tal cual en tu respuesta para que el usuario pueda abrir el archivo. " .
            "No modifiques, acortes ni omitas esas URLs. Escríbelas exactamente como aparecen en el contexto.\n" .

            ($sinContexto
                ? "\nATENCIÓN: No se encontraron datos en la base de datos para esta consulta. " .
                  "Puedes buscar por nombre, apellido o número de cédula. Ejemplo: '¿Qué documentos tiene Juan Pérez?' o 'Busca a María García'."
                : ($modoBusqueda
                    ? "\n\nRESULTADOS DE BÚSQUEDA EN BASE DE DATOS (ÚNICA FUENTE DE VERDAD):\n{$contexto}"
                    : "\n\nINFORMACIÓN DE ASPIRANTES DISPONIBLE (ÚNICA FUENTE DE VERDAD):\n{$contexto}"));

        // Construir mensajes: system + historial previo + pregunta actual
        $historial = collect($request->historial ?? [])
            ->map(fn($m) => ['role' => $m['role'], 'content' => $m['content']])
            ->values()
            ->toArray();

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $historial,
            [['role' => 'user', 'content' => $request->pregunta]]
        );

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'messages'    => $messages,
                'temperature' => 0.4,
                'max_tokens'  => 1500,
            ]);

        if ($response->failed()) {
            Log::error('Grok API consultar error: ' . $response->status() . ' ' . $response->body());
            return response()->json(['message' => 'Error al consultar la IA (' . $response->status() . '). Verifica la GROK_API_KEY.'], 500);
        }

        return response()->json([
            'respuesta' => $response->json('choices.0.message.content'),
        ]);
    }

    /**
     * Valida si un documento adjunto corresponde al tipo esperado.
     * POST /api/ia/documento/validar
     */
    public function validarDocumento(Request $request)
    {
        $request->validate([
            'documento_url'  => 'required|string',
            'tipo_esperado'  => 'required|string|max:200',
            'nombre_archivo' => 'nullable|string|max:500',
        ]);

        $apiKey = config('services.grok.api_key');
        if (!$apiKey) {
            return response()->json(['message' => 'API key de IA no configurada.'], 500);
        }

        $nombreArchivo = $request->nombre_archivo
            ?? basename(parse_url($request->documento_url, PHP_URL_PATH) ?? 'documento');

        $textoExtraido = $this->extraerTextoDeURL($request->documento_url);
        $tipoEsperado  = $request->tipo_esperado;

        $prompt =
            "Analiza el siguiente documento para determinar si corresponde a: \"{$tipoEsperado}\".\n\n" .
            "Nombre del archivo: {$nombreArchivo}\n" .
            "Contenido extraído del PDF:\n---\n{$textoExtraido}\n---\n\n" .
            "Instrucciones:\n" .
            "- Si el contenido extraído contiene texto real, analízalo para determinar si el documento es lo que dice ser.\n" .
            "- Verifica que el documento corresponda al tipo esperado basándote en el contenido (menciones a instituciones, títulos, fechas, firmas, sellos, etc.).\n" .
            "- Si el texto dice que es un PDF escaneado, valida solo por el nombre del archivo.\n" .
            "- Sé estricto: si el contenido NO corresponde al tipo esperado, marca valido=false.\n\n" .
            "Responde ÚNICAMENTE con este JSON válido (sin texto adicional, sin markdown):\n" .
            "{\n  \"valido\": true,\n  \"confianza\": \"alta\",\n  \"mensaje\": \"explicación breve en español de máximo 2 oraciones\",\n  \"advertencias\": []\n}";

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un validador experto de documentos académicos y laborales universitarios. Responde SOLO con el JSON solicitado, sin texto adicional.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.1,
                'max_tokens'  => 300,
            ]);

        if ($response->failed()) {
            Log::error('Grok API validar error: ' . $response->status() . ' ' . $response->body());
            return response()->json(['message' => 'Error al validar el documento.'], 500);
        }

        $content = $response->json('choices.0.message.content') ?? '';

        // Extraer JSON de la respuesta aunque venga envuelto en markdown
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if ($decoded) {
                return response()->json($decoded);
            }
        }

        return response()->json([
            'valido'       => null,
            'confianza'    => 'baja',
            'mensaje'      => trim($content),
            'advertencias' => [],
        ]);
    }

    // ─── Helpers de contexto ────────────────────────────────────────────────────

    private function buildAspiranteContext(int $userId): string
    {
        $user = User::with([
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia',
            'produccionAcademicaUsuario',
        ])->find($userId);

        if (!$user) return 'Aspirante no encontrado.';

        try {
            $puntaje = $this->puntajeService->calcular($userId);
        } catch (\Exception) {
            $puntaje = ['total' => 0, 'estudios' => 0, 'idiomas' => 0, 'experiencia' => 0];
        }

        $lines = [
            "=== {$user->primer_nombre} {$user->primer_apellido} (ID: {$user->id}) ===",
            "Puntaje total: {$puntaje['total']} pts | Estudios: {$puntaje['estudios']} | Idiomas: {$puntaje['idiomas']} | Experiencia: {$puntaje['experiencia']}",
            "",
            "ESTUDIOS:",
        ];

        foreach ($user->estudiosUsuario as $e) {
            $grad    = ($e->graduado === 'Si') ? 'Graduado' : 'En curso';
            $lines[] = "  - {$e->tipo_estudio}: \"{$e->titulo_estudio}\" en {$e->institucion} [{$grad}]";
            foreach ($e->documentosEstudio as $doc) {
                if (!empty($doc->archivo)) {
                    $url     = asset('storage/' . $doc->archivo);
                    $nombre  = basename($doc->archivo);
                    $lines[] = "    📄 [Documento estudio: {$nombre}]({$url})";
                }
            }
        }

        $lines[] = "";
        $lines[] = "IDIOMAS:";
        foreach ($user->idiomasUsuario as $i) {
            $lines[] = "  - {$i->idioma} Nivel {$i->nivel}";
            foreach ($i->documentosIdioma as $doc) {
                if (!empty($doc->archivo)) {
                    $url     = asset('storage/' . $doc->archivo);
                    $nombre  = basename($doc->archivo);
                    $lines[] = "    📄 [Certificado idioma {$i->idioma}: {$nombre}]({$url})";
                }
            }
        }

        $lines[] = "";
        $lines[] = "EXPERIENCIA LABORAL:";
        foreach ($user->experienciasUsuario as $exp) {
            $hasta   = $exp->trabajo_actual ? 'Actual' : ($exp->fecha_finalizacion ?? 'N/D');
            $lines[] = "  - {$exp->tipo_experiencia}: {$exp->cargo} en {$exp->institucion_experiencia} ({$exp->fecha_inicio} - {$hasta})";
            foreach ($exp->documentosExperiencia as $doc) {
                if (!empty($doc->archivo)) {
                    $url     = asset('storage/' . $doc->archivo);
                    $nombre  = basename($doc->archivo);
                    $lines[] = "    📄 [Certificado experiencia - {$exp->cargo}: {$nombre}]({$url})";
                }
            }
        }

        $lines[] = "";
        $lines[] = "PRODUCCIÓN ACADÉMICA: " . $user->produccionAcademicaUsuario->count() . " ítem(s) registrado(s)";

        return implode("\n", $lines);
    }

    private function buildConvocatoriaContext(int $convocatoriaId): string
    {
        $postulaciones = Postulacion::where('convocatoria_id', $convocatoriaId)
            ->with([
                'usuarioPostulacion.estudiosUsuario',
                'usuarioPostulacion.idiomasUsuario',
                'usuarioPostulacion.experienciasUsuario',
            ])
            ->get();

        if ($postulaciones->isEmpty()) {
            return "No hay aspirantes postulados a la convocatoria #{$convocatoriaId}.";
        }

        $aspirantes = [];
        foreach ($postulaciones as $post) {
            $user = $post->usuarioPostulacion;
            if (!$user) continue;
            try {
                $p = $this->puntajeService->calcular($user->id);
            } catch (\Exception) {
                $p = ['total' => 0, 'estudios' => 0, 'idiomas' => 0, 'experiencia' => 0];
            }
            $aspirantes[] = ['user' => $user, 'puntaje' => $p];
        }

        usort($aspirantes, fn($a, $b) => $b['puntaje']['total'] - $a['puntaje']['total']);

        $lines = ["=== ASPIRANTES CONVOCATORIA #{$convocatoriaId} (ordenados por puntaje) ===", ""];

        foreach ($aspirantes as $idx => $item) {
            $u = $item['user'];
            $p = $item['puntaje'];
            $pos     = $idx + 1;
            $lines[] = "#{$pos}. {$u->primer_nombre} {$u->primer_apellido} — {$p['total']} pts";
            $lines[] = "    Estudios: {$p['estudios']} | Idiomas: {$p['idiomas']} | Experiencia: {$p['experiencia']}";
            $lines[] = "    Estudios registrados: " . $u->estudiosUsuario->count() .
                       " | Idiomas: " . $u->idiomasUsuario->count() .
                       " | Experiencias: " . $u->experienciasUsuario->count();
        }

        return implode("\n", $lines);
    }

    /**
     * Busca usuarios en la BD por nombre, apellido o número de identificación
     * y construye un contexto detallado con sus datos para la IA.
     */
    private function buildBusquedaContext(string $query): string
    {
        $query = trim($query);

        // Separar términos de búsqueda y escapar caracteres especiales de LIKE
        $terminos = array_filter(
            array_unique(array_map('trim', preg_split('/\s+/', $query))),
            fn($t) => mb_strlen($t) >= 2
        );

        if (empty($terminos)) {
            return "Consulta de búsqueda vacía o demasiado corta.";
        }

        $users = User::with([
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia',
            'produccionAcademicaUsuario',
            'postulacionesUsuario.convocatoriaPostulacion',
            'roles',
        ])
        ->where(function ($q) use ($terminos) {
            foreach ($terminos as $t) {
                $like = '%' . addcslashes($t, '%_\\') . '%';
                $q->orWhere('primer_nombre',         'LIKE', $like)
                  ->orWhere('segundo_nombre',         'LIKE', $like)
                  ->orWhere('primer_apellido',        'LIKE', $like)
                  ->orWhere('segundo_apellido',       'LIKE', $like)
                  ->orWhere('numero_identificacion',  'LIKE', $like)
                  ->orWhere('email',                  'LIKE', $like);
            }
        })
        ->limit(10)
        ->get();

        if ($users->isEmpty()) {
            return "No se encontraron usuarios en la base de datos con el criterio: \"{$query}\".";
        }

        $lines = [
            "=== BÚSQUEDA: \"{$query}\" — {$users->count()} usuario(s) encontrado(s) ===",
            "",
        ];

        foreach ($users as $user) {
            $rol      = $user->roles->first()?->name ?? 'Sin rol';
            $nombreC  = trim("{$user->primer_nombre} {$user->segundo_nombre} {$user->primer_apellido} {$user->segundo_apellido}");

            try {
                $p = $this->puntajeService->calcular($user->id);
            } catch (\Exception) {
                $p = ['total' => 0, 'estudios' => 0, 'idiomas' => 0, 'experiencia' => 0];
            }

            $lines[] = "── {$nombreC} (ID sistema: {$user->id}) ──";
            $lines[] = "  Rol: {$rol}";
            $lines[] = "  Cédula/Identificación: {$user->numero_identificacion} ({$user->tipo_identificacion})";
            $lines[] = "  Email: {$user->email}";
            $lines[] = "  Puntaje total: {$p['total']} pts  (Estudios: {$p['estudios']} | Idiomas: {$p['idiomas']} | Experiencia: {$p['experiencia']})";

            // Estudios
            if ($user->estudiosUsuario->isNotEmpty()) {
                $lines[] = "  ESTUDIOS:";
                foreach ($user->estudiosUsuario as $e) {
                    $grad    = ($e->graduado === 'Si') ? 'Graduado' : 'En curso';
                    $lines[] = "    · {$e->tipo_estudio}: \"{$e->titulo_estudio}\" — {$e->institucion} [{$grad}]";
                    foreach ($e->documentosEstudio as $doc) {
                        if (!empty($doc->archivo)) {
                            $url     = asset('storage/' . $doc->archivo);
                            $nombre  = basename($doc->archivo);
                            $lines[] = "      📄 [Documento estudio: {$nombre}]({$url})";
                        }
                    }
                }
            } else {
                $lines[] = "  ESTUDIOS: Sin estudios registrados.";
            }

            // Idiomas
            if ($user->idiomasUsuario->isNotEmpty()) {
                $lines[] = "  IDIOMAS:";
                foreach ($user->idiomasUsuario as $i) {
                    $lines[] = "    · {$i->idioma} — Nivel {$i->nivel}";
                    foreach ($i->documentosIdioma as $doc) {
                        if (!empty($doc->archivo)) {
                            $url     = asset('storage/' . $doc->archivo);
                            $nombre  = basename($doc->archivo);
                            $lines[] = "      📄 [Certificado {$i->idioma}: {$nombre}]({$url})";
                        }
                    }
                }
            } else {
                $lines[] = "  IDIOMAS: Sin idiomas registrados.";
            }

            // Experiencia
            if ($user->experienciasUsuario->isNotEmpty()) {
                $lines[] = "  EXPERIENCIA LABORAL:";
                foreach ($user->experienciasUsuario as $exp) {
                    $hasta   = $exp->trabajo_actual ? 'Actual' : ($exp->fecha_finalizacion ?? 'N/D');
                    $lines[] = "    · {$exp->tipo_experiencia}: {$exp->cargo} en {$exp->institucion_experiencia} ({$exp->fecha_inicio} – {$hasta})";
                    foreach ($exp->documentosExperiencia as $doc) {
                        if (!empty($doc->archivo)) {
                            $url     = asset('storage/' . $doc->archivo);
                            $nombre  = basename($doc->archivo);
                            $lines[] = "      📄 [Certificado experiencia {$exp->cargo}: {$nombre}]({$url})";
                        }
                    }
                }
            } else {
                $lines[] = "  EXPERIENCIA LABORAL: Sin experiencias registradas.";
            }

            // Producción académica
            $numProd = $user->produccionAcademicaUsuario->count();
            $lines[] = "  PRODUCCIÓN ACADÉMICA: {$numProd} ítem(s) registrado(s).";

            // Postulaciones
            $postulaciones = $user->postulacionesUsuario;
            if ($postulaciones->isNotEmpty()) {
                $lines[] = "  POSTULACIONES:";
                foreach ($postulaciones as $post) {
                    $conv    = $post->convocatoriaPostulacion;
                    $nomConv = $conv ? ($conv->nombre_convocatoria ?? "Convocatoria #{$conv->id_convocatoria}") : 'Convocatoria desconocida';
                    $lines[] = "    · {$nomConv} — Estado: {$post->estado_postulacion}";
                }
            } else {
                $lines[] = "  POSTULACIONES: Sin postulaciones registradas.";
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Extrae términos de búsqueda relevantes (nombres propios y números de cédula)
     * de una pregunta en lenguaje natural.
     */
    private function extractSearchTerms(string $pregunta): array
    {
        // Palabras comunes en español que no son nombres propios
        $stopWords = [
            'los', 'las', 'que', 'con', 'por', 'para', 'del', 'una', 'uno',
            'tiene', 'esta', 'están', 'son', 'sus', 'cuales', 'cual', 'cuántos',
            'cómo', 'quiénes', 'quién', 'cuáles', 'cómo', 'quiero', 'buscar',
            'busca', 'dónde', 'donde', 'información', 'informacion', 'perfil',
            'documentos', 'documento', 'aspirante', 'usuario', 'datos',
            'registrado', 'registrados', 'sistema', 'hay', 'ver', 'obtener',
            'me', 'mi', 'sobre', 'del', 'dime', 'muéstrame', 'encuentras',
        ];

        // Números de cédula / identificación (6+ dígitos consecutivos)
        preg_match_all('/\b\d{6,}\b/', $pregunta, $numMatches);

        // Palabras capitalizadas (posibles nombres propios), mínimo 3 caracteres
        preg_match_all('/\b[A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,}\b/u', $pregunta, $nameMatches);

        $nombres = array_filter(
            $nameMatches[0],
            fn($w) => !in_array(mb_strtolower($w), $stopWords)
        );

        $terminos = array_merge($numMatches[0], array_values($nombres));

        return array_unique($terminos);
    }

    /**
     * Extrae texto de un PDF usando pdftotext (poppler) con fallback a Ghostscript.
     * Acepta una URL pública o una URL de /storage/.
     */
    private function extraerTextoDeURL(string $url): string
    {
        try {
            $urlPath     = parse_url($url, PHP_URL_PATH) ?? '';
            $storagePath = preg_replace('#^/storage/#', '', $urlPath);

            if (!$storagePath || !Storage::disk('public')->exists($storagePath)) {
                return 'No se encontró el archivo en el servidor (verifica la URL).';
            }

            $absolutePath = Storage::disk('public')->path($storagePath);
            $ext          = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                $fileSize = Storage::disk('public')->size($storagePath);
                return "Archivo {$ext} de " . round($fileSize / 1024) . " KB. Solo se puede analizar el nombre del archivo.";
            }

            // ── Método 1: pdftotext (poppler-utils) — más preciso ──────────
            $tempOut = sys_get_temp_dir() . '/ia_' . uniqid() . '.txt';
            exec(
                sprintf('pdftotext -layout %s %s 2>/dev/null', escapeshellarg($absolutePath), escapeshellarg($tempOut)),
                $out1,
                $code1
            );
            if ($code1 === 0 && file_exists($tempOut) && filesize($tempOut) > 10) {
                $text = file_get_contents($tempOut);
                @unlink($tempOut);
                $cleaned = trim(preg_replace('/\s+/', ' ', $text));
                if ($cleaned) {
                    return mb_substr($cleaned, 0, 6000);
                }
            }
            @unlink($tempOut);

            // ── Método 2: Ghostscript — fallback ───────────────────────────
            $tempGs = sys_get_temp_dir() . '/ia_gs_' . uniqid() . '.txt';
            exec(
                sprintf(
                    'gs -sDEVICE=txtwrite -dNOPAUSE -dBATCH -dQUIET -sOutputFile=%s %s 2>/dev/null',
                    escapeshellarg($tempGs),
                    escapeshellarg($absolutePath)
                ),
                $out2,
                $code2
            );
            if ($code2 === 0 && file_exists($tempGs) && filesize($tempGs) > 10) {
                $text = file_get_contents($tempGs);
                @unlink($tempGs);
                $cleaned = trim(preg_replace('/\s+/', ' ', $text));
                if ($cleaned) {
                    return mb_substr($cleaned, 0, 6000);
                }
            }
            @unlink($tempGs);

            // ── Sin texto extraíble (PDF escaneado / imagen) ───────────────
            $fileSize = Storage::disk('public')->size($storagePath);
            return sprintf(
                'PDF escaneado o basado en imágenes (%s KB). No se puede extraer texto automáticamente. ' .
                'La validación se basará únicamente en el nombre del archivo.',
                round($fileSize / 1024)
            );

        } catch (\Exception $e) {
            Log::warning('IA: Error extrayendo texto de documento: ' . $e->getMessage());
            return 'Error al procesar el documento.';
        }
    }
}
