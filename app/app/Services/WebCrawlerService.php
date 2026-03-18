<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebCrawlerService
{
    private const MAX_CONTENT_CHARS = 8000;

    private const FETCH_TIMEOUT = 20;

    private const API_TIMEOUT = 180;

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
            throw new \RuntimeException('Pagina vuota o non leggibile: '.$url);
        }

        $items = $this->extractWithClaude($text, $url);

        // Ensure required job fields are present
        return array_map(function ($item) {
            $item['imported'] = false;
            $item['url'] = $item['source_url'] ?? $item['url'] ?? '';
            $item['h2s'] = $item['h2s'] ?? [];
            $item['type'] = $item['type'] ?? 'product';

            return $item;
        }, $items);
    }

    // ── HTTP fetch ─────────────────────────────────────────────────────────

    private function fetchPage(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Studio3GHD/1.0; product-catalog-bot)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->withoutVerifying()
            ->timeout(self::FETCH_TIMEOUT)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} per $url");
        }

        $body = $response->body();
        $charset = 'UTF-8';

        // Detect charset from Content-Type header
        if (preg_match('/charset=([^\s;]+)/i', $response->header('Content-Type') ?? '', $m)) {
            $charset = strtoupper(trim($m[1]));
        } elseif (preg_match('/<meta[^>]+charset=["\']?([^"\'\s;>]+)/i', $body, $m)) {
            $charset = strtoupper(trim($m[1]));
        }

        if ($charset !== 'UTF-8' && $charset !== 'UTF8') {
            $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $body);
            if ($converted !== false) {
                $body = $converted;
            }
        }

        // Final safety: strip any remaining invalid UTF-8 bytes
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $body);

        return $clean !== false ? $clean : '';
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

    // ── AI API — Ollama (default) o Anthropic (se ANTHROPIC_API_KEY presente) ──

    private function extractWithClaude(string $content, string $sourceUrl): array
    {
        if (config('services.anthropic.api_key')) {
            return $this->extractWithAnthropic($content, $sourceUrl);
        }

        return $this->extractWithOllama($content, $sourceUrl);
    }

    private function extractWithOllama(string $content, string $sourceUrl): array
    {
        $url = config('services.ollama.url', 'http://studio_ollama:11434');
        $model = config('services.ollama.model', 'qwen2.5:3b');

        $response = Http::timeout(self::API_TIMEOUT)
            ->post("{$url}/api/generate", [
                'model' => $model,
                'prompt' => $this->buildPrompt($content, $sourceUrl),
                'stream' => false,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            Log::error('WebCrawlerService: Ollama error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Errore Ollama API: HTTP '.$response->status());
        }

        $text = $response->json('response', '');

        return $this->parseResponse($text, $sourceUrl);
    }

    private function extractWithAnthropic(string $content, string $sourceUrl): array
    {
        $apiKey = config('services.anthropic.api_key');
        $model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(self::API_TIMEOUT)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 2048,
                'messages' => [
                    ['role' => 'user', 'content' => $this->buildPrompt($content, $sourceUrl)],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('WebCrawlerService: Anthropic error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Errore Anthropic API: HTTP '.$response->status());
        }

        $text = $response->json('content.0.text', '');

        return $this->parseResponse($text, $sourceUrl);
    }

    private function buildPrompt(string $content, string $sourceUrl): string
    {
        return "Sei un assistente specializzato nel settore dell'arredamento e interior design italiano.\n\n"
            .'Analizza il testo estratto dalla pagina web di un fornitore di arredamento '
            ."e identifica tutti i prodotti e le collezioni presenti.\n\n"
            ."URL sorgente: {$sourceUrl}\n\n"
            ."TESTO DELLA PAGINA:\n{$content}\n\n"
            ."Per ogni elemento trovato restituisci questo schema JSON:\n"
            ."{\n"
            ."  \"name\": \"nome prodotto o collezione (obbligatorio)\",\n"
            ."  \"type\": \"product\" oppure \"collection\",\n"
            ."  \"brand\": \"marchio/brand se diverso dal fornitore oppure null\",\n"
            ."  \"collection\": \"nome della serie o linea di appartenenza oppure null\",\n"
            ."  \"description\": \"descrizione breve max 250 caratteri oppure null\",\n"
            ."  \"materials\": {\"struttura\": \"materiale\", \"piano\": \"materiale\"} oppure null,\n"
            ."  \"finishes\": [\"finitura1\", \"finitura2\"] oppure null,\n"
            ."  \"colors\": [\"colore1\", \"colore2\"] oppure null,\n"
            ."  \"h2s\": [\"variante1\", \"variante2\"],\n"
            ."  \"source_url\": \"{$sourceUrl}\"\n"
            ."}\n\n"
            ."Linee guida:\n"
            ."- usa type=\"collection\" per linee/serie di prodotti (es. collezione Seta, sistema componibile)\n"
            ."- usa type=\"product\" per singoli articoli (divano, tavolo, armadio...)\n"
            ."- h2s: elenca i nomi delle varianti o prodotti inclusi nella collection\n"
            ."- materials: chiavi descrittive del componente (struttura, piano, seduta, rivestimento, gambe)\n"
            ."- finishes: finiture superficiali (laccato opaco, impiallacciato noce, cromato...)\n"
            ."- colors: colori espliciti menzionati nel testo\n"
            ."- Includi tutti gli elementi trovati, anche con dati parziali\n\n"
            .'Rispondi SOLO con JSON valido in questo formato: {"items": [...]}. '
            .'Se non trovi elementi rispondi {"items": []}.';
    }

    private function parseResponse(string $text, string $sourceUrl): array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $items = $decoded['items'] ?? $decoded;
        } elseif (preg_match('/"items"\s*:\s*(\[[\s\S]*?\])/m', $text, $m)) {
            $items = json_decode($m[1], true) ?? [];
        } elseif (preg_match('/(\[[\s\S]*\])/m', $text, $m)) {
            $items = json_decode($m[1], true) ?? [];
        } else {
            Log::warning('WebCrawlerService: nessun JSON nella risposta', ['raw' => substr($text, 0, 500)]);

            return [];
        }

        return array_values(array_filter(
            array_map(fn ($p) => $this->normalizeItem($p, $sourceUrl), $items),
            fn ($p) => ! empty($p['name'])
        ));
    }

    private function normalizeItem(array $p, string $fallbackUrl): array
    {
        $url = $this->str($p['source_url'] ?? $p['url'] ?? null) ?? $fallbackUrl;

        return [
            // Fields used by DiscoverWebsiteJob / ViewWebDiscovery blade
            'name' => trim((string) ($p['name'] ?? '')),
            'type' => in_array($p['type'] ?? '', ['collection', 'product']) ? $p['type'] : 'product',
            'url' => $url,
            'source_url' => $url,
            'h2s' => is_array($p['h2s'] ?? null) ? $p['h2s'] : [],
            'imported' => false,

            // AI-enriched fields used by importSelected()
            'brand' => $this->str($p['brand'] ?? null),
            'collection' => $this->str($p['collection'] ?? null),
            'description' => $this->str($p['description'] ?? null),
            'materials' => is_array($p['materials'] ?? null) ? $p['materials'] : null,
            'finishes' => is_array($p['finishes'] ?? null) ? array_values($p['finishes']) : null,
            'colors' => is_array($p['colors'] ?? null) ? array_values($p['colors']) : null,
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
