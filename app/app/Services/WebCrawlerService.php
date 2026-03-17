<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebCrawlerService
{
    private const MAX_CONTENT_CHARS = 8000;
    private const FETCH_TIMEOUT     = 20;

    /**
     * Fetch a URL and extract products/collections via Claude Haiku.
     *
     * Returns a unified item array compatible with both:
     *  - DiscoverWebsiteJob (uses: name, type, url, h2s, imported)
     *  - ViewWebDiscovery import (uses: name, collection, description, materials, finishes, colors, url)
     *
     * @return array[]
     */
    public function crawl(string $url): array
    {
        $html = $this->fetchPage($url);
        $text = $this->extractText($html);

        if (empty(trim($text))) {
            throw new \RuntimeException('Pagina vuota o non leggibile: ' . $url);
        }

        $items = $this->extractWithClaude($text, $url);

        // Ensure required job fields are present
        return array_map(function ($item) {
            $item['imported'] = false;
            $item['url']      = $item['source_url'] ?? $item['url'] ?? '';
            $item['h2s']      = $item['h2s'] ?? [];
            $item['type']     = $item['type'] ?? 'product';
            return $item;
        }, $items);
    }

    // ── HTTP fetch ─────────────────────────────────────────────────────────

    private function fetchPage(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Studio3GHD/1.0; product-catalog-bot)',
            'Accept'     => 'text/html,application/xhtml+xml',
        ])
            ->timeout(self::FETCH_TIMEOUT)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} per $url");
        }

        return $response->body();
    }

    // ── HTML → testo pulito ────────────────────────────────────────────────

    private function extractText(string $html): string
    {
        $html = preg_replace(
            '/<(script|style|svg|nav|footer|header|noscript)[^>]*>[\s\S]*?<\/\1>/i',
            '',
            $html
        );
        $html = preg_replace('/<(h[1-6]|p|li|td|th|br)[^>]*>/i', "\n", $html);
        $html = preg_replace('/<[^>]+>/', ' ', $html);

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return substr(trim($text), 0, self::MAX_CONTENT_CHARS);
    }

    // ── Claude Haiku API ───────────────────────────────────────────────────

    private function extractWithClaude(string $content, string $sourceUrl): array
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
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => 2048,
            'messages'   => [
                ['role' => 'user', 'content' => $this->buildPrompt($content, $sourceUrl)],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Errore Claude API: HTTP ' . $response->status());
        }

        $text = $response->json('content.0.text', '');
        return $this->parseResponse($text, $sourceUrl);
    }

    private function buildPrompt(string $content, string $sourceUrl): string
    {
        return "Sei un assistente specializzato nel settore dell'arredamento italiano.\n\n"
            . "Analizza questo testo estratto dalla pagina web di un fornitore di arredamento "
            . "e identifica i prodotti e le collezioni presenti.\n\n"
            . "URL sorgente: {$sourceUrl}\n\n"
            . "TESTO DELLA PAGINA:\n{$content}\n\n"
            . "Per ogni elemento trovato restituisci questo schema JSON:\n"
            . "{\n"
            . "  \"name\": \"nome prodotto/collezione (obbligatorio)\",\n"
            . "  \"type\": \"product\" oppure \"collection\",\n"
            . "  \"collection\": \"nome della serie/collezione oppure null\",\n"
            . "  \"description\": \"descrizione breve max 200 caratteri oppure null\",\n"
            . "  \"materials\": {\"componente\": \"materiale\"} oppure null,\n"
            . "  \"finishes\": [\"finitura1\", \"finitura2\"] oppure null,\n"
            . "  \"colors\": [\"colore1\", \"colore2\"] oppure null,\n"
            . "  \"h2s\": [\"sotto-prodotto1\", \"sotto-prodotto2\"],\n"
            . "  \"source_url\": \"{$sourceUrl}\"\n"
            . "}\n\n"
            . "Note: usa type=\"collection\" per raggruppamenti di più prodotti (es. linea Seta), "
            . "type=\"product\" per singoli prodotti. Il campo h2s lista i nomi dei prodotti "
            . "all'interno di una collection.\n\n"
            . "Rispondi SOLO con un array JSON valido. Se non trovi elementi rispondi [].";
    }

    private function parseResponse(string $text, string $sourceUrl): array
    {
        if (! preg_match('/(\[[\s\S]*\])/m', $text, $m)) {
            return [];
        }

        $decoded = json_decode($m[1], true);
        if (! is_array($decoded)) {
            Log::warning('WebCrawlerService: JSON non valido', ['raw' => substr($text, 0, 500)]);
            return [];
        }

        return array_values(array_filter(
            array_map(fn($p) => $this->normalizeItem($p, $sourceUrl), $decoded),
            fn($p) => ! empty($p['name'])
        ));
    }

    private function normalizeItem(array $p, string $fallbackUrl): array
    {
        $url = $this->str($p['source_url'] ?? $p['url'] ?? null) ?? $fallbackUrl;

        return [
            // Fields used by DiscoverWebsiteJob / ViewWebDiscovery blade
            'name'        => trim((string) ($p['name'] ?? '')),
            'type'        => in_array($p['type'] ?? '', ['collection', 'product']) ? $p['type'] : 'product',
            'url'         => $url,
            'source_url'  => $url,
            'h2s'         => is_array($p['h2s'] ?? null) ? $p['h2s'] : [],
            'imported'    => false,

            // AI-enriched fields used by importSelected()
            'collection'  => $this->str($p['collection'] ?? null),
            'description' => $this->str($p['description'] ?? null),
            'materials'   => is_array($p['materials'] ?? null) ? $p['materials'] : null,
            'finishes'    => is_array($p['finishes'] ?? null) ? array_values($p['finishes']) : null,
            'colors'      => is_array($p['colors'] ?? null) ? array_values($p['colors']) : null,
        ];
    }

    private function str(mixed $v): ?string
    {
        if ($v === null || $v === '' || strtolower((string) $v) === 'null') {
            return null;
        }
        return trim((string) $v);
    }
}
