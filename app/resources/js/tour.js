/**
 * Tour guidato Studio3GHD — Driver.js
 *
 * ARCHITETTURA:
 * onDestroyed si attiva su OGNI transizione step — non è affidabile per rilevare
 * il completamento. Soluzione: la navigazione avviene direttamente in onNextClick
 * sull'ultimo step, senza dipendere da onDestroyed.
 *
 * X / ESC: il tour si chiude e basta — nessun overlay.
 */

/**
 * Logica core esportata per i test (Vitest).
 * @param {object} deps
 *   phases, PHASE_KEY, WELCOME_KEY,
 *   onNavigate(url), onCompleteTour()
 */
export function buildTourCore({ phases, PHASE_KEY, WELCOME_KEY, onNavigate, onCompleteTour }) {

    function isOnPhasePage(phase) {
        const path = window.location.pathname;
        if (phase.excludeMatch && path.includes(phase.excludeMatch)) return false;
        return path.includes(phase.match);
    }

    function buildSteps(index) {
        const phase = phases[index];
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

        const steps = buildSteps(index);
        const isLastPhase = index === phases.length - 1;

        let driverObj;

        const mappedSteps = steps.map(function (step, i) {
            const isLast = i === steps.length - 1;
            return Object.assign({}, step, {
                onNextClick: function () {
                    if (isLast) {
                        // Navigazione diretta — non dipende da onDestroyed
                        const next = index + 1;
                        if (next < phases.length) {
                            sessionStorage.setItem(PHASE_KEY, String(next));
                            onNavigate(phases[next].url);
                        } else {
                            onCompleteTour();
                            driverObj.destroy();
                        }
                    } else {
                        driverObj.moveNext();
                    }
                },
                onPrevClick: function () {
                    driverObj.movePrevious();
                },
            });
        });

        driverObj = window.driver.js.driver({
            showProgress: false,
            steps: mappedSteps,
            nextBtnText: 'Avanti \u2192',
            prevBtnText: '\u2190 Indietro',
            doneBtnText: isLastPhase ? '\u2713 Fatto!' : 'Prossima sezione \u2192',
        });

        driverObj.drive(resumeAtStep || 0);
    }

    return { startPhase, buildSteps, isOnPhasePage };
}

// ─── Bootstrap browser ───────────────────────────────────────────────────────
if (typeof window !== 'undefined' && typeof window.driver !== 'undefined') {
    const PHASE_KEY   = 'studio3ghd_tour_phase';
    const WELCOME_KEY = 'studio3ghd_tour_welcome';

    const phases = [
        {
            url: '/admin/products',
            match: '/admin/products',
            excludeMatch: '/create',
            steps: [
                { element: 'aside a[href*="/admin/products"]', popover: { title: '\uD83D\uDCE6 Prodotti', description: 'Qui trovi il catalogo completo. Ogni riga è un prodotto con codice, fornitore e prezzo.', side: 'right' } },
                { element: '.fi-ta-header-toolbar', popover: { title: 'Filtra e cerca', description: 'Usa la barra di ricerca per trovare per nome, SKU o fornitore. "Filtri" filtra per fornitore, categoria e tipo. "Colonne" mostra o nasconde i campi.', side: 'bottom', align: 'start' } },
                { element: '.fi-ta-row:first-child', popover: { title: 'Modifica un prodotto', description: 'Clicca sull\\'icona matita a destra di ogni riga per aprire la scheda prodotto completa con tutti i campi.', side: 'top', align: 'center' } },
            ],
        },
        {
            url: '/admin/products/create',
            match: '/admin/products/create',
            steps: [
                { element: '.fi-fo-select-wrp', popover: { title: '\u270F\uFE0F Tipo prodotto', description: 'Seleziona il <strong>Tipo prodotto</strong> — obbligatorio. Campionato per configurazioni con codice fornitore, A listino per prodotti a prezzo fisso.', side: 'bottom', align: 'start' } },
                { popover: { title: 'Sidebar \u2014 Classificazione', description: 'Nella colonna di destra assegna <strong>Fornitore</strong> e <strong>Categoria</strong>. Se non esistono ancora puoi crearli dalla select con il pulsante "+". Inserisci anche il Codice fornitore (verbatim dal catalogo).' } },
                { popover: { title: 'Sidebar \u2014 Prezzi', description: 'Il <strong>Prezzo cliente</strong> si aggiorna automaticamente: Costo acquisto \u00d7 Markup. Il markup usa quello del fornitore se non imposti un override. Il costo acquisto è visibile solo allo staff.' } },
                { popover: { title: 'Sezioni collassate in basso', description: 'Clicca su <strong>Attributi prodotto</strong> per aggiungere rivestimento, colore, gambe, ecc. In <strong>Dimensioni</strong> inserisci L \u00d7 P \u00d7 H. In <strong>Note e Tag</strong> aggiungi memo interni.' } },
            ],
        },
        {
            url: '/admin/suppliers',
            match: '/admin/suppliers',
            excludeMatch: '/edit',
            steps: [
                { element: 'aside a[href*="/admin/suppliers"]', popover: { title: '\uD83C\uDFED Fornitori', description: 'Gestisci i fornitori del catalogo. Il markup default si applica a tutti i prodotti senza override individuale.', side: 'right' } },
                { popover: { title: 'Scheda fornitore', description: 'Apri una scheda fornitore per vedere nome, sito, markup e contatti. In fondo trovi la lista dei suoi prodotti \u2014 clicca per modificarli direttamente.' } },
            ],
        },
        {
            url: '/admin/categories',
            match: '/admin/categories',
            steps: [
                { element: 'aside a[href*="/admin/categories"]', popover: { title: '\uD83D\uDDC2\uFE0F Categorie', description: 'Vista ad albero gerarchica. Le categorie radice sono in grassetto, le sottocategorie sono rientrate con \u2514\u2500. Usa le categorie per filtrare i prodotti.', side: 'right' } },
            ],
        },
        {
            url: '/admin/help',
            match: '/admin/help',
            steps: [
                { element: 'aside a[href*="/admin/help"]', popover: { title: '\u2753 Guida', description: 'Qui trovi la documentazione completa di tutte le funzionalit\u00e0. Puoi rilanciare questo tour quando vuoi con il pulsante "Riavvia tour guidato".', side: 'right' } },
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
