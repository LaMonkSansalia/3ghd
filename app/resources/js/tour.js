/**
 * Tour guidato Studio3GHD — Driver.js
 *
 * Il tour è suddiviso in fasi, una per pagina.
 * Ogni fase naviga alla pagina corretta prima di mostrare i suoi step.
 * Lo stato viene persistito in sessionStorage così sopravvive ai reload.
 *
 * Avvio automatico se tour_completed === false (primo accesso).
 * Rilanciabile dalla pagina Guida con window.startStudio3GHDTour().
 *
 * NOTA Driver.js v1: onDestroyStarted si attiva su OGNI transizione step,
 * non solo su X/ESC. Per distinguere chiusura volontaria (X) da completamento
 * naturale (done button) si usano onNextClick/onPrevClick su tutti gli step
 * per tenere traccia dell'indice attivo, e un flag completedNaturally.
 */

/**
 * Logica core esportata per i test (Vitest).
 * Nel browser viene usata dall'IIFE qui sotto.
 */
export function buildTourCore({ phases, PHASE_KEY, WELCOME_KEY, onNavigate, onCompleteTour, onShowOverlay }) {

    function isOnPhasePage(phase) {
        const path = window.location.pathname;
        if (phase.excludeMatch && path.includes(phase.excludeMatch)) return false;
        return path.includes(phase.match);
    }

    function buildSteps(index, resumeAtStep) {
        const phase = phases[index];
        let steps = phase.steps.slice();

        // Messaggio di benvenuto — solo al primo accesso
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

        return steps;
    }

    function startPhase(index, resumeAtStep) {
        const phase = phases[index];
        if (! phase) {
            onCompleteTour();
            return;
        }

        if (! isOnPhasePage(phase)) {
            sessionStorage.setItem(PHASE_KEY, String(index));
            onNavigate(phase.url);
            return;
        }

        sessionStorage.setItem(PHASE_KEY, String(index));

        const steps = buildSteps(index, resumeAtStep);
        const isLastPhase = index === phases.length - 1;

        let completedNaturally = false;
        let activeStepIndex = resumeAtStep || 0;

        // Mappa tutti gli step con onNextClick/onPrevClick per tracciare la posizione.
        // sull'ultimo step, onNextClick imposta completedNaturally=true.
        const mappedSteps = steps.map(function (step, i) {
            const isLast = i === steps.length - 1;
            return Object.assign({}, step, {
                onNextClick: function () {
                    if (isLast) {
                        completedNaturally = true;
                    } else {
                        activeStepIndex = i + 1;
                    }
                    driverObj.moveNext();
                },
                onPrevClick: function () {
                    activeStepIndex = Math.max(0, i - 1);
                    driverObj.movePrevious();
                },
            });
        });

        let driverObj;
        driverObj = window.driver.js.driver({
            showProgress: false,
            steps: mappedSteps,
            nextBtnText: 'Avanti \u2192',
            prevBtnText: '\u2190 Indietro',
            doneBtnText: isLastPhase ? '\u2713 Fatto!' : 'Prossima sezione \u2192',
            onDestroyed: function () {
                if (completedNaturally) {
                    const next = index + 1;
                    if (next < phases.length) {
                        sessionStorage.setItem(PHASE_KEY, String(next));
                        onNavigate(phases[next].url);
                    } else {
                        onCompleteTour();
                    }
                } else {
                    onShowOverlay(index, activeStepIndex, startPhase);
                }
            },
        });

        driverObj.drive(resumeAtStep || 0);
    }

    return { startPhase, buildSteps, isOnPhasePage };
}

/**
 * Mostra l'overlay di conferma uscita tour.
 * Separato per essere testabile indipendentemente.
 */
export function showExitConfirmation(phaseIndex, stepIndex, startPhase, onCompleteTour) {
    const existing = document.getElementById('tour-exit-overlay');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'tour-exit-overlay';
    overlay.innerHTML =
        '<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center;">' +
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
        onCompleteTour();
    });

    document.getElementById('tour-exit-resume').addEventListener('click', function () {
        overlay.remove();
        startPhase(phaseIndex, stepIndex);
    });
}

