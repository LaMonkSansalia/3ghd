<x-filament-panels::page>

    {{-- Status bar --}}
    <div class="flex items-center gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-sm">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wider">Fornitore</p>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $this->record->supplier->name }}</p>
        </div>
        <div class="border-l border-gray-200 dark:border-gray-700 pl-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">URL</p>
            <a href="{{ $this->record->entry_url }}" target="_blank" class="text-sm text-teal-600 hover:underline truncate max-w-xs block">
                {{ $this->record->entry_url }}
            </a>
        </div>
        <div class="border-l border-gray-200 dark:border-gray-700 pl-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Trovati</p>
            <p class="font-bold text-2xl text-teal-600">{{ $this->record->items_found }}</p>
        </div>
        <div class="border-l border-gray-200 dark:border-gray-700 pl-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Importati</p>
            <p class="font-bold text-2xl text-gray-500">{{ $this->record->items_imported }}</p>
        </div>
        <div class="border-l border-gray-200 dark:border-gray-700 pl-4 ml-auto">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Selezionati</p>
            <p class="font-bold text-2xl text-amber-600">{{ count($selectedItems) }}</p>
        </div>
    </div>

    {{-- Failed state --}}
    @if($this->record->status === 'failed')
        <div class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 text-red-700 dark:text-red-400">
            <p class="font-semibold">Analisi fallita</p>
            <p class="text-sm mt-1">{{ $this->record->notes }}</p>
        </div>
    @endif

    {{-- Crawling state --}}
    @if($this->record->status === 'crawling')
        <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 text-amber-700 dark:text-amber-400">
            <p class="font-semibold flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Analisi in corso...
            </p>
            <p class="text-sm mt-1">Aggiorna la pagina tra qualche istante per vedere i risultati.</p>
        </div>
    @endif

    {{-- Editable table --}}
    @if(count($editableItems) > 0)

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid #e5e7eb;" class="dark:border-gray-700">
                            <th style="width:40px;padding:10px 12px;text-align:center;">
                                <input
                                    type="checkbox"
                                    wire:click="toggleAll"
                                    {{ count($selectedItems) === count(array_filter($editableItems, fn($i) => !($i['imported'] ?? false))) && count($selectedItems) > 0 ? 'checked' : '' }}
                                    style="width:16px;height:16px;cursor:pointer;accent-color:#0D9488;"
                                />
                            </th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">Tipo</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">Nome</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">Collezione</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">Descrizione</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">Dati PDF</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">URL</th>
                            <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;" class="text-gray-500 dark:text-gray-400">Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($editableItems as $idx => $item)
                            @php
                                $isSelected = in_array($idx, $selectedItems);
                                $isImported = $item['imported'] ?? false;
                            @endphp
                            <tr
                                style="border-bottom:1px solid #f3f4f6;{{ $isImported ? 'opacity:0.5;' : ($isSelected ? 'background-color:rgba(13,148,136,.05);' : '') }}"
                                class="dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/40"
                            >
                                {{-- Checkbox --}}
                                <td style="padding:8px 12px;text-align:center;">
                                    @if($isImported)
                                        <span style="color:#9ca3af;font-size:18px;">✓</span>
                                    @else
                                        <input
                                            type="checkbox"
                                            wire:click="toggleItem({{ $idx }})"
                                            {{ $isSelected ? 'checked' : '' }}
                                            style="width:16px;height:16px;cursor:pointer;accent-color:#0D9488;"
                                        />
                                    @endif
                                </td>

                                {{-- Tipo + source badge --}}
                                <td style="padding:8px 12px;white-space:nowrap;">
                                    <select
                                        wire:model.blur="editableItems.{{ $idx }}.type"
                                        style="font-size:11px;padding:2px 6px;border-radius:999px;border:1px solid #e5e7eb;background:{{ $item['type'] === 'collection' ? '#f3e8ff' : '#eff6ff' }};color:{{ $item['type'] === 'collection' ? '#7e22ce' : '#1d4ed8' }};cursor:pointer;"
                                    >
                                        <option value="collection" {{ ($item['type'] ?? '') === 'collection' ? 'selected' : '' }}>Collezione</option>
                                        <option value="product" {{ ($item['type'] ?? '') !== 'collection' ? 'selected' : '' }}>Prodotto</option>
                                    </select>
                                    @if(($item['source'] ?? 'html') === 'pdf')
                                        <span style="display:block;margin-top:3px;font-size:10px;background:#fed7aa;color:#c2410c;padding:1px 6px;border-radius:9999px;width:fit-content;">PDF</span>
                                    @endif
                                </td>

                                {{-- Nome (editabile) --}}
                                <td style="padding:8px 12px;min-width:180px;">
                                    <input
                                        type="text"
                                        wire:model.blur="editableItems.{{ $idx }}.name"
                                        style="width:100%;padding:4px 8px;border:1px solid transparent;border-radius:6px;font-size:13px;font-weight:500;background:transparent;box-sizing:border-box;"
                                        class="text-gray-900 dark:text-white hover:border-gray-300 dark:hover:border-gray-600 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 dark:bg-transparent"
                                        placeholder="Nome prodotto"
                                    />
                                </td>

                                {{-- Collezione (editabile) --}}
                                <td style="padding:8px 12px;min-width:140px;">
                                    <input
                                        type="text"
                                        wire:model.blur="editableItems.{{ $idx }}.collection"
                                        style="width:100%;padding:4px 8px;border:1px solid transparent;border-radius:6px;font-size:13px;background:transparent;box-sizing:border-box;"
                                        class="text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 dark:bg-transparent"
                                        placeholder="—"
                                    />
                                </td>

                                {{-- Descrizione (editabile) --}}
                                <td style="padding:8px 12px;min-width:220px;max-width:320px;">
                                    <textarea
                                        wire:model.blur="editableItems.{{ $idx }}.description"
                                        rows="2"
                                        style="width:100%;padding:4px 8px;border:1px solid transparent;border-radius:6px;font-size:12px;line-height:1.4;background:transparent;resize:vertical;box-sizing:border-box;"
                                        class="text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 dark:bg-transparent"
                                        placeholder="—"
                                    >{{ $item['description'] ?? '' }}</textarea>
                                </td>

                                {{-- Dati PDF (sku, price_list, dimensions) --}}
                                <td style="padding:8px 12px;min-width:120px;white-space:nowrap;">
                                    @if(!empty($item['sku']))
                                        <p style="font-size:11px;color:#6b7280;margin:0 0 2px;">SKU: <strong>{{ $item['sku'] }}</strong></p>
                                    @endif
                                    @if(!empty($item['price_list']))
                                        <p style="font-size:11px;color:#0D9488;font-weight:600;margin:0 0 2px;">€ {{ number_format((float)$item['price_list'], 2) }}</p>
                                    @endif
                                    @if(!empty($item['dimensions']))
                                        @php $d = $item['dimensions']; @endphp
                                        <p style="font-size:10px;color:#9ca3af;margin:0;">
                                            {{ ($d['width'] ?? $d['l'] ?? null) ? 'L'.($d['width'] ?? $d['l']) : '' }}
                                            {{ ($d['depth'] ?? $d['p'] ?? null) ? '×P'.($d['depth'] ?? $d['p']) : '' }}
                                            {{ ($d['height'] ?? $d['h'] ?? null) ? '×H'.($d['height'] ?? $d['h']) : '' }} cm
                                        </p>
                                    @endif
                                    @if(empty($item['sku']) && empty($item['price_list']) && empty($item['dimensions']))
                                        <span style="font-size:12px;color:#d1d5db;">—</span>
                                    @endif
                                </td>

                                {{-- URL --}}
                                <td style="padding:8px 12px;max-width:160px;">
                                    @if(!empty($item['url']))
                                        <a
                                            href="{{ $item['url'] }}"
                                            target="_blank"
                                            wire:click.stop
                                            style="font-size:11px;color:#0D9488;text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                            title="{{ $item['url'] }}"
                                        >
                                            {{ parse_url($item['url'], PHP_URL_HOST) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400" style="font-size:12px;">—</span>
                                    @endif
                                </td>

                                {{-- Stato --}}
                                <td style="padding:8px 12px;text-align:center;white-space:nowrap;">
                                    @if($isImported)
                                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:#f3f4f6;color:#6b7280;">importato</span>
                                    @else
                                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:#f0fdf4;color:#16a34a;">pronto</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($this->record->status === 'done')
        <div class="text-center py-12 text-gray-400">
            <svg class="mx-auto mb-3 opacity-40" style="width:48px;height:48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <p>Nessun elemento trovato. Il sito potrebbe richiedere JavaScript per caricare i contenuti.</p>
        </div>
    @endif

</x-filament-panels::page>
