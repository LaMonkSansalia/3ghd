<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Analizza il sito del fornitore</x-slot>
        <x-slot name="description">
            Inserisci l'URL della pagina prodotti/catalogo del fornitore.
            Claude AI estrarrà prodotti, collezioni, materiali e finiture in background.
            I risultati saranno disponibili in <strong>Analisi Siti</strong> una volta completata l'analisi.
            <br><em class="text-amber-600 text-xs">I prezzi non sono disponibili sul web — andranno inseriti tramite Import Catalogo.</em>
        </x-slot>

        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>

        <div class="mt-4">
            <x-filament::button
                wire:click="dispatch"
                wire:loading.attr="disabled"
                icon="heroicon-o-rocket-launch"
                color="primary"
            >
                <span wire:loading.remove>Avvia analisi</span>
                <span wire:loading>Avvio in corso...</span>
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
