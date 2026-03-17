<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfImportService
{
    private const MIN_TEXT_LENGTH = 100;
    private const MAX_TEXT_CHARS  = 12000;
    private const API_TIMEOUT     = 60;

    /**
     * Extract product candidates from a PDF file.
     *
     * Returns an array of normalized product arrays ready for user review.
     * Each item maps to Product fillable fields (name, sku, collection, etc.).
     *
     * @throws \RuntimeException on unrecoverable errors
     */
    public function extract(string $filePath): array
    {
        $text = $this->extractText($filePath);

        if (! $this->isTextUsable($text)) {
            throw new \RuntimeException(
                'PDF non leggibile o vuoto. Assicurati che il PDF contenga testo selezionabile (non solo immagini scansionate).'
            );
        }

        return $this->parseWithClaude($text);
    }

    // ── Text extraction ────────────────────────────────────────────────────

    private function extractText(string $filePath): string
    {
        $escaped = escapeshellarg($filePath);
        $output  = shell_exec("pdftotext -layout {$escaped} - 2>/dev/null");

        if ($output === null || $output === false) {
            return '';
        }

        // Normalise whitespace
        $text = preg_replace('/[ \t]+/', ' ', (string) $output);
        $text = preg_replace('/\n{4,}/', "\n\n\n", $text);

        return substr(trim($text), 0, self::MAX_TEXT_CHARS);
    }

    private function isTextUsable(string $text): bool
    {
        return mb_strlen(trim($text)) >= self::MIN_TEXT_LENGTH;
    }

    // ── Claude Haiku API ───────────────────────────────────────────────────

    private function parseWithClaude(string $content): array
    {
        $apiKey = config('services.anthropic.api_key');
        $model  = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        if (! $apiKey) {
            throw new \RuntimeException(
                'ANTHROPIC_API_KEY non configurata. Aggiungila al file .env.'
            );
        }

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(self::API_TIMEOUT)
          ->post('https://api.anthropic.com/v1/messages', [
              'model'      => $model,
              'max_tokens' => 4096,
              'messages'   => [
                  ['role' => 'user', 'content' => $this->buildPrompt($content)],
              ],
          ]);

        if (! $response->successful()) {
            Log::error('PdfImportService: Claude API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Errore Claude API: HTTP ' . $response->status());
        }

        $text = $response->json('content.0.text', '');
        return $this->parseResponse($text);
    }

    private function buildPrompt(string $content): string
    {
        return "Sei un assistente specializzato nel settore dell'arredamento italiano.\n\n"
            . "Analizza questo testo estratto dal listino/catalogo PDF di un fornitore di arredamento "
            . "e identifica tutti i prodotti con i relativi dati.\n\n"
            . "TESTO DEL PDF:\n{$content}\n\n"
            . "Per ogni prodotto trovato restituisci un oggetto con questo schema:\n"
            . "{\n"
            . "  \"name\": \"nome prodotto (obbligatorio)\",\n"
            . "  \"sku\": \"codice articolo oppure null\",\n"
            . "  \"collection\": \"nome della serie/collezione oppure null\",\n"
            . "  \"description\": \"descrizione breve max 300 caratteri oppure null\",\n"
            . "  \"materials\": {\"componente\": \"materiale\"} oppure null,\n"
            . "  \"finishes\": [\"finitura1\", \"finitura2\"] oppure null,\n"
            . "  \"colors\": [\"colore1\", \"colore2\"] oppure null,\n"
            . "  \"dimensions\": {\"width\": numero, \"depth\": numero, \"height\": numero} oppure null,\n"
            . "  \"price_list\": numero decimale oppure null\n"
            . "}\n\n"
            . "Regole:\n"
            . "- Includi TUTTI i prodotti trovati, anche con dati parziali\n"
            . "- price_list: solo il numero (es. 1250.00), senza simboli €\n"
            . "- dimensions in centimetri se possibile\n"
            . "- materials: chiave = componente (es. \"struttura\", \"piano\", \"seduta\"), valore = materiale\n"
            . "- Se un campo non è presente nel testo, usa null\n\n"
            . "Rispondi SOLO con un array JSON valido. Se non trovi prodotti rispondi [].";
    }

    private function parseResponse(string $text): array
    {
        if (! preg_match('/(\[[\s\S]*\])/m', $text, $m)) {
            Log::warning('PdfImportService: nessun array JSON nella risposta', [
                'raw' => substr($text, 0, 500),
            ]);
            return [];
        }

        $decoded = json_decode($m[1], true);
        if (! is_array($decoded)) {
            Log::warning('PdfImportService: JSON non valido', ['raw' => substr($text, 0, 500)]);
            return [];
        }

        return array_values(array_filter(
            array_map(fn($p) => $this->normalizeProduct($p), $decoded),
            fn($p) => ! empty($p['name'])
        ));
    }

    // ── Normalisation ──────────────────────────────────────────────────────

    private function normalizeProduct(array $p): array
    {
        return [
            'name'        => trim((string) ($p['name'] ?? '')),
            'sku'         => $this->str($p['sku'] ?? null),
            'collection'  => $this->str($p['collection'] ?? null),
            'description' => $this->str($p['description'] ?? null),
            'materials'   => is_array($p['materials'] ?? null) ? $p['materials'] : null,
            'finishes'    => is_array($p['finishes'] ?? null) ? array_values($p['finishes']) : null,
            'colors'      => is_array($p['colors'] ?? null) ? array_values($p['colors']) : null,
            'dimensions'  => $this->normalizeDimensions($p['dimensions'] ?? null),
            'price_list'  => $this->numericOrNull($p['price_list'] ?? null),
        ];
    }

    private function normalizeDimensions(mixed $v): ?array
    {
        if (! is_array($v)) {
            return null;
        }
        $d = array_filter([
            'width'  => $this->numericOrNull($v['width'] ?? null),
            'depth'  => $this->numericOrNull($v['depth'] ?? null),
            'height' => $this->numericOrNull($v['height'] ?? null),
        ], fn($x) => $x !== null);

        return empty($d) ? null : $d;
    }

    private function numericOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = filter_var($v, FILTER_VALIDATE_FLOAT);
        return $n !== false ? (float) $n : null;
    }

    private function str(mixed $v): ?string
    {
        if ($v === null || $v === '' || strtolower((string) $v) === 'null') {
            return null;
        }
        return trim((string) $v);
    }
}
