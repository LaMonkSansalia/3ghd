<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebCrawlerService
{
    private const MAX_CONTENT_CHARS = 8000;

    private const FETCH_TIMEOUT = 20;

    private const PDF_FETCH_TIMEOUT = 30;

    private const API_TIMEOUT = 180;

    private const MAX_PAGES_TO_CRAWL = 15;

    private const MAX_PDFS_TO_EXTRACT = 25;

    private const MAX_PRODUCT_PAGES_TO_SCAN = 100;

    /** Domains that serve PDF viewers rather than direct files */
    private const VIEWER_DOMAINS = [
        'issuu.com', 'fliphtml5.com', 'yumpu.com', 'calameo.com',
        'joomag.com', 'publuu.com', 'paperturn.com', 'heyzine.com',
        'flipsnack.com', 'anyflip.com', 'simplebooklet.com', 'madmagz.com',
        'e-pages.dk', 'myebook.com', 'flipbuilder.com', 'turboflip.com',
    ];

    /** Path keywords that suggest product/category pages */
    private const PRODUCT_KEYWORDS = [
        'cucin', 'prodott', 'collezion', 'living', 'armadi', 'armadio',
        'camera', 'bagn', 'ufficio', 'contract', 'complement', 'divani',
        'catalog', 'scheda', 'product', 'kitchen', 'bedroom', 'office',
        'tavol', 'sedie', 'sgabell', 'letti', 'comod', 'madie',
    ];

    /** Path keywords to exclude from crawl targets */
    private const EXCLUDE_PATH_KEYWORDS = [
        '/blog', '/news', '/notizie', '/evento', '/fiera', '/contatt',
        '/about', '/chi-siamo', '/privacy', '/cookie', '/login', '/admin',
        '/riservat', '/dealer', '/rivendit', '/store-locator', '/dove-siamo',
    ];

    public function __construct(private readonly PdfImportService $pdfImporter) {}

    /**
     * Full crawl: sitemap discovery → multi-page HTML analysis → PDF extraction → merge.
     *
     * @return array[]
     */
    public function crawl(string $url): array
    {
        Log::info('[Crawler] START', ['url' => $url]);

        $html = $this->fetchPage($url);

        // Phase 1 — discover site structure
        $sectionUrls = $this->discoverUrls($url, $html);

        // Phase 2 — HTML extraction on discovered pages
        $pagesToAnalyze = array_unique(array_merge([$url], array_column($sectionUrls, 'url')));
        $pagesToAnalyze = array_slice($pagesToAnalyze, 0, self::MAX_PAGES_TO_CRAWL);

        Log::info('[Crawler] Phase 1 done — pages to crawl', ['count' => count($pagesToAnalyze), 'urls' => $pagesToAnalyze]);

        $htmlItems = [];
        $allPdfUrls = [];
        $productPageLinks = [];
        $pageIndex = 0;

        foreach ($pagesToAnalyze as $pageUrl) {
            $pageIndex++;
            try {
                $pageHtml = ($pageUrl === $url) ? $html : $this->fetchPage($pageUrl);
            } catch (\Throwable $e) {
                Log::warning('[Crawler] skip page on error', ['url' => $pageUrl, 'error' => $e->getMessage()]);

                continue;
            }

            $text = $this->extractText($pageHtml);
            $pageItems = [];
            if (! empty(trim($text))) {
                $pageItems = $this->extractWithClaude($text, $pageUrl);
                $htmlItems = array_merge($htmlItems, $pageItems);
            }

            $pdfLinks = $this->findPdfLinks($pageHtml, $pageUrl);
            $allPdfUrls = array_merge($allPdfUrls, $pdfLinks);

            $productPageLinks = array_merge($productPageLinks, $this->findProductPageLinks($pageHtml, $pageUrl));

            Log::info("[Crawler] Page {$pageIndex}/".count($pagesToAnalyze).' done', [
                'url' => $pageUrl,
                'items' => count($pageItems),
                'pdf_links' => count($pdfLinks),
            ]);
        }

        // Phase 2.5 — scan product-level pages for PDFs (no Haiku, link-follow only)
        $alreadyCrawled = array_fill_keys($pagesToAnalyze, true);
        $productPageLinks = array_values(array_filter(
            array_unique($productPageLinks),
            fn ($u) => ! isset($alreadyCrawled[$u])
        ));
        $productPageLinks = array_slice($productPageLinks, 0, self::MAX_PRODUCT_PAGES_TO_SCAN);

        if (! empty($productPageLinks)) {
            Log::info('[Crawler] Phase 2.5 — product pages PDF scan', ['count' => count($productPageLinks)]);
            foreach ($productPageLinks as $productPageUrl) {
                if (count(array_unique($allPdfUrls)) >= self::MAX_PDFS_TO_EXTRACT) {
                    Log::info('[Crawler] Phase 2.5 — early exit, enough PDFs collected');
                    break;
                }
                try {
                    $productHtml = $this->fetchPage($productPageUrl);
                    $pdfLinks = $this->findPdfLinks($productHtml, $productPageUrl);
                    $allPdfUrls = array_merge($allPdfUrls, $pdfLinks);
                    if (! empty($pdfLinks)) {
                        Log::info('[Crawler] Phase 2.5 page — PDF found', ['url' => $productPageUrl, 'pdf_links' => count($pdfLinks)]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[Crawler] Phase 2.5 skip page', ['url' => $productPageUrl, 'error' => $e->getMessage()]);
                }
            }
        }

        // Phase 3 — PDF extraction
        $allPdfUrls = array_slice(array_unique($allPdfUrls), 0, self::MAX_PDFS_TO_EXTRACT);

        Log::info('[Crawler] Phase 3 — PDF extraction', ['pdf_count' => count($allPdfUrls), 'urls' => $allPdfUrls]);

        $pdfItems = ! empty($allPdfUrls) ? $this->extractFromPdfs($allPdfUrls) : [];

        // Merge: PDF items have priority (richer data); HTML fills in the rest
        $pdfNames = array_map(fn ($i) => mb_strtolower(trim($i['name'])), $pdfItems);
        $htmlOnly = array_filter(
            $htmlItems,
            fn ($i) => ! empty($i['name']) && ! in_array(mb_strtolower(trim($i['name'])), $pdfNames)
        );

        $total = count($pdfItems) + count($htmlOnly);
        Log::info('[Crawler] DONE', ['total' => $total, 'pdf_items' => count($pdfItems), 'html_items' => count($htmlOnly)]);

        return array_values(array_merge($pdfItems, $htmlOnly));
    }

    // ── Phase 1: Site structure discovery ─────────────────────────────────

    /**
     * Try sitemap first; fall back to regex nav-link extraction, then Haiku.
     *
     * @return array<array{url: string}>
     */
    private function discoverUrls(string $baseUrl, string $html): array
    {
        // 1. Sitemap
        $sitemapUrls = $this->tryFetchSitemap($baseUrl);
        if (! empty($sitemapUrls)) {
            Log::info('[Crawler] Phase 1: sitemap OK', ['count' => count($sitemapUrls)]);

            return $sitemapUrls;
        }

        // 2. Regex nav-link extraction (fast, no AI)
        $navUrls = $this->extractNavLinks($html, $baseUrl);
        if (! empty($navUrls)) {
            Log::info('[Crawler] Phase 1: nav-regex OK', ['count' => count($navUrls)]);

            return $navUrls;
        }

        // 3. Haiku navigation analysis (last resort)
        Log::info('[Crawler] Phase 1: falling back to Haiku nav discovery');

        return $this->discoverUrlsWithHaiku($html, $baseUrl);
    }

    /**
     * Try common sitemap paths and parse the first one that responds.
     *
     * @return array<array{url: string}>
     */
    private function tryFetchSitemap(string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $origin = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');

        $paths = ['/sitemap.xml', '/sitemap_index.xml', '/sitemap-product.xml', '/products-sitemap.xml'];

        foreach ($paths as $path) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Studio3GHD/1.0; product-catalog-bot)',
                ])
                    ->withoutVerifying()
                    ->timeout(self::FETCH_TIMEOUT)
                    ->get($origin.$path);

                if (! $response->successful()) {
                    continue;
                }

                $body = $response->body();
                if (! str_contains($body, '<urlset') && ! str_contains($body, '<sitemapindex')) {
                    continue;
                }

                $urls = $this->parseSitemap($body, $origin);
                if (! empty($urls)) {
                    return $urls;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * Parse sitemap XML (both <urlset> and <sitemapindex>) and return product URLs.
     *
     * @return array<array{url: string}>
     */
    private function parseSitemap(string $xml, string $origin, int $depth = 0): array
    {
        if ($depth > 1) {
            return [];
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        // Sitemap index: recurse into child sitemaps
        if (isset($doc->sitemap)) {
            $urls = [];
            foreach ($doc->sitemap as $sitemap) {
                $loc = trim((string) ($sitemap->loc ?? ''));
                if (! $loc) {
                    continue;
                }
                try {
                    $response = Http::withoutVerifying()->timeout(self::FETCH_TIMEOUT)->get($loc);
                    if ($response->successful()) {
                        $child = $this->parseSitemap($response->body(), $origin, $depth + 1);
                        $urls = array_merge($urls, $child);
                        if (count($urls) >= self::MAX_PAGES_TO_CRAWL) {
                            break;
                        }
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return $urls;
        }

        // Regular sitemap: filter <url><loc> entries by product keywords
        $urls = [];
        foreach ($doc->url ?? [] as $entry) {
            $loc = trim((string) ($entry->loc ?? ''));
            if ($loc && $this->isProductUrl($loc)) {
                $urls[] = ['url' => $loc];
                if (count($urls) >= self::MAX_PAGES_TO_CRAWL) {
                    break;
                }
            }
        }

        return $urls;
    }

    /**
     * Regex-based extraction of navigation links from HTML.
     * No AI — fast and cheap.
     *
     * @return array<array{url: string}>
     */
    private function extractNavLinks(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $origin = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');

        // Focus on <nav> and <header> — ignore footers/body links to avoid noise
        $navHtml = '';
        if (preg_match_all('/<nav[^>]*>([\s\S]*?)<\/nav>/i', $html, $m)) {
            $navHtml .= implode(' ', $m[1]);
        }
        if (empty(trim($navHtml)) && preg_match('/<header[^>]*>([\s\S]*?)<\/header>/i', $html, $m)) {
            $navHtml = $m[1];
        }

        if (empty(trim($navHtml))) {
            return [];
        }

        preg_match_all('/href=["\']([^"\'#][^"\']*)["\']/', $navHtml, $m);
        $links = array_unique($m[1] ?? []);

        // Resolve relative URLs
        $resolved = array_map(function (string $link) use ($origin, $baseUrl): string {
            if (str_starts_with($link, 'http')) {
                return $link;
            }
            if (str_starts_with($link, '/')) {
                return $origin.$link;
            }

            return rtrim($baseUrl, '/').'/'.$link;
        }, $links);

        // Filter: same domain + not an excluded path (no PRODUCT_KEYWORDS check — nav is already curated)
        $filtered = array_filter($resolved, function (string $link) use ($parsed): bool {
            $lParsed = parse_url($link);
            if (($lParsed['host'] ?? '') !== ($parsed['host'] ?? '')) {
                return false;
            }

            return $this->isNotExcludedUrl($link);
        });

        return array_values(array_slice(
            array_map(fn ($l) => ['url' => $l], array_unique($filtered)),
            0,
            self::MAX_PAGES_TO_CRAWL
        ));
    }

    /**
     * Haiku-based navigation discovery — used only when sitemap and regex both fail.
     *
     * @return array<array{url: string}>
     */
    private function discoverUrlsWithHaiku(string $html, string $baseUrl): array
    {
        if (! config('services.anthropic.api_key')) {
            return [];
        }

        // Send only the nav/header portion to save tokens
        $navHtml = '';
        if (preg_match_all('/<nav[^>]*>([\s\S]*?)<\/nav>/i', $html, $m)) {
            $navHtml = implode(' ', $m[1]);
        }
        if (empty(trim($navHtml)) && preg_match('/<header[^>]*>([\s\S]*?)<\/header>/i', $html, $m)) {
            $navHtml = $m[1];
        }

        $navText = html_entity_decode(strip_tags($navHtml ?: $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $navText = preg_replace('/\s+/', ' ', $navText);
        $navText = substr(trim($navText), 0, 3000);

        if (empty(trim($navText))) {
            return [];
        }

        $prompt = 'Sei un web crawler per siti italiani di arredamento. Analizza questo testo di navigazione '
            ."e restituisci le URL delle sezioni prodotto principali (cucine, living, armadi, bagni, ecc.).\n"
            ."Ignora: blog, contatti, chi siamo, privacy, login, fiere, news.\n\n"
            ."BASE URL: {$baseUrl}\n\n"
            ."TESTO NAVIGAZIONE:\n{$navText}\n\n"
            .'Rispondi SOLO con JSON valido: {"urls": ["https://...", ...]}. '
            .'Se non trovi URL di sezioni prodotto: {"urls": []}.';

        try {
            $apiKey = config('services.anthropic.api_key');
            $model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(self::API_TIMEOUT)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 512,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $text = $response->json('content.0.text', '');
            $text = preg_replace('/```(?:json)?\s*([\s\S]*?)```/s', '$1', $text);
            $decoded = json_decode(trim($text), true);
            $urls = $decoded['urls'] ?? [];

            if (! is_array($urls)) {
                return [];
            }

            $parsed = parse_url($baseUrl);
            $valid = array_filter($urls, function (string $u) use ($parsed): bool {
                $p = parse_url($u);

                return ($p['host'] ?? '') === ($parsed['host'] ?? '');
            });

            return array_values(array_slice(
                array_map(fn ($u) => ['url' => $u], array_unique($valid)),
                0,
                self::MAX_PAGES_TO_CRAWL
            ));
        } catch (\Throwable $e) {
            Log::warning('WebCrawlerService: Haiku navigation discovery failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Check if a URL path is likely to contain product/catalog content.
     */
    private function isProductUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        foreach (self::EXCLUDE_PATH_KEYWORDS as $kw) {
            if (str_contains($path, $kw)) {
                return false;
            }
        }

        foreach (self::PRODUCT_KEYWORDS as $kw) {
            if (str_contains($path, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when a URL is not in the exclusion list — used for nav links which are already curated.
     */
    private function isNotExcludedUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        // Skip file extensions (images, scripts, feeds)
        if (preg_match('/\.(jpg|jpeg|png|gif|svg|ico|css|js|xml|rss|txt|zip)(\?.*)?$/i', $path)) {
            return false;
        }

        foreach (self::EXCLUDE_PATH_KEYWORDS as $kw) {
            if (str_contains($path, $kw)) {
                return false;
            }
        }

        return true;
    }

    // ── Phase 2 helpers: PDF link discovery ───────────────────────────────

    /**
     * Find direct PDF links in an HTML page.
     *
     * @return string[]
     */
    private function findPdfLinks(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $origin = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');

        $patterns = [
            // Standard HTML links and attributes
            '/href=["\']([^"\']*\.pdf(?:\?[^"\']*)?)["\']/',
            '/data-(?:src|href|pdf|url|file)=["\']([^"\']*\.pdf(?:\?[^"\']*)?)["\']/',
            // Embedded viewers and objects
            '/<iframe[^>]+src=["\']([^"\']*\.pdf(?:\?[^"\']*)?)["\']/',
            '/<object[^>]+data=["\']([^"\']*\.pdf(?:\?[^"\']*)?)["\']/',
            '/<embed[^>]+src=["\']([^"\']*\.pdf(?:\?[^"\']*)?)["\']/',
            // Preload / prefetch hints
            '/<link[^>]+href=["\']([^"\']*\.pdf(?:\?[^"\']*)?)["\']/',
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $m)) {
                $found = array_merge($found, $m[1]);
            }
        }

        $resolved = array_map(function (string $link) use ($origin, $baseUrl): string {
            if (str_starts_with($link, 'http')) {
                return $link;
            }
            if (str_starts_with($link, '/')) {
                return $origin.$link;
            }

            return rtrim($baseUrl, '/').'/'.$link;
        }, $found);

        // Also extract PDFs embedded via 3D Flip Book plugin (FB3D_CLIENT_DATA.push('base64...'))
        if (preg_match_all("/FB3D_CLIENT_DATA\.push\('([A-Za-z0-9+\/=]+)'\)/", $html, $fb3d)) {
            foreach ($fb3d[1] as $b64) {
                $decoded = base64_decode($b64, strict: true);
                if ($decoded === false) {
                    continue;
                }
                $data = json_decode($decoded, true);
                if (! is_array($data)) {
                    continue;
                }
                foreach ($data['posts'] ?? [] as $post) {
                    if (($post['type'] ?? '') === 'pdf') {
                        $guid = $post['data']['guid'] ?? null;
                        if ($guid && str_ends_with(strtolower($guid), '.pdf')) {
                            $resolved[] = $guid;
                        }
                    }
                }
            }
        }

        // General JS string extraction: catches inline script PDF URLs
        // (custom catalog players, WPDM config, JSON-LD, window variables, etc.)
        if (preg_match_all('/"(https?:\/\/[^"\s<>]*\.pdf(?:\?[^"\s<>]*)?)"/i', $html, $jsM)) {
            foreach ($jsM[1] as $jsUrl) {
                $resolved[] = $jsUrl;
            }
        }

        return array_unique($resolved);
    }

    /**
     * Find internal links to product-level pages for Phase 2.5 PDF discovery.
     *
     * @return string[]
     */
    private function findProductPageLinks(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $origin = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
        $host = $parsed['host'] ?? '';

        if (! preg_match_all('/href=["\']([^"\'#][^"\']*)["\']/', $html, $m)) {
            return [];
        }

        $rawLinks = array_filter($m[1], static function (string $link): bool {
            return ! str_starts_with($link, 'mailto:')
                && ! str_starts_with($link, 'tel:')
                && ! str_starts_with($link, 'javascript:');
        });

        $resolved = array_map(function (string $link) use ($origin, $baseUrl): string {
            if (str_starts_with($link, 'http')) {
                return $link;
            }
            if (str_starts_with($link, '//')) {
                return 'https:'.$link;
            }
            if (str_starts_with($link, '/')) {
                return $origin.$link;
            }

            return rtrim($baseUrl, '/').'/'.$link;
        }, $rawLinks);

        $filtered = array_filter($resolved, function (string $link) use ($host): bool {
            if ((parse_url($link, PHP_URL_HOST) ?? '') !== $host) {
                return false;
            }

            return $this->isProductUrl($link);
        });

        return array_unique(array_values($filtered));
    }

    /**
     * Determine if a PDF URL is directly downloadable or behind a viewer.
     */
    private function detectPdfAccessibility(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        foreach (self::VIEWER_DOMAINS as $domain) {
            if (str_contains($host, $domain)) {
                return 'viewer';
            }
        }

        // Try to extract direct PDF from viewer query params
        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        foreach (['file', 'url', 'doc', 'pdf'] as $param) {
            if (isset($query[$param]) && str_contains(strtolower($query[$param]), '.pdf')) {
                return 'viewer_extractable';
            }
        }

        return 'direct';
    }

    // ── Phase 3: PDF extraction ────────────────────────────────────────────

    /**
     * Download and extract products from each PDF URL.
     *
     * @param  string[]  $pdfUrls
     * @return array[]
     */
    private function extractFromPdfs(array $pdfUrls): array
    {
        $items = [];

        foreach ($pdfUrls as $url) {
            $accessibility = $this->detectPdfAccessibility($url);

            if ($accessibility === 'viewer') {
                Log::info('[Crawler] PDF viewer skipped', ['url' => $url]);

                continue;
            }

            $tmpPath = null;
            try {
                Log::info('[Crawler] PDF downloading', ['url' => $url]);
                $tmpPath = $this->downloadPdf($url);
                if ($tmpPath === null) {
                    Log::warning('[Crawler] PDF download returned null (non-PDF or error)', ['url' => $url]);

                    continue;
                }

                $products = $this->pdfImporter->extract($tmpPath);
                $count = 0;
                foreach ($products as $product) {
                    if (! empty($product['name'])) {
                        $items[] = $this->normalizePdfItem($product, $url);
                        $count++;
                    }
                }
                Log::info('[Crawler] PDF extracted', ['url' => $url, 'items' => $count]);
            } catch (\Throwable $e) {
                Log::warning('[Crawler] PDF extraction failed', ['url' => $url, 'error' => $e->getMessage()]);
            } finally {
                if ($tmpPath && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        return $items;
    }

    /**
     * Download a PDF file to a temp path.
     * Returns null if the response is not a valid PDF.
     */
    private function downloadPdf(string $url): ?string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; Studio3GHD/1.0; product-catalog-bot)',
        ])
            ->withoutVerifying()
            ->timeout(self::PDF_FETCH_TIMEOUT)
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower($response->header('Content-Type') ?? '');
        if (! str_contains($contentType, 'application/pdf') && ! str_ends_with(strtolower($url), '.pdf')) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'studio_pdf_').'.pdf';
        file_put_contents($tmpPath, $response->body());

        return $tmpPath;
    }

    /**
     * Normalize PdfImportService output to the WebDiscovery item format.
     */
    private function normalizePdfItem(array $p, string $pdfUrl): array
    {
        return [
            'name' => trim((string) ($p['name'] ?? '')),
            'type' => 'product',
            'source' => 'pdf',
            'sku' => $this->str($p['sku'] ?? null),
            'brand' => $this->str($p['brand'] ?? null),
            'collection' => $this->str($p['collection'] ?? null),
            'description' => $this->str($p['description'] ?? null),
            'materials' => is_array($p['materials'] ?? null) ? $p['materials'] : null,
            'finishes' => is_array($p['finishes'] ?? null) ? array_values($p['finishes']) : null,
            'colors' => is_array($p['colors'] ?? null) ? array_values($p['colors']) : null,
            'dimensions' => is_array($p['dimensions'] ?? null) ? $p['dimensions'] : null,
            'price_list' => $this->numericOrNull($p['price_list'] ?? null),
            'url' => $pdfUrl,
            'source_url' => $pdfUrl,
            'h2s' => [],
            'imported' => false,
        ];
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
        $text = preg_replace('/( *\n){3,}/', "\n\n", $text);

        return substr(trim($text), 0, self::MAX_CONTENT_CHARS);
    }

    // ── AI API — Anthropic Haiku ──────────────────────────────────────────

    private function extractWithClaude(string $content, string $sourceUrl): array
    {
        if (config('services.anthropic.api_key')) {
            return $this->extractWithAnthropic($content, $sourceUrl);
        }

        Log::warning('WebCrawlerService: ANTHROPIC_API_KEY non configurata');

        return [];
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
                'max_tokens' => 4096,
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
        return "Sei un assistente specializzato nell'estrazione di cataloghi di prodotti da siti web "
            ."di fornitori italiani di arredamento (cucine, soggiorni, camere, bagni, ufficio, contract).\n\n"
            ."## COMPITO\n\n"
            .'Analizza il testo di una pagina web di un fornitore e restituisci TUTTI i prodotti '
            .'e le collezioni trovati. Anche un solo nome senza descrizione è utile — serve per '
            ."costruire una bozza del catalogo. Non scartare mai un elemento per dati incompleti.\n\n"
            ."---\n\n"
            ."## ESEMPI REALI DA SITI DEI NOSTRI FORNITORI\n\n"
            ."### Esempio 1 — Lista collezioni con link (Evo Cucine)\n"
            ."TESTO:\n"
            ."  Bali SCOPRI DI PIÙ\n"
            ."  Doha SCOPRI DI PIÙ\n"
            ."  Kaori SCOPRI DI PIÙ\n"
            ."  Rio SCOPRI DI PIÙ\n"
            ."  Micra SCOPRI DI PIÙ\n"
            ."  Memory SCOPRI DI PIÙ\n"
            ."OUTPUT CORRETTO:\n"
            ."  [{\"name\":\"Bali\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Doha\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Kaori\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Rio\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Micra\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Memory\",\"type\":\"collection\",...}]\n"
            ."REGOLA: ogni nome prima di 'SCOPRI DI PIÙ' / 'Scopri' / 'Vedi' / 'Discover' = 1 collezione\n\n"
            ."### Esempio 2 — Menu navigazione con collezioni (Aran Cucine)\n"
            ."TESTO:\n"
            ."  Prodotti\n"
            ."  Cucine\n"
            ."  Modulo 13\n"
            ."  Modern\n"
            ."  Contemporary\n"
            ."  DESIGNERS COLLECTION: Luce Oasi Sipario Cucinando\n"
            ."OUTPUT CORRETTO:\n"
            ."  [{\"name\":\"Modulo 13\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Modern\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Contemporary\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Luce\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Oasi\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Sipario\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Cucinando\",\"type\":\"collection\",...}]\n"
            ."REGOLA: 'Cucine' è una macro-categoria merceologica → NON estrarre.\n"
            ."        'Modulo 13', 'Modern', 'Luce' ecc. sono nomi di collezioni → ESTRARRE.\n\n"
            ."### Esempio 3 — Cataloghi con nome proprio nel menu (Giessegi)\n"
            ."TESTO:\n"
            ."  Collezioni | Camerette | Soggiorni | Cabine Armadio | Camere Matrimoniali\n"
            ."  Cataloghi: Uno per tutti Linea Fly Day By Day Le dee della bellezza Night Collection\n"
            ."OUTPUT CORRETTO:\n"
            ."  [{\"name\":\"Uno per tutti\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Linea Fly\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Day By Day\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Le dee della bellezza\",\"type\":\"collection\",...},\n"
            ."   {\"name\":\"Night Collection\",\"type\":\"collection\",...}]\n"
            ."REGOLA: 'Camerette', 'Soggiorni', 'Cabine Armadio' sono macro-categorie → NON estrarre.\n\n"
            ."### Esempio 4 — Tipologie prodotto senza nomi collezione (Maronese)\n"
            ."TESTO (nota: nome e link su righe separate):\n"
            ."  ZONA GIORNO\n"
            ."  Madie\n"
            ."   Scopri di più\n"
            ."  Sistema O Pen\n"
            ."   Scopri di più\n"
            ."  Tavolini\n"
            ."   Scopri di più\n"
            ."  ZONA NOTTE\n"
            ."  Letti\n"
            ."   Scopri di più\n"
            ."  Comò e Comodini\n"
            ."   Scopri di più\n"
            ."OUTPUT CORRETTO:\n"
            ."  [{\"name\":\"Madie\",\"type\":\"product\",...},\n"
            ."   {\"name\":\"Sistema O Pen\",\"type\":\"product\",...},\n"
            ."   {\"name\":\"Tavolini\",\"type\":\"product\",...},\n"
            ."   {\"name\":\"Letti\",\"type\":\"product\",...},\n"
            ."   {\"name\":\"Comò e Comodini\",\"type\":\"product\",...}]\n"
            ."REGOLA: quando nome e 'Scopri di più' sono su righe consecutive → estrarre il nome.\n"
            ."        Quando non ci sono nomi di collezioni propri, estrarre le tipologie come 'product'.\n\n"
            ."### Esempio 5 — Pagina prodotto con dettagli tecnici\n"
            ."TESTO:\n"
            ."  Cucina Bali — Design moderno con ante in laccato opaco\n"
            ."  Struttura: pannello melaminico bianco\n"
            ."  Finiture disponibili: laccato opaco, laccato lucido, impiallacciato rovere\n"
            ."  Colori: bianco, grigio nebbia, antracite\n"
            ."  Varianti: Bali Open, Bali Island, Bali Linear\n"
            ."OUTPUT CORRETTO:\n"
            ."  [{\"name\":\"Bali\",\"type\":\"collection\",\"description\":\"Design moderno con ante in laccato opaco\",\n"
            ."    \"materials\":{\"struttura\":\"pannello melaminico bianco\"},\n"
            ."    \"finishes\":[\"laccato opaco\",\"laccato lucido\",\"impiallacciato rovere\"],\n"
            ."    \"colors\":[\"bianco\",\"grigio nebbia\",\"antracite\"],\n"
            ."    \"h2s\":[\"Bali Open\",\"Bali Island\",\"Bali Linear\"]}]\n\n"
            ."---\n\n"
            ."## COSA NON ESTRARRE\n\n"
            .'- Macro-categorie merceologiche quando appaiono SOLO nel menu senza link dedicato: '
            ."Cucine, Soggiorni, Camere, Camerette, Cabine Armadio, Bagni, Complementi, Divani\n"
            ."  ECCEZIONE: se la stessa parola appare seguita da 'Scopri di più' → estrarre come product\n"
            ."- Nomi di designer/persone (Marco Piva, Stefano Boeri, ecc.)\n"
            .'- Testi di navigazione generici: Novità, Promozioni, Contattaci, Virtual Tour, '
            ."Download, Area Riservata, Rete vendita, Blog\n"
            ."- Testi marketing: 'qualità certificata', 'made in Italy', 'design italiano'\n"
            ."- Nomi di fiere/eventi: 'Milan Design Week', 'Salone del Mobile'\n\n"
            ."---\n\n"
            ."## TESTO DA ANALIZZARE\n\n"
            ."URL: {$sourceUrl}\n\n"
            ."{$content}\n\n"
            ."---\n\n"
            ."## SCHEMA OUTPUT\n\n"
            ."{\n"
            ."  \"name\": \"nome (obbligatorio)\",\n"
            ."  \"type\": \"collection\" | \"product\",\n"
            ."  \"brand\": null,\n"
            ."  \"collection\": \"serie di appartenenza se diverso dal name, altrimenti null\",\n"
            ."  \"description\": \"max 200 caratteri se disponibile nel testo, altrimenti null\",\n"
            ."  \"materials\": {\"componente\": \"materiale\"} oppure null,\n"
            ."  \"finishes\": [\"finitura1\", \"finitura2\"] oppure null,\n"
            ."  \"colors\": [\"colore1\"] oppure null,\n"
            ."  \"h2s\": [\"variante o sottoprodotto\"],\n"
            ."  \"source_url\": \"{$sourceUrl}\"\n"
            ."}\n\n"
            ."Popola materials/finishes/colors/h2s SOLO se esplicitamente presenti nel testo.\n"
            ."type: dubbio tra collection/product → usa \"collection\".\n\n"
            .'Rispondi con JSON valido e nient\'altro: {"items": [...]}. '
            .'Se non trovi nessun prodotto o collezione: {"items": []}.';
    }

    private function parseResponse(string $text, string $sourceUrl): array
    {
        $text = preg_replace('/```(?:json)?\s*([\s\S]*?)```/s', '$1', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $items = $decoded['items'] ?? $decoded;
        } elseif (preg_match('/"items"\s*:\s*(\[[\s\S]*\])\s*\}/s', $text, $m)) {
            $items = json_decode($m[1], true) ?? [];
        } elseif (preg_match('/(\[[\s\S]*\])/s', $text, $m)) {
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
            'name' => trim((string) ($p['name'] ?? '')),
            'type' => in_array($p['type'] ?? '', ['collection', 'product']) ? $p['type'] : 'product',
            'source' => 'html',
            'url' => $url,
            'source_url' => $url,
            'h2s' => is_array($p['h2s'] ?? null) ? $p['h2s'] : [],
            'imported' => false,
            'sku' => null,
            'brand' => $this->str($p['brand'] ?? null),
            'collection' => $this->str($p['collection'] ?? null),
            'description' => $this->str($p['description'] ?? null),
            'materials' => is_array($p['materials'] ?? null) ? $p['materials'] : null,
            'finishes' => is_array($p['finishes'] ?? null) ? array_values($p['finishes']) : null,
            'colors' => is_array($p['colors'] ?? null) ? array_values($p['colors']) : null,
            'dimensions' => null,
            'price_list' => null,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function str(mixed $v): ?string
    {
        if ($v === null || $v === '' || strtolower((string) $v) === 'null') {
            return null;
        }

        return trim((string) $v);
    }

    private function numericOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = filter_var($v, FILTER_VALIDATE_FLOAT);

        return $n !== false ? (float) $n : null;
    }
}
