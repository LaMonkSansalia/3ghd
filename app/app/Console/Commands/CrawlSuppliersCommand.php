<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverWebsiteJob;
use App\Models\Supplier;
use Illuminate\Console\Command;

class CrawlSuppliersCommand extends Command
{
    protected $signature = 'suppliers:crawl
                            {--supplier= : ID fornitore specifico (opzionale)}
                            {--dry-run   : Mostra i fornitori che verrebbero crawlati senza dispatch}';

    protected $description = 'Dispatcha DiscoverWebsiteJob per tutti i fornitori con catalog_format=web';

    public function handle(): int
    {
        $query = Supplier::query()
            ->where('is_active', true)
            ->where('catalog_format', 'web')
            ->whereNotNull('website')
            ->where('website', '!=', '');

        if ($supplierId = $this->option('supplier')) {
            $query->where('id', (int) $supplierId);
        }

        $suppliers = $query->orderBy('name')->get();

        if ($suppliers->isEmpty()) {
            $this->warn('Nessun fornitore web attivo trovato.');
            return self::SUCCESS;
        }

        $this->info("Fornitori da crawlare: {$suppliers->count()}");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Nome', 'Sito'],
                $suppliers->map(fn($s) => [$s->id, $s->name, $s->website])->toArray()
            );
            $this->warn('[dry-run] Nessun job dispatchiato.');
            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($suppliers as $supplier) {
            $this->line("  → {$supplier->name} ({$supplier->website})");

            DiscoverWebsiteJob::dispatch($supplier->id, $supplier->website);
            $dispatched++;
        }

        $this->newLine();
        $this->info("✓ $dispatched job dispatchiati. Risultati in Analisi Siti.");

        return self::SUCCESS;
    }
}
