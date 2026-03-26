/**
 * Tour guidato Studio3GHD — Driver.js
 *
 * Il tour è suddiviso in fasi, una per pagina.
 * Ogni fase naviga alla pagina corretta prima di mostrare i suoi step.
 * Lo stato viene persistito in sessionStorage così sopravvive ai reload.
 *
 * Avvio automatico se tour_completed === false (primo accesso).
 * Rilanciabile dalla pagina Guida con window.startStudio3GHDTour().
 */
(function () {
    if (typeof window.driver === 'undefined') return;

    const PHASE_KEY = 'studio3ghd_tour_phase';

    /**
     * Fasi del tour. Ogni fase:
     *  - url:     pagina a cui navigare prima di mostrare gli step
     *  - match:   stringa che deve essere inclusa nel pathname per riconoscere la pagina
     *  - steps:   array di step Driver.js (element opzionale — se manca il popover è centrato)
     */
    const phases = [
        {
            url: '/admin/products',
            match: '/admin/products',
            excludeMatch: '/create',
            steps: [
                {
                    element: 'aside a[href*="/admin/products"]',
                    popover: {
                        title: '📦 Prodotti',
                        description: 'Qui trovi il catalogo completo. Ogni riga è un prodotto con codice, fornitore e prezzo.',
                        side: 'right',
                    },
                },
                {
                    popover: {
                        title: 'Filtra e cerca',
                        description: 'Usa la barra di ricerca per trovare per nome, SKU o fornitore. Il pulsante "Filtri" consente di filtrare per fornitore, categoria e tipo. "Colonne" mostra o nasconde i campi visibili.',
                    },
                },
                {
                    popover: {
                        title: 'Modifica un prodotto',
                        description: 'Clicca sull\'icona matita a destra di ogni riga per aprire la scheda prodotto completa con tutti i campi.',
                    },
                },
            ],
        },
        {
            url: '/admin/products/create',
            match: '/admin/products/create',
            steps: [
                {
                    popover: {
                        title: '✏️ Scheda prodotto',
                        description: 'In alto a sinistra seleziona il <strong>Tipo prodotto</strong> — obbligatorio. Campionato per configurazioni con codice fornitore, A listino per prodotti a prezzo fisso.',
                    },
                },
                {
                    popover: {
                        title: 'Sidebar destra — Classificazione',
                        description: 'Assegna <strong>Fornitore</strong> e <strong>Categoria</strong>. Se non esistono ancora puoi crearli direttamente dalla select con il pulsante "+". Inserisci anche il Codice fornitore (verbatim dal catalogo).',
                    },
                },
                {
                    popover: {
                        title: 'Sidebar destra — Prezzi',
                        description: 'Il <strong>Prezzo cliente</strong> si aggiorna automaticamente: Costo acquisto × Markup. Il markup usa quello del fornitore se non imposti un override. Il costo acquisto è visibile solo allo staff.',
                    },
                },
                {
                    popover: {
                        title: 'Sezioni in basso (collapsed)',
                        description: 'Clicca su <strong>Attributi prodotto</strong> per aggiungere rivestimento, colore, gambe, ecc. In <strong>Dimensioni</strong> inserisci L × P × H. In <strong>Note e Tag</strong> aggiungi memo interni.',
                    },
                },
            ],
        },
        {
            url: '/admin/suppliers',
            match: '/admin/suppliers',
            excludeMatch: '/edit',
            steps: [
                {
                    element: 'aside a[href*="/admin/suppliers"]',
                    popover: {
                        title: '🏭 Fornitori',
                        description: 'Gestisci i fornitori del catalogo. Il markup default si applica a tutti i prodotti senza override individuale.',
                        side: 'right',
                    },
                },
                {
                    popover: {
                        title: 'Scheda fornitore',
                        description: 'Apri una scheda fornitore per vedere nome, sito, markup e contatti. In fondo trovi la lista dei suoi prodotti — clicca per modificarli direttamente.',
                    },
                },
            ],
        },
        {
            url: '/admin/categories',
            match: '/admin/categories',
            steps: [
                {
                    element: 'aside a[href*="/admin/categories"]',
                    popover: {
                        title: '🗂️ Categorie',
                        description: 'Vista ad albero gerarchica. Le categorie radice sono in grassetto, le sottocategorie sono rientrate con └─. Usa le categorie per filtrare i prodotti.',
                        side: 'right',
                    },
                },
            ],
        },
        {
            url: '/admin/help',
            match: '/admin/help',
            steps: [
                {
                    element: 'aside a[href*="/admin/help"]',
                    popover: {
                        title: '❓ Guida',
                        description: 'Qui trovi la documentazione completa di tutte le funzionalità. Puoi rilanciare questo tour quando vuoi con il pulsante "Riavvia tour guidato".',
                        side: 'right',
                    },
                },
            ],
        },
    ];

    function isOnPhagePage(phase) {
        const path = window.location.pathname;
        if (phase.excludeMatch && path.includes(phase.excludeMatch)) return false;
        return path.includes(phase.match);
    }

    function completeTour() {
        sessionStorage.removeItem(PHASE_KEY);
        fetch('/studio/tour-complete', {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        }).catch(() => {});
    }

    function startPhase(index) {
        const phase = phases[index];
        if (! phase) {
            completeTour();
            return;
        }

        // Se non siamo sulla pagina giusta, naviga e riprendi
        if (! isOnPhagePage(phase)) {
            sessionStorage.setItem(PHASE_KEY, String(index));
            window.location.href = phase.url;
            return;
        }

        sessionStorage.setItem(PHASE_KEY, String(index));

        const isLastPhase = index === phases.length - 1;

        const driverObj = window.driver.js.driver({
            showProgress: true,
            progressText: 'Sezione {{current}} di ' + phases.length,
            steps: phase.steps,
            nextBtnText: 'Avanti →',
            prevBtnText: '← Indietro',
            doneBtnText: isLastPhase ? '✓ Fatto!' : 'Prossima sezione →',
            onDestroyed: function () {
                const next = index + 1;
                if (next < phases.length) {
                    sessionStorage.setItem(PHASE_KEY, String(next));
                    window.location.href = phases[next].url;
                } else {
                    completeTour();
                }
            },
        });

        driverObj.drive();
    }

    // Esporta per il pulsante "Riavvia tour" nella pagina Guida
    window.startStudio3GHDTour = function () {
        sessionStorage.removeItem(PHASE_KEY);
        startPhase(0);
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Fase pendente da navigazione precedente
        const pendingPhase = sessionStorage.getItem(PHASE_KEY);
        if (pendingPhase !== null) {
            setTimeout(function () { startPhase(parseInt(pendingPhase, 10)); }, 600);
            return;
        }

        // Avvio automatico al primo accesso
        if (window.studio3ghdTourPending === true) {
            setTimeout(function () { startPhase(0); }, 800);
        }
    });
})();
