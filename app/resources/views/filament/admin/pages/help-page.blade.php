<x-filament-panels::page>
    <div style="max-width: 860px;">

        {{-- Tour button ------------------------------------------------- --}}
        <div style="margin-bottom: 1.5rem;">
            <button
                onclick="window.startStudio3GHDTour && window.startStudio3GHDTour()"
                style="
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.5rem 1.25rem;
                    background: #0D9488;
                    color: #fff;
                    border: none;
                    border-radius: 0.5rem;
                    font-size: 0.875rem;
                    font-weight: 600;
                    cursor: pointer;
                "
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Riavvia tour guidato
            </button>
        </div>

        {{-- Docs sections ------------------------------------------------ --}}
        @foreach([
            ['icon' => '📦', 'title' => 'Prodotti', 'file' => 'prodotti'],
            ['icon' => '🏭', 'title' => 'Fornitori', 'file' => 'fornitori'],
            ['icon' => '🗂️', 'title' => 'Categorie', 'file' => 'categorie'],
            ['icon' => '❓', 'title' => 'FAQ',       'file' => 'faq'],
        ] as $section)
            @php
                $path = resource_path('docs/' . $section['file'] . '.md');
                $content = file_exists($path) ? \Illuminate\Support\Str::markdown(file_get_contents($path)) : '<p>Documentazione non disponibile.</p>';
            @endphp

            <details style="
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                overflow: hidden;
            ">
                <summary style="
                    padding: 1rem 1.25rem;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    user-select: none;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    list-style: none;
                ">
                    <span>{{ $section['icon'] }}</span>
                    <span>{{ $section['title'] }}</span>
                </summary>

                <div style="
                    padding: 1.25rem 1.5rem;
                    border-top: 1px solid #e5e7eb;
                    font-size: 0.9rem;
                    line-height: 1.7;
                " class="prose prose-sm max-w-none">
                    {!! $content !!}
                </div>
            </details>
        @endforeach

    </div>
</x-filament-panels::page>
