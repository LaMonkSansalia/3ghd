<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ImportLogs\ImportLogResource;
use App\Models\ImportLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\PdfImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class PdfImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.pdf-import';

    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-document-arrow-up';
    protected static ?string $navigationLabel = 'Importa PDF';
    protected static \UnitEnum|string|null $navigationGroup = 'Catalogo';
    protected static ?int    $navigationSort  = 11;
    protected static ?string $title           = 'Importa Catalogo PDF';

    // ── State ────────────────────────────────────────────────────────────────

    public array  $extractedProducts = [];
    public bool   $extractionDone    = false;
    public string $extractionError   = '';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    protected function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([

                    // ── Step 1: Upload ───────────────────────────────────────
                    Wizard\Step::make('upload')
                        ->label('Upload PDF')
                        ->icon('heroicon-o-document-arrow-up')
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Fornitore')
                                ->options(
                                    Supplier::where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required()
                                ->placeholder('Seleziona fornitore...'),

                            FileUpload::make('file')
                                ->label('File PDF')
                                ->helperText('PDF con testo selezionabile — max 30 MB. Claude Haiku analizzerà automaticamente il contenuto.')
                                ->disk('local')
                                ->directory('imports')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(30720)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, $livewire) {
                                    $livewire->extractedProducts = [];
                                    $livewire->extractionDone    = false;
                                    $livewire->extractionError   = '';

                                    // Filament v5 / Livewire 3: state is TemporaryUploadedFile or array thereof.
                                    // Do NOT pass to Storage::disk()->path() — SplFileInfo __toString() gives wrong path.
                                    // Use getRealPath() which returns the actual storage path.
                                    $file = is_array($state) ? ($state[0] ?? null) : $state;
                                    if (! $file) {
                                        return;
                                    }
                                    $path = ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                        ? $file->getRealPath()
                                        : Storage::disk('local')->path((string) $file);

                                    \Illuminate\Support\Facades\Log::error('[PDF] path resolved', [
                                        'path'   => $path,
                                        'exists' => file_exists($path ?? ''),
                                    ]);

                                    if (! $path || ! file_exists($path)) {
                                        return;
                                    }

                                    try {
                                        \Illuminate\Support\Facades\Log::error('[PDF] calling extract');
                                        $service = app(PdfImportService::class);

                                        $livewire->extractedProducts = $service->extract($path);
                                        $livewire->extractionDone    = true;
                                        \Illuminate\Support\Facades\Log::error('[PDF] extract done', ['count' => count($livewire->extractedProducts)]);
                                    } catch (\Throwable $e) {
                                        $livewire->extractionError = $e->getMessage();

                                        Notification::make()
                                            ->title('Errore estrazione PDF')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),

                            Placeholder::make('extraction_status')
                                ->label('')
                                ->content(fn ($livewire) => $livewire->renderExtractionStatus()),
                        ]),

                    // ── Step 2: Revisione prodotti estratti ──────────────────
                    Wizard\Step::make('review')
                        ->label('Revisione')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            Placeholder::make('products_table')
                                ->label('Prodotti estratti da Claude Haiku')
                                ->content(fn ($livewire) => new HtmlString(
                                    $livewire->renderProductsTable()
                                ))
                                ->columnSpanFull(),
                        ]),

                    // ── Step 3: Conferma e import ────────────────────────────
                    Wizard\Step::make('conferma')
                        ->label('Conferma')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Placeholder::make('import_summary')
                                ->label('Riepilogo import')
                                ->content(fn ($livewire) => new HtmlString(
                                    $livewire->renderImportSummary()
                                ))
                                ->columnSpanFull(),
                        ]),

                ])
                ->submitAction(new HtmlString(
                    '<button type="button" wire:click="import" '
                    . 'class="fi-btn fi-btn-size-md fi-btn-color-success fi-color-success '
                    . 'inline-flex items-center gap-1.5 font-semibold rounded-lg px-4 py-2 '
                    . 'bg-teal-600 text-white hover:bg-teal-500 focus:ring-2 focus:ring-teal-500">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">'
                    . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>'
                    . '</svg>'
                    . ' Importa come bozze'
                    . '</button>'
                )),
            ]);
    }

    // ── Render helpers ────────────────────────────────────────────────────────

    public function renderExtractionStatus(): HtmlString
    {
        if ($this->extractionError) {
            return new HtmlString(
                "<div class='flex items-center gap-2 text-sm text-red-600 dark:text-red-400 mt-2'>"
                . "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 flex-shrink-0' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>"
                . '<span>' . e($this->extractionError) . '</span>'
                . "</div>"
            );
        }

        if ($this->extractionDone) {
            $count = count($this->extractedProducts);
            return new HtmlString(
                "<div class='flex items-center gap-2 text-sm text-teal-700 dark:text-teal-400 mt-2'>"
                . "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 flex-shrink-0' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/></svg>"
                . "<span><strong>$count prodotti</strong> estratti. Procedi al passo successivo per revisionarli.</span>"
                . "</div>"
            );
        }

        return new HtmlString('');
    }

    public function renderProductsTable(): string
    {
        if (empty($this->extractedProducts)) {
            return '<p class="text-sm text-gray-400">Nessun prodotto estratto. Torna al passo precedente e carica un PDF.</p>';
        }

        $rows = '';
        foreach ($this->extractedProducts as $p) {
            $name  = e($p['name'] ?? '—');
            $sku   = e($p['sku'] ?? '—');
            $coll  = e($p['collection'] ?? '—');
            $price = isset($p['price_list'])
                ? '€ ' . number_format((float) $p['price_list'], 2, ',', '.')
                : '—';
            $mats = is_array($p['materials'] ?? null)
                ? e(implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($p['materials']), $p['materials'])))
                : '—';
            $fins = is_array($p['finishes'] ?? null)
                ? e(implode(', ', $p['finishes']))
                : '—';

            $rows .= "<tr class='border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'>"
                . "<td class='px-3 py-2 text-sm font-medium text-gray-900 dark:text-white'>$name</td>"
                . "<td class='px-3 py-2 text-sm text-gray-500 font-mono'>$sku</td>"
                . "<td class='px-3 py-2 text-sm text-gray-500'>$coll</td>"
                . "<td class='px-3 py-2 text-sm text-gray-700 dark:text-gray-300 font-medium'>$price</td>"
                . "<td class='px-3 py-2 text-sm text-gray-400 max-w-xs truncate'>$mats</td>"
                . "<td class='px-3 py-2 text-sm text-gray-400 max-w-xs truncate'>$fins</td>"
                . "</tr>";
        }

        $count = count($this->extractedProducts);

        return "<div class='overflow-x-auto rounded border border-gray-200 dark:border-gray-700'>"
            . "<table class='min-w-full divide-y divide-gray-200 dark:divide-gray-700'>"
            . "<thead class='bg-gray-50 dark:bg-gray-800'><tr>"
            . "<th class='px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>Nome</th>"
            . "<th class='px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>SKU</th>"
            . "<th class='px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>Collezione</th>"
            . "<th class='px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>Listino</th>"
            . "<th class='px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>Materiali</th>"
            . "<th class='px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider'>Finiture</th>"
            . "</tr></thead>"
            . "<tbody class='bg-white dark:bg-gray-900'>$rows</tbody>"
            . "</table></div>"
            . "<p class='text-xs text-gray-400 mt-2'>$count prodotti saranno importati come bozze (non attivi — da completare).</p>";
    }

    public function renderImportSummary(): string
    {
        $data         = $this->data ?? [];
        $supplierId   = $data['supplier_id'] ?? null;
        $supplierName = $supplierId
            ? (Supplier::find($supplierId)?->name ?? "ID $supplierId")
            : '—';
        $count   = count($this->extractedProducts);
        $rawFile = $data['file'] ?? null;
        $file    = e(basename(is_array($rawFile) ? ($rawFile[0] ?? '—') : ($rawFile ?? '—')));

        return "<div class='space-y-4'>"
            . "<div class='grid grid-cols-3 gap-4'>"
            . "<div class='bg-gray-50 dark:bg-gray-800 rounded-lg p-3'>"
            . "<p class='text-xs text-gray-400 uppercase tracking-wider mb-1'>Fornitore</p>"
            . "<p class='font-semibold text-gray-900 dark:text-white'>" . e($supplierName) . "</p>"
            . "</div>"
            . "<div class='bg-gray-50 dark:bg-gray-800 rounded-lg p-3'>"
            . "<p class='text-xs text-gray-400 uppercase tracking-wider mb-1'>File PDF</p>"
            . "<p class='font-semibold text-gray-900 dark:text-white text-sm'>$file</p>"
            . "</div>"
            . "<div class='bg-teal-50 dark:bg-teal-900/30 rounded-lg p-3'>"
            . "<p class='text-xs text-teal-600 dark:text-teal-400 uppercase tracking-wider mb-1'>Prodotti da importare</p>"
            . "<p class='font-bold text-2xl text-teal-700 dark:text-teal-300'>$count</p>"
            . "</div>"
            . "</div>"
            . "<div class='flex items-start gap-2 text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3'>"
            . "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 flex-shrink-0 mt-0.5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>"
            . "<span>I prodotti saranno importati come <strong>bozze</strong> (non attivi, non disponibili). "
            . "Completa prezzi, categorie e immagini prima di renderli visibili. "
            . "Se un SKU è già presente verrà saltato automaticamente.</span>"
            . "</div>"
            . "</div>";
    }

    // ── Import action ─────────────────────────────────────────────────────────

    public function import(): void
    {
        if (empty($this->extractedProducts)) {
            Notification::make()
                ->title('Nessun prodotto da importare')
                ->body('Estrai prima i prodotti dal PDF.')
                ->warning()
                ->send();
            return;
        }

        $data       = $this->form->getState();
        $supplierId = $data['supplier_id'] ?? null;
        // Filament v5: FileUpload may return array even for single upload
        $rawFile    = $data['file'] ?? null;
        $file       = is_array($rawFile) ? ($rawFile[0] ?? null) : $rawFile;

        if (! $supplierId) {
            Notification::make()
                ->title('Fornitore mancante')
                ->body('Seleziona un fornitore.')
                ->danger()
                ->send();
            return;
        }

        $log = ImportLog::create([
            'supplier_id'     => $supplierId,
            'format'          => 'pdf',
            'file_name'       => basename($file ?? 'unknown.pdf'),
            'file_path'       => $file ?? '',
            'status'          => 'processing',
            'total_rows'      => count($this->extractedProducts),
            'ai_assisted'     => true,
            'extracted_items' => $this->extractedProducts,
            'started_at'      => now(),
        ]);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($this->extractedProducts as $item) {
            try {
                // Skip if SKU already exists for this supplier
                if (! empty($item['sku'])) {
                    $exists = Product::where('supplier_id', $supplierId)
                        ->where('sku', $item['sku'])
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                Product::create([
                    'supplier_id'  => $supplierId,
                    'name'         => $item['name'],
                    'sku'          => $item['sku'] ?? null,
                    'collection'   => $item['collection'] ?? null,
                    'description'  => $item['description'] ?? null,
                    'materials'    => $item['materials'] ?? null,
                    'finishes'     => $item['finishes'] ?? null,
                    'colors'       => $item['colors'] ?? null,
                    'dimensions'   => $item['dimensions'] ?? null,
                    'price_list'   => $item['price_list'] ?? null,
                    'source_file'  => basename($file ?? ''),
                    'is_active'    => false,
                    'is_available' => false,
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ($item['name'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        $log->update([
            'status'          => empty($errors) ? 'done' : 'failed',
            'imported_count'  => $imported,
            'skipped_count'   => $skipped,
            'errors_count'    => count($errors),
            'error_details'   => empty($errors) ? null : $errors,
            'completed_at'    => now(),
        ]);

        Notification::make()
            ->title('Import PDF completato')
            ->body("$imported prodotti importati come bozze. $skipped saltati (SKU già presente).")
            ->success()
            ->send();

        $this->redirect(ImportLogResource::getUrl('index'));
    }
}
