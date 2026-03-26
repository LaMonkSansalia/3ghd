<?php

namespace App\Filament\Pages;

use App\Jobs\ImportProductsJob;
use App\Models\ImportLog;
use App\Models\Supplier;
use App\Services\SpreadsheetParser;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ImportWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.import-wizard';

    protected static bool $shouldRegisterNavigation = false;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Importa Catalogo';
    protected static \UnitEnum|string|null $navigationGroup = 'Catalogo';
    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Importa Catalogo Fornitore';

    // ── Parsed state (populated after file upload) ──────────────────────────
    public array $parsedHeaders = [];
    public array $parsedPreview = [];
    public int   $parsedTotalRows = 0;
    public string $detectedFormat = '';

    // ── Result state (populated after job dispatch) ──────────────────────────
    public ?int $importLogId = null;

    // ── Form data ────────────────────────────────────────────────────────────
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([

                    // ── Step 1: Upload ───────────────────────────────────────
                    Wizard\Step::make('upload')
                        ->label('Upload')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Fornitore')
                                ->options(Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->placeholder('Seleziona fornitore...'),

                            FileUpload::make('file')
                                ->label('File catalogo')
                                ->helperText('Formati supportati: CSV, Excel (.xlsx, .xls) — max 20 MB')
                                ->disk('local')
                                ->directory('imports')
                                ->acceptedFileTypes([
                                    'text/csv',
                                    'text/plain',
                                    'application/csv',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->maxSize(20480)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, $livewire) {
                                    // Filament v5 / Livewire 3: state is TemporaryUploadedFile or array thereof.
                                    $file = is_array($state) ? ($state[0] ?? null) : $state;

                                    if (! $file) {
                                        $livewire->parsedHeaders   = [];
                                        $livewire->parsedPreview   = [];
                                        $livewire->parsedTotalRows = 0;
                                        return;
                                    }

                                    $path = ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                        ? $file->getRealPath()
                                        : Storage::disk('local')->path((string) $file);

                                    if (! $path || ! file_exists($path)) {
                                        $livewire->parsedHeaders   = [];
                                        $livewire->parsedPreview   = [];
                                        $livewire->parsedTotalRows = 0;
                                        return;
                                    }

                                    try {
                                        $parser = app(SpreadsheetParser::class);
                                        $result = $parser->parse($path);

                                        $livewire->parsedHeaders   = $result['headers'];
                                        $livewire->parsedPreview   = $result['preview'];
                                        $livewire->parsedTotalRows = $result['total_rows'];

                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                        $livewire->detectedFormat = match ($ext) {
                                            'csv'  => 'csv',
                                            'xlsx' => 'excel',
                                            'xls'  => 'excel',
                                            default => 'csv',
                                        };
                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->title('Errore lettura file')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),

                            Placeholder::make('file_info')
                                ->label('')
                                ->content(function ($livewire) {
                                    if (empty($livewire->parsedHeaders)) {
                                        return new HtmlString('');
                                    }
                                    $rows  = $livewire->parsedTotalRows;
                                    $cols  = count($livewire->parsedHeaders);
                                    $fmt   = strtoupper($livewire->detectedFormat);
                                    return new HtmlString(
                                        "<div class='flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400'>"
                                        . "<span class='inline-flex items-center px-2 py-0.5 rounded bg-teal-100 text-teal-800 font-medium'>$fmt</span>"
                                        . "<span>$rows righe · $cols colonne rilevate</span>"
                                        . "</div>"
                                    );
                                }),
                        ]),

                    // ── Step 2: Mapping colonne ──────────────────────────────
                    Wizard\Step::make('mapping')
                        ->label('Mapping colonne')
                        ->icon('heroicon-o-table-cells')
                        ->schema([

                            // Anteprima prime 5 righe
                            Placeholder::make('preview_table')
                                ->label('Anteprima file')
                                ->content(fn ($livewire) => new HtmlString(
                                    $livewire->renderPreviewTable()
                                ))
                                ->columnSpanFull(),

                            // Mapping per ogni campo prodotto
                            Grid::make(2)
                                ->schema([
                                    Select::make('mapping.name')
                                        ->label('Nome prodotto *')
                                        ->options(fn ($livewire) => $livewire->headerOptions(true))
                                        ->placeholder('-- Seleziona colonna --')
                                        ->required(),

                                    Select::make('mapping.sku')
                                        ->label('Codice / SKU')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.collection')
                                        ->label('Collezione / Serie')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.brand')
                                        ->label('Brand')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.description')
                                        ->label('Descrizione')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.materials')
                                        ->label('Materiali')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.price_list')
                                        ->label('Prezzo listino')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.cost')
                                        ->label('Prezzo acquisto (PRIVATO)')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),

                                    Select::make('mapping.notes')
                                        ->label('Note')
                                        ->options(fn ($livewire) => $livewire->headerOptions())
                                        ->placeholder('-- Non mappato --'),
                                ]),
                        ]),

                    // ── Step 3: Conferma ─────────────────────────────────────
                    Wizard\Step::make('conferma')
                        ->label('Conferma')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Placeholder::make('summary')
                                ->label('Riepilogo import')
                                ->content(fn ($livewire) => new HtmlString(
                                    $livewire->renderSummary()
                                ))
                                ->columnSpanFull(),
                        ]),

                ])
                ->submitAction(new HtmlString(
                    '<button type="button" wire:click="import" '
                    . 'class="fi-btn fi-btn-size-md fi-btn-color-success fi-color-success '
                    . 'inline-flex items-center gap-1.5 font-semibold rounded-lg px-4 py-2 '
                    . 'bg-teal-600 text-white hover:bg-teal-500 focus:ring-2 focus:ring-teal-500">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>'
                    . ' Avvia Import'
                    . '</button>'
                )),
            ]);
    }

    // ── Helper: header options for Select ────────────────────────────────────

    public function headerOptions(bool $required = false): array
    {
        $options = $required ? [] : ['' => '— Non mappato —'];
        foreach ($this->parsedHeaders as $h) {
            $options[$h] = $h;
        }
        return $options;
    }

    // ── Helper: HTML preview table ────────────────────────────────────────────

    public function renderPreviewTable(): string
    {
        if (empty($this->parsedPreview)) {
            return '<p class="text-sm text-gray-400">Carica un file nel passo precedente.</p>';
        }

        $headers = $this->parsedHeaders;
        $rows    = $this->parsedPreview;
        $total   = $this->parsedTotalRows;

        $th = implode('', array_map(
            fn($h) => '<th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">'
                . e($h) . '</th>',
            $headers
        ));

        $tbody = '';
        foreach ($rows as $row) {
            $tds = implode('', array_map(
                fn($h) => '<td class="px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 truncate max-w-[200px]">'
                    . e($row[$h] ?? '') . '</td>',
                $headers
            ));
            $tbody .= "<tr class='border-t border-gray-100 dark:border-gray-700'>$tds</tr>";
        }

        $shown = count($rows);
        $more  = $total - $shown;
        $footer = $more > 0
            ? "<p class='text-xs text-gray-400 mt-2'>Mostrate $shown righe su $total totali.</p>"
            : "<p class='text-xs text-gray-400 mt-2'>$total righe totali.</p>";

        return "<div class='overflow-x-auto rounded border border-gray-200 dark:border-gray-700'>"
            . "<table class='min-w-full divide-y divide-gray-200 dark:divide-gray-700'>"
            . "<thead class='bg-gray-50 dark:bg-gray-800'><tr>$th</tr></thead>"
            . "<tbody class='bg-white dark:bg-gray-900'>$tbody</tbody>"
            . "</table></div>$footer";
    }

    // ── Helper: summary HTML ──────────────────────────────────────────────────

    public function renderSummary(): string
    {
        $data = $this->data ?? [];

        $supplierId = $data['supplier_id'] ?? null;
        $supplierName = $supplierId
            ? (Supplier::find($supplierId)?->name ?? "ID $supplierId")
            : '—';

        $rawFile = $data['file'] ?? null;
        $rawFile = is_array($rawFile) ? ($rawFile[0] ?? null) : $rawFile;
        $file    = ($rawFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
            ? $rawFile->getClientOriginalName()
            : ($rawFile ? (string) $rawFile : '—');
        $rows    = $this->parsedTotalRows;
        $format  = strtoupper($this->detectedFormat ?: '—');
        $mapping = $data['mapping'] ?? [];
        $mapped  = array_filter($mapping);

        $mappingRows = '';
        $labels = [
            'name'        => 'Nome prodotto',
            'sku'         => 'Codice / SKU',
            'collection'  => 'Collezione / Serie',
            'brand'       => 'Brand',
            'description' => 'Descrizione',
            'materials'   => 'Materiali',
            'price_list'  => 'Prezzo listino',
            'cost'        => 'Prezzo acquisto',
            'notes'       => 'Note',
        ];
        foreach ($labels as $field => $label) {
            $col = $mapping[$field] ?? null;
            $badge = $col
                ? "<span class='text-teal-700 dark:text-teal-400 font-medium'>← $col</span>"
                : "<span class='text-gray-400'>— non mappato</span>";
            $mappingRows .= "<tr class='border-t border-gray-100 dark:border-gray-700'>"
                . "<td class='py-1.5 pr-4 text-sm text-gray-600 dark:text-gray-400'>$label</td>"
                . "<td class='py-1.5 text-sm'>$badge</td>"
                . "</tr>";
        }

        return "<div class='space-y-4'>"
            . "<div class='grid grid-cols-2 gap-4'>"
            . "<div class='bg-gray-50 dark:bg-gray-800 rounded-lg p-3'>"
            . "<p class='text-xs text-gray-400 uppercase tracking-wider mb-1'>Fornitore</p>"
            . "<p class='font-semibold text-gray-900 dark:text-white'>" . e($supplierName) . "</p>"
            . "</div>"
            . "<div class='bg-gray-50 dark:bg-gray-800 rounded-lg p-3'>"
            . "<p class='text-xs text-gray-400 uppercase tracking-wider mb-1'>File</p>"
            . "<p class='font-semibold text-gray-900 dark:text-white text-sm'>" . e(basename($file)) . " <span class='text-gray-400 font-normal'>($format · $rows righe)</span></p>"
            . "</div>"
            . "</div>"
            . "<div>"
            . "<p class='text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2'>Mapping colonne (" . count($mapped) . "/" . count($labels) . " campi)</p>"
            . "<table class='w-full'><tbody>$mappingRows</tbody></table>"
            . "</div>"
            . (isset($mapping['name']) && $mapping['name']
                ? "<div class='flex items-center gap-2 text-sm text-teal-700 dark:text-teal-400 font-medium'>"
                . "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/></svg>"
                . "Pronto per l'import"
                . "</div>"
                : "<div class='flex items-center gap-2 text-sm text-amber-600 font-medium'>"
                . "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01M12 4a8 8 0 100 16 8 8 0 000-16z'/></svg>"
                . "Mappa almeno il campo <strong>Nome prodotto</strong> per procedere"
                . "</div>")
            . "</div>";
    }

    // ── Import action ─────────────────────────────────────────────────────────

    public function import(): void
    {
        $data = $this->form->getState();

        $supplierId = $data['supplier_id'] ?? null;
        // Filament v5: FileUpload may return array even for single upload
        $rawFile    = $data['file'] ?? null;
        $file       = is_array($rawFile) ? ($rawFile[0] ?? null) : $rawFile;
        $mapping    = $data['mapping'] ?? [];

        if (! $supplierId || ! $file) {
            Notification::make()
                ->title('Dati mancanti')
                ->body('Seleziona fornitore e carica un file.')
                ->danger()
                ->send();
            return;
        }

        if (empty($mapping['name'] ?? null)) {
            Notification::make()
                ->title('Mapping incompleto')
                ->body('Devi mappare almeno il campo "Nome prodotto".')
                ->warning()
                ->send();
            return;
        }

        $fileName = basename($file);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $format = match ($ext) {
            'csv'  => 'csv',
            'xlsx' => 'excel',
            'xls'  => 'excel',
            default => 'csv',
        };

        $log = ImportLog::create([
            'supplier_id'    => $supplierId,
            'format'         => $format,
            'file_name'      => $fileName,
            'file_path'      => $file,
            'status'         => 'pending',
            'total_rows'     => $this->parsedTotalRows,
            'column_mapping' => array_filter($mapping),
            'ai_assisted'    => false,
        ]);

        ImportProductsJob::dispatch($log->id, $file, array_filter($mapping));

        $this->importLogId = $log->id;

        Notification::make()
            ->title('Import avviato')
            ->body("Job #{$log->id} in coda. Verifica lo stato nei Log Importazioni.")
            ->success()
            ->send();

        $this->redirect(
            \App\Filament\Resources\ImportLogs\ImportLogResource::getUrl('index')
        );
    }
}
