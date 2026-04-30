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
        ]);

        $apiKey = config('services.grok.api_key');
        if (!$apiKey) {
            return response()->json(['message' => 'API key de IA no configurada. Agrega GROK_API_KEY al .env'], 500);
        }

        $contexto = '';
        if ($request->user_id) {
            $contexto = $this->buildAspiranteContext((int) $request->user_id);
        } elseif ($request->convocatoria_id) {
            $contexto = $this->buildConvocatoriaContext((int) $request->convocatoria_id);
        }

        $sinContexto = !$contexto;

        $systemPrompt =
            "Eres un asistente experto en evaluación de docentes universitarios para el sistema UniDoc de la Universidad Autónoma. " .
            "Ayudas a los paneles de Talento Humano, Coordinadores, Vicerrectoría y Rectoría a evaluar y comparar aspirantes. " .
            "Responde siempre en español, de forma clara, objetiva y profesional.\n\n" .

            "REGLAS ESTRICTAS — DEBES SEGUIRLAS SIN EXCEPCIÓN:\n" .
            "1. SOLO puedes hablar de aspirantes o datos que aparezcan explícitamente en la sección 'INFORMACIÓN DE ASPIRANTES DISPONIBLE'. " .
            "2. NUNCA inventes, supongas ni completes información que no esté en esa sección. " .
            "3. NUNCA uses nombres, puntajes, títulos, publicaciones ni ningún dato que no haya sido proporcionado. " .
            "4. Si no tienes información de aspirantes, responde EXACTAMENTE: " .
            "'No tengo datos de aspirantes en este momento. Por favor, abre el perfil de un aspirante o selecciona una convocatoria activa para que pueda analizar información real.' " .
            "5. Si el usuario pide algo que no puedes responder con los datos disponibles, díselo claramente en lugar de inventar. " .
            "6. Al ordenar los mejores aspirantes, usa ÚNICAMENTE los puntajes del sistema (campo 'Puntaje total') — no calcules ni estimes puntajes propios.\n" .

            ($sinContexto
                ? "\nATENCIÓN: No se ha proporcionado información de aspirantes. Aplica la regla 4 para CUALQUIER pregunta sobre aspirantes o candidatos."
                : "\n\nINFORMACIÓN DE ASPIRANTES DISPONIBLE (ÚNICA FUENTE DE VERDAD):\n{$contexto}");

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $request->pregunta],
                ],
                'temperature' => 0.3,
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
            'estudiosUsuario',
            'idiomasUsuario',
            'experienciasUsuario',
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
        }

        $lines[] = "";
        $lines[] = "IDIOMAS:";
        foreach ($user->idiomasUsuario as $i) {
            $lines[] = "  - {$i->idioma} Nivel {$i->nivel}";
        }

        $lines[] = "";
        $lines[] = "EXPERIENCIA LABORAL:";
        foreach ($user->experienciasUsuario as $exp) {
            $hasta   = $exp->trabajo_actual ? 'Actual' : ($exp->fecha_finalizacion ?? 'N/D');
            $lines[] = "  - {$exp->tipo_experiencia}: {$exp->cargo} en {$exp->institucion_experiencia} ({$exp->fecha_inicio} - {$hasta})";
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
