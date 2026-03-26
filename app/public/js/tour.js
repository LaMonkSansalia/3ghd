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

    const PHASE_KEY   = 'studio3ghd_tour_phase';
    const WELCOME_KEY = 'studio3ghd_tour_welcome';

    /**
     * Fasi del tour. Ogni fase:
     *  - url:     pagina a cui navigare prima di mostrare gli step
     *  - match:   stringa che deve essere inclusa nel pathname per riconoscere la pagina
     *  - steps:   array di step Driver.js (element opzionale — se manca il popover è centrato)
     *
     * Selettori Filament v5:
     *  .fi-ta-header-toolbar         barra strumenti tabella (ricerca + filtri + colonne)
     *  .fi-ta-search-field           campo di ricerca
     *  .fi-ta-row:first-child        prima riga della tabella
     *  .fi-ta-col-manager            pulsante gestione colonne
     *  .fi-fo-select-wrp             wrapper di un campo Select (primo = tipo_prodotto)
     *  aside a[href*="..."]          link nella navigazione laterale
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
                    element: '.fi-ta-header-toolbar',
                    popover: {
                        title: 'Filtra e cerca',
                        description: 'Usa la barra di ricerca per trovare per nome, SKU o fornitore. "Filtri" filtra per fornitore, categoria e tipo. "Colonne" mostra o nasconde i campi.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    element: '.fi-ta-row:first-child',
                    popover: {
                        title: 'Modifica un prodotto',
                        description: 'Clicca sull\'icona matita a destra di ogni riga per aprire la scheda prodotto completa con tutti i campi.',
                        side: 'top',
                        align: 'center',
                    },
                },
            ],
        },
        {
            url: '/admin/products/create',
            match: '/admin/products/create',
            steps: [
                {
                    element: '.fi-fo-select-wrp',
                    popover: {
                        title: '✏️ Tipo prodotto',
                        description: 'Seleziona il <strong>Tipo prodotto</strong> — obbligatorio. Campionato per configurazioni con codice fornitore, A listino per prodotti a prezzo fisso.',
                        side: 'bottom',
                        align: 'start',
                    },
                },
                {
                    popover: {
                        title: 'Sidebar — Classificazione',
                        description: 'Nella colonna di destra assegna <strong>Fornitore</strong> e <strong>Categoria</strong>. Se non esistono ancora puoi crearli direttamente dalla select con il pulsante "+". Inserisci anche il Codice fornitore (verbatim dal catalogo).',
                    },
                },
                {
                    popover: {
                        title: 'Sidebar — Prezzi',
                        description: 'Il <strong>Prezzo cliente</strong> si aggiorna automaticamente: Costo acquisto × Markup. Il markup usa quello del fornitore se non imposti un override. Il costo acquisto è visibile solo allo staff.',
                    },
                },
                {
                    popover: {
                        title: 'Sezioni collassate in basso',
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

    function isOnPhasePage(phase) {
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

    function showExitConfirmation(phaseIndex, stepIndex) {
        const existing = document.getElementById('tour-exit-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'tour-exit-overlay';
        overlay.innerHTML = '<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center;">' +
            '<div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18);font-family:inherit;">' +
                '<p style="margin:0 0 8px;font-size:1rem;font-weight:600;color:#111827;">Vuoi togliere il tutorial?</p>' +
                '<p style="margin:0 0 24px;font-size:.9rem;color:#6b7280;">Puoi sempre trovarlo nella <a href="/admin/help" style="color:#0d9488;font-weight:500;text-decoration:underline;">pagina Guida \u2192</a></p>' +
                '<div style="display:flex;gap:10px;justify-content:flex-end;">' +
                    '<button id="tour-exit-resume" style="padding:8px 18px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;font-size:.875rem;color:#374151;">Continua tour</button>' +
                    '<button id="tour-exit-skip" style="padding:8px 18px;border:none;border-radius:8px;background:#0d9488;color:#fff;cursor:pointer;font-size:.875rem;">S\u00ec, salta</button>' +
                '</div>' +
            '</div>' +
        '</div>';
        document.body.appendChild(overlay);

        document.getElementById('tour-exit-skip').addEventListener('click', function () {
            overlay.remove();
            completeTour();
        });

        document.getElementById('tour-exit-resume').addEventListener('click', function () {
            overlay.remove();
            startPhase(phaseIndex, stepIndex);
        });
    }

    function startPhase(index, resumeAtStep) {
        const phase = phases[index];
        if (! phase) {
            completeTour();
            return;
        }

        // Se non siamo sulla pagina giusta, naviga e riprendi
        if (! isOnPhasePage(phase)) {
            sessionStorage.setItem(PHASE_KEY, String(index));
            window.location.href = phase.url;
            return;
        }

        sessionStorage.setItem(PHASE_KEY, String(index));

        // Messaggio di benvenuto — solo al primo accesso (WELCOME_KEY impostato dall'avvio automatico)
        let steps = phase.steps.slice();
        if (index === 0 && sessionStorage.getItem(WELCOME_KEY) === '1') {
            sessionStorage.removeItem(WELCOME_KEY);
            steps = [
                {
                    popover: {
                        title: 'Ciao Peppe Greco',
                        description: 'Questo è lo strumento che ti supporterà in tutto quello che vuoi. Ho preparato un breve tutorial e riorganizzato le informazioni. Fammi sapere che ne pensi.<br><br><em>— Placito</em>',
                    },
                },
            ].concat(steps);
        }

        const isLastPhase = index === phases.length - 1;

        // Intercetta il completamento naturale (click su "Prossima sezione →" o "✓ Fatto!")
        // sull'ultimo step. Se invece l'utente clicca X, onDestroyed mostra la conferma.
        let completedNaturally = false;
        let activeStepIndex = resumeAtStep || 0;
        const lastIdx = steps.length - 1;
        steps[lastIdx] = Object.assign({}, steps[lastIdx], {
            onNextClick: function () {
                completedNaturally = true;
                driverObj.moveNext();
            },
        });

        let driverObj;
        driverObj = window.driver.js.driver({
            showProgress: false,
            steps: steps,
            nextBtnText: 'Avanti \u2192',
            prevBtnText: '\u2190 Indietro',
            doneBtnText: isLastPhase ? '\u2713 Fatto!' : 'Prossima sezione \u2192',
            onDestroyStarted: function () {
                activeStepIndex = driverObj.getActiveIndex() || 0;
            },
            onDestroyed: function () {
                if (completedNaturally) {
                    const next = index + 1;
                    if (next < phases.length) {
                        sessionStorage.setItem(PHASE_KEY, String(next));
                        window.location.href = phases[next].url;
                    } else {
                        completeTour();
                    }
                } else {
                    showExitConfirmation(index, activeStepIndex);
                }
            },
        });

        driverObj.drive(resumeAtStep || 0);
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
            sessionStorage.setItem(WELCOME_KEY, '1');
            setTimeout(function () { startPhase(0); }, 800);
        }
    });
})();
