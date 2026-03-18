<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\Product;
use App\Services\SpreadsheetParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /**
     * @param int    $importLogId   ID of the ImportLog record
     * @param string $storagePath   Path relative to the local disk (e.g. "imports/file.xlsx")
     * @param array  $columnMapping Map of product field => spreadsheet column header
     *                              e.g. ['name' => 'Prodotto', 'sku' => 'Codice', ...]
     */
    public function __construct(
        private int $importLogId,
        private string $storagePath,
        private array $columnMapping,
    ) {}

    public function handle(SpreadsheetParser $parser): void
    {
        $log = ImportLog::findOrFail($this->importLogId);
        $log->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $absolutePath = Storage::disk('local')->path($this->storagePath);
            $rows = $parser->allRows($absolutePath);

            $log->update(['total_rows' => count($rows)]);

            $imported = $updated = $skipped = $errors = 0;
            $errorDetails = [];

            foreach ($rows as $index => $row) {
                try {
                    $name = trim($this->getValue($row, 'name'));
                    if (empty($name)) {
                        $skipped++;
                        continue;
                    }

                    $sku = trim($this->getValue($row, 'sku'));

                    // Deduplication: try SKU first, then name+supplier
                    $existing = null;
                    if ($sku) {
                        $existing = Product::where('supplier_id', $log->supplier_id)
                            ->where('sku', $sku)
                            ->first();
                    }
                    if (! $existing) {
                        $existing = Product::where('supplier_id', $log->supplier_id)
                            ->where('name', $name)
                            ->first();
                    }

                    $attrs = array_filter([
                        'supplier_id' => $log->supplier_id,
                        'name'        => $name,
                        'sku'         => $sku ?: null,
                        'brand'       => trim($this->getValue($row, 'brand')) ?: null,
                        'collection'  => trim($this->getValue($row, 'collection')) ?: null,
                        'description' => trim($this->getValue($row, 'description')) ?: null,
                        'materials'   => trim($this->getValue($row, 'materials')) ?: null,
                        'price_list'  => $this->decimal($this->getValue($row, 'price_list')),
                        'cost'        => $this->decimal($this->getValue($row, 'cost')),
                        'notes'       => trim($this->getValue($row, 'notes')) ?: null,
                        'source_file' => $log->file_name,
                    ], fn($v) => $v !== null);

                    if ($existing) {
                        $existing->update($attrs);
                        $updated++;
                    } else {
                        Product::create($attrs);
                        $imported++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $errorDetails[] = ['row' => $index + 2, 'error' => $e->getMessage()];
                }
            }

            $log->update([
                'status'         => 'done',
                'imported_count' => $imported,
                'updated_count'  => $updated,
                'skipped_count'  => $skipped,
                'errors_count'   => $errors,
                'error_details'  => $errorDetails ?: null,
                'completed_at'   => now(),
            ]);

            $log->supplier->update(['last_imported_at' => now()]);

        } catch (\Throwable $e) {
            $log->update([
                'status'       => 'failed',
                'error_details' => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
        }
    }

    private function getValue(array $row, string $field): string
    {
        $col = $this->columnMapping[$field] ?? null;
        return ($col && isset($row[$col])) ? (string) $row[$col] : '';
    }

    private function decimal(string $val): ?float
    {
        if (trim($val) === '') {
            return null;
        }
        // Strip currency symbols, spaces; normalize decimal separator
        $cleaned = preg_replace('/[^\d,\.]/', '', $val);
        $cleaned = str_replace(',', '.', $cleaned);
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}