// ─── Bootstrap browser (non eseguito nei test) ───────────────────────────────
if (typeof window !== 'undefined' && typeof window.driver !== 'undefined') {
    const PHASE_KEY   = 'studio3ghd_tour_phase';
    const WELCOME_KEY = 'studio3ghd_tour_welcome';

    const phases = [
        {
            url: '/admin/products',
            match: '/admin/products',
            excludeMatch: '/create',
            steps: [
                {
                    element: 'aside a[href*="/admin/products"]',
                    popover: { title: '\uD83D\uDCE6 Prodotti', description: 'Qui trovi il catalogo completo. Ogni riga è un prodotto con codice, fornitore e prezzo.', side: 'right' },
                },
                {
                    element: '.fi-ta-header-toolbar',
                    popover: { title: 'Filtra e cerca', description: 'Usa la barra di ricerca per trovare per nome, SKU o fornitore. "Filtri" filtra per fornitore, categoria e tipo. "Colonne" mostra o nasconde i campi.', side: 'bottom', align: 'start' },
                },
                {
                    element: '.fi-ta-row:first-child',
                    popover: { title: 'Modifica un prodotto', description: 'Clicca sull\'icona matita a destra di ogni riga per aprire la scheda prodotto completa con tutti i campi.', side: 'top', align: 'center' },
                },
            ],
        },
        {
            url: '/admin/products/create',
            match: '/admin/products/create',
            steps: [
                {
                    element: '.fi-fo-select-wrp',
                    popover: { title: '\u270F\uFE0F Tipo prodotto', description: 'Seleziona il <strong>Tipo prodotto</strong> — obbligatorio. Campionato per configurazioni con codice fornitore, A listino per prodotti a prezzo fisso.', side: 'bottom', align: 'start' },
                },
                { popover: { title: 'Sidebar — Classificazione', description: 'Nella colonna di destra assegna <strong>Fornitore</strong> e <strong>Categoria</strong>. Se non esistono ancora puoi crearli direttamente dalla select con il pulsante "+". Inserisci anche il Codice fornitore (verbatim dal catalogo).' } },
                { popover: { title: 'Sidebar — Prezzi', description: 'Il <strong>Prezzo cliente</strong> si aggiorna automaticamente: Costo acquisto × Markup. Il markup usa quello del fornitore se non imposti un override. Il costo acquisto è visibile solo allo staff.' } },
                { popover: { title: 'Sezioni collassate in basso', description: 'Clicca su <strong>Attributi prodotto</strong> per aggiungere rivestimento, colore, gambe, ecc. In <strong>Dimensioni</strong> inserisci L × P × H. In <strong>Note e Tag</strong> aggiungi memo interni.' } },
            ],
        },
        {
            url: '/admin/suppliers',
            match: '/admin/suppliers',
            excludeMatch: '/edit',
            steps: [
                {
                    element: 'aside a[href*="/admin/suppliers"]',
                    popover: { title: '\uD83C\uDFED Fornitori', description: 'Gestisci i fornitori del catalogo. Il markup default si applica a tutti i prodotti senza override individuale.', side: 'right' },
                },
                { popover: { title: 'Scheda fornitore', description: 'Apri una scheda fornitore per vedere nome, sito, markup e contatti. In fondo trovi la lista dei suoi prodotti — clicca per modificarli direttamente.' } },
            ],
        },
        {
            url: '/admin/categories',
            match: '/admin/categories',
            steps: [
                {
                    element: 'aside a[href*="/admin/categories"]',
                    popover: { title: '\uD83D\uDDC2\uFE0F Categorie', description: 'Vista ad albero gerarchica. Le categorie radice sono in grassetto, le sottocategorie sono rientrate con └─. Usa le categorie per filtrare i prodotti.', side: 'right' },
                },
            ],
        },
        {
            url: '/admin/help',
            match: '/admin/help',
            steps: [
                {
                    element: 'aside a[href*="/admin/help"]',
                    popover: { title: '\u2753 Guida', description: 'Qui trovi la documentazione completa di tutte le funzionalità. Puoi rilanciare questo tour quando vuoi con il pulsante "Riavvia tour guidato".', side: 'right' },
                },
            ],
        },
    ];

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

    const { startPhase } = buildTourCore({
        phases,
        PHASE_KEY,
        WELCOME_KEY,
        onNavigate: (url) => { window.location.href = url; },
        onCompleteTour: completeTour,
        onShowOverlay: (phaseIdx, stepIdx, resume) => showExitConfirmation(phaseIdx, stepIdx, resume, completeTour),
    });

    window.startStudio3GHDTour = function () {
        sessionStorage.removeItem(PHASE_KEY);
        startPhase(0);
    };

    document.addEventListener('DOMContentLoaded', function () {
        const pendingPhase = sessionStorage.getItem(PHASE_KEY);
        if (pendingPhase !== null) {
            setTimeout(function () { startPhase(parseInt(pendingPhase, 10)); }, 600);
            return;
        }
        if (window.studio3ghdTourPending === true) {
            sessionStorage.setItem(WELCOME_KEY, '1');
            setTimeout(function () { startPhase(0); }, 800);
        }
    });
}
