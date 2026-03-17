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

    {{-- Items grid --}}
    @php $items = $this->record->items ?? []; @endphp

    @if(count($items) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($items as $idx => $item)
                @php
                    $isSelected = in_array($idx, $selectedItems);
                    $isImported = $item['imported'] ?? false;
                @endphp
                <div
                    wire:click="toggleItem({{ $idx }})"
                    class="relative cursor-pointer rounded-xl border-2 transition-all
                        {{ $isImported ? 'border-gray-200 bg-gray-50 dark:bg-gray-900 opacity-60' : ($isSelected ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20' : 'border-gray-200 bg-white dark:bg-gray-900 hover:border-teal-300') }}"
                >
                    {{-- Selection checkbox --}}
                    <div class="absolute top-3 right-3">
                        @if($isImported)
                            <span class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-500 rounded-full px-2 py-0.5">importato</span>
                        @elseif($isSelected)
                            <div class="w-5 h-5 rounded bg-teal-500 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        @else
                            <div class="w-5 h-5 rounded border-2 border-gray-300 dark:border-gray-600"></div>
                        @endif
                    </div>

                    <div class="p-4">
                        {{-- Type badge --}}
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full mb-2
                            {{ $item['type'] === 'collection' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $item['type'] === 'collection' ? 'Collezione' : 'Prodotto' }}
                        </span>

                        {{-- Name --}}
                        <h3 class="font-semibold text-gray-900 dark:text-white text-sm leading-snug pr-6">
                            {{ $item['name'] }}
                        </h3>

                        {{-- Description --}}
                        @if(!empty($item['description']))
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                {{ $item['description'] }}
                            </p>
                        @endif

                        {{-- URL --}}
                        <a href="{{ $item['url'] }}" target="_blank" wire:click.stop
                           class="text-xs text-teal-600 hover:underline mt-2 block truncate">
                            {{ parse_url($item['url'], PHP_URL_HOST) }}{{ parse_url($item['url'], PHP_URL_PATH) }}
                        </a>

                        {{-- Sub-items (h2s) --}}
                        @if(!empty($item['h2s']))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach(array_slice($item['h2s'], 0, 4) as $h2)
                                    <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded px-1.5 py-0.5">
                                        {{ $h2 }}
                                    </span>
                                @endforeach
                                @if(count($item['h2s']) > 4)
                                    <span class="text-xs text-gray-400">+{{ count($item['h2s']) - 4 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($this->record->status === 'done')
        <div class="text-center py-12 text-gray-400">
            <svg class="mx-auto h-12 w-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <p>Nessun elemento trovato. Il sito potrebbe richiedere JavaScript per caricare i contenuti.</p>
        </div>
    @endif

</x-filament-panels::page>
