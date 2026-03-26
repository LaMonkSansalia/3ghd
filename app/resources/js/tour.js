/**
 * Tour guidato Studio3GHD — Driver.js
 * Si avvia automaticamente al primo accesso (tourCompleted === false).
 * Può essere rilanciato dalla pagina Guida con window.startStudio3GHDTour().
 */
(function () {
    if (typeof window.driver === 'undefined') return;

    const steps = [
        // ── Prodotti ──────────────────────────────────────────────────────
        {
            element: 'a[href*="/admin/products"]',
            popover: {
                title: 'Prodotti',
                description: 'Qui trovi il catalogo completo. Ogni riga è un prodotto. Puoi filtrare per fornitore, categoria e tipo.',
                side: 'right',
            },
        },
        {
            element: 'a[href*="/admin/products/create"]',
            popover: {
                title: 'Nuovo prodotto',
                description: 'Clicca qui per aggiungere un nuovo prodotto. Inizia sempre selezionando il Tipo prodotto.',
                side: 'right',
            },
        },

        // ── Fornitori ─────────────────────────────────────────────────────
        {
            element: 'a[href*="/admin/suppliers"]',
            popover: {
                title: 'Fornitori',
                description: 'Gestisci i fornitori del catalogo. In ogni scheda fornitore trovi la lista dei suoi prodotti.',
                side: 'right',
            },
        },

        // ── Categorie ─────────────────────────────────────────────────────
        {
            element: 'a[href*="/admin/categories"]',
            popover: {
                title: 'Categorie',
                description: 'Organizza i prodotti in categorie gerarchiche. Le categorie radice sono in grassetto, le sottocategorie sono rientrate.',
                side: 'right',
            },
        },

        // ── Guida ─────────────────────────────────────────────────────────
        {
            element: 'a[href*="/admin/help"]',
            popover: {
                title: 'Questa pagina — Guida',
                description: 'Qui trovi la documentazione di tutte le funzionalità. Puoi rilanciare questo tour quando vuoi.',
                side: 'right',
            },
        },
    ];

    function completeTour() {
        fetch('/studio/tour-complete', {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        }).catch(() => {});
    }

    function startTour() {
        const driverObj = window.driver.js.driver({
            showProgress: true,
            steps: steps,
            nextBtnText: 'Avanti →',
            prevBtnText: '← Indietro',
            doneBtnText: 'Fatto!',
            onDestroyed: completeTour,
        });
        driverObj.drive();
    }

    // Esporta per il pulsante "Riavvia tour" nella pagina Guida
    window.startStudio3GHDTour = startTour;

    // Avvio automatico al primo accesso
    document.addEventListener('DOMContentLoaded', function () {
        if (window.studio3ghdTourPending === true) {
            setTimeout(startTour, 800);
        }
    });
})();
