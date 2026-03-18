<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\WebDiscovery;
use App\Services\WebCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DiscoverWebsiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        private int    $supplierId,
        private string $entryUrl,
    ) {}

    public function handle(WebCrawlerService $crawler): void
    {
        // Create (or reuse pending) WebDiscovery record
        $discovery = WebDiscovery::create([
            'supplier_id' => $this->supplierId,
            'entry_url'   => $this->entryUrl,
            'status'      => 'crawling',
            'started_at'  => now(),
        ]);

        try {
            $items = $crawler->crawl($this->entryUrl);

            $discovery->update([
                'status'        => 'done',
                'pages_crawled' => count(array_unique(array_column($items, 'url'))),
                'items_found'   => count($items),
                'items'         => $items,
                'completed_at'  => now(),
            ]);

        } catch (\Throwable $e) {
            $discovery->update([
                'status'       => 'failed',
                'notes'        => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
