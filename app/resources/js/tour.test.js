/**
 * Test suite — Tour guidato Studio3GHD
 *
 * Testa il core del tour (buildTourCore, showExitConfirmation) usando Vitest + jsdom.
 * Il bootstrap browser (IIFE) non viene eseguito nei test perché window.driver è undefined.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { buildTourCore, showExitConfirmation } from './tour.js';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeDriver(config) {
    const instance = {
        drive:          vi.fn(),
        destroy:        vi.fn(() => config.onDestroyed?.()),
        moveNext:       vi.fn(() => config.onDestroyed?.()),
        movePrevious:   vi.fn(),
        getActiveIndex: vi.fn(() => 0),
    };
    return instance;
}

function makePhasesFixture() {
    return [
        {
            url: '/admin/products',
            match: '/admin/products',
            excludeMatch: '/create',
            steps: [
                { popover: { title: 'Step A' } },
                { popover: { title: 'Step B' } },
                { popover: { title: 'Step C' } },
            ],
        },
        {
            url: '/admin/suppliers',
            match: '/admin/suppliers',
            steps: [
                { popover: { title: 'Step D' } },
            ],
        },
    ];
}

// ─── Setup globale ─────────────────────────────────────────────────────────────

beforeEach(() => {
    // Pulisci sessionStorage
    sessionStorage.clear();

    // Simula window.location.pathname = /admin/products (pagina corretta per fase 0)
    Object.defineProperty(window, 'location', {
        value: { pathname: '/admin/products', href: '' },
        writable: true,
        configurable: true,
    });

    // Installa window.driver mock — buildTourCore ne ha bisogno
    let capturedConfig;
    window.driver = {
        js: {
            driver: vi.fn((config) => {
                capturedConfig = config;
                return makeDriver(config);
            }),
        },
    };
    window._capturedDriverConfig = () => capturedConfig;
});

afterEach(() => {
    document.getElementById('tour-exit-overlay')?.remove();
    vi.restoreAllMocks();
});

// ─── isOnPhasePage ─────────────────────────────────────────────────────────────

describe('isOnPhasePage', () => {
    it('ritorna true se il pathname include il match della fase', () => {
        window.location.pathname = '/admin/products';
        const { isOnPhasePage } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'w',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        expect(isOnPhasePage(makePhasesFixture()[0])).toBe(true);
    });

    it('ritorna false se il pathname include excludeMatch', () => {
        window.location.pathname = '/admin/products/create';
        const { isOnPhasePage } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'w',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        expect(isOnPhasePage(makePhasesFixture()[0])).toBe(false);
    });

    it('ritorna false se il pathname non include il match', () => {
        window.location.pathname = '/admin/categories';
        const { isOnPhasePage } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'w',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        expect(isOnPhasePage(makePhasesFixture()[0])).toBe(false);
    });
});

// ─── buildSteps ───────────────────────────────────────────────────────────────

describe('buildSteps', () => {
    it('ritorna gli step della fase senza welcome se WELCOME_KEY non è impostata', () => {
        const { buildSteps } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        const steps = buildSteps(0);
        expect(steps).toHaveLength(3);
        expect(steps[0].popover.title).toBe('Step A');
    });

    it('prepende il welcome step se WELCOME_KEY === "1" e fase === 0', () => {
        sessionStorage.setItem('wk', '1');
        const { buildSteps } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        const steps = buildSteps(0);
        expect(steps).toHaveLength(4);
        expect(steps[0].popover.title).toBe('Ciao Peppe Greco');
    });

    it('rimuove WELCOME_KEY dopo averlo usato', () => {
        sessionStorage.setItem('wk', '1');
        const { buildSteps } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        buildSteps(0);
        expect(sessionStorage.getItem('wk')).toBeNull();
    });

    it('NON prepende welcome step su fase > 0 anche se WELCOME_KEY è impostata', () => {
        sessionStorage.setItem('wk', '1');
        const { buildSteps } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'k', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        const steps = buildSteps(1);
        expect(steps).toHaveLength(1);
        expect(steps[0].popover.title).toBe('Step D');
    });
});

// ─── startPhase — navigazione ─────────────────────────────────────────────────

describe('startPhase — navigazione', () => {
    it('naviga alla URL della fase se non siamo sulla pagina corretta', () => {
        window.location.pathname = '/admin/categories';
        const onNavigate = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate, onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        startPhase(0);
        expect(onNavigate).toHaveBeenCalledWith('/admin/products');
        expect(sessionStorage.getItem('pk')).toBe('0');
    });

    it('salva PHASE_KEY prima di navigare', () => {
        window.location.pathname = '/admin/categories';
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        startPhase(1);
        expect(sessionStorage.getItem('pk')).toBe('1');
    });

    it('chiama completeTour se l\'indice supera il numero di fasi', () => {
        const onCompleteTour = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour, onShowOverlay: vi.fn(),
        });
        startPhase(99);
        expect(onCompleteTour).toHaveBeenCalledOnce();
    });
});

// ─── startPhase — driver ──────────────────────────────────────────────────────

describe('startPhase — avvio driver', () => {
    it('avvia il driver con drive(0) se non c\'è resumeAtStep', () => {
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        startPhase(0);
        const driverInstance = window.driver.js.driver.mock.results[0].value;
        expect(driverInstance.drive).toHaveBeenCalledWith(0);
    });

    it('avvia il driver con drive(stepIndex) se viene passato resumeAtStep', () => {
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        startPhase(0, 2);
        const driverInstance = window.driver.js.driver.mock.results[0].value;
        expect(driverInstance.drive).toHaveBeenCalledWith(2);
    });

    it('tutti gli step hanno onNextClick e onPrevClick iniettati', () => {
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        startPhase(0);
        const config = window._capturedDriverConfig();
        config.steps.forEach((step) => {
            expect(typeof step.onNextClick).toBe('function');
            expect(typeof step.onPrevClick).toBe('function');
        });
    });
});

// ─── startPhase — completamento naturale (done button) ───────────────────────

describe('startPhase — completamento naturale', () => {
    it('naviga alla fase successiva quando onNextClick sull\'ultimo step → onDestroyed', () => {
        const onNavigate = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate, onCompleteTour: vi.fn(), onShowOverlay: vi.fn(),
        });
        startPhase(0);
        const config = window._capturedDriverConfig();
        const lastStep = config.steps[config.steps.length - 1];
        lastStep.onNextClick(); // simula click su "Prossima sezione →"
        expect(onNavigate).toHaveBeenCalledWith('/admin/suppliers');
        expect(sessionStorage.getItem('pk')).toBe('1');
    });

    it('chiama completeTour quando onNextClick sull\'ultimo step dell\'ultima fase', () => {
        window.location.pathname = '/admin/suppliers';
        const onCompleteTour = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour, onShowOverlay: vi.fn(),
        });
        startPhase(1);
        const config = window._capturedDriverConfig();
        const lastStep = config.steps[config.steps.length - 1];
        lastStep.onNextClick();
        expect(onCompleteTour).toHaveBeenCalledOnce();
    });
});

// ─── startPhase — X / chiusura prematura ─────────────────────────────────────

describe('startPhase — chiusura con X', () => {
    it('chiama onShowOverlay con phaseIndex e stepIndex corrente quando onDestroyed senza completamento', () => {
        const onShowOverlay = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay,
        });
        startPhase(0);
        const config = window._capturedDriverConfig();
        // Simula X premuta: onDestroyed senza aver cliccato onNextClick sull'ultimo step
        config.onDestroyed();
        expect(onShowOverlay).toHaveBeenCalledWith(0, 0, expect.any(Function));
    });

    it('aggiorna activeStepIndex quando onNextClick su step non-ultimo', () => {
        const onShowOverlay = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay,
        });
        startPhase(0);
        const config = window._capturedDriverConfig();
        // Avanza allo step 1
        config.steps[0].onNextClick();
        // Poi simula X
        config.onDestroyed();
        expect(onShowOverlay).toHaveBeenCalledWith(0, 1, expect.any(Function));
    });

    it('traccia indietro con onPrevClick', () => {
        const onShowOverlay = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate: vi.fn(), onCompleteTour: vi.fn(), onShowOverlay,
        });
        startPhase(0, 2); // parte dallo step 2
        const config = window._capturedDriverConfig();
        config.steps[2].onPrevClick(); // torna a step 1
        config.onDestroyed();
        expect(onShowOverlay).toHaveBeenCalledWith(0, 1, expect.any(Function));
    });
});

// ─── showExitConfirmation ─────────────────────────────────────────────────────

describe('showExitConfirmation', () => {
    it('aggiunge l\'overlay al DOM', () => {
        showExitConfirmation(0, 1, vi.fn(), vi.fn());
        expect(document.getElementById('tour-exit-overlay')).not.toBeNull();
    });

    it('rimuove un eventuale overlay precedente prima di crearne uno nuovo', () => {
        showExitConfirmation(0, 0, vi.fn(), vi.fn());
        showExitConfirmation(0, 0, vi.fn(), vi.fn());
        expect(document.querySelectorAll('#tour-exit-overlay')).toHaveLength(1);
    });

    it('"Sì, salta" chiama onCompleteTour e rimuove l\'overlay', () => {
        const onCompleteTour = vi.fn();
        showExitConfirmation(0, 0, vi.fn(), onCompleteTour);
        document.getElementById('tour-exit-skip').click();
        expect(onCompleteTour).toHaveBeenCalledOnce();
        expect(document.getElementById('tour-exit-overlay')).toBeNull();
    });

    it('"Continua tour" chiama startPhase con phaseIndex e stepIndex corretti', () => {
        const startPhase = vi.fn();
        showExitConfirmation(0, 2, startPhase, vi.fn());
        document.getElementById('tour-exit-resume').click();
        expect(startPhase).toHaveBeenCalledWith(0, 2);
        expect(document.getElementById('tour-exit-overlay')).toBeNull();
    });

    it('"Continua tour" dopo X riprende dallo step corretto, non dall\'inizio', () => {
        // Scenario completo: X premuta allo step 1 → Continua → deve ripartire dallo step 1
        const onShowOverlay = vi.fn((phaseIdx, stepIdx, resume) => {
            showExitConfirmation(phaseIdx, stepIdx, resume, vi.fn());
        });
        const onNavigate = vi.fn();
        const { startPhase } = buildTourCore({
            phases: makePhasesFixture(), PHASE_KEY: 'pk', WELCOME_KEY: 'wk',
            onNavigate, onCompleteTour: vi.fn(), onShowOverlay,
        });

        // Avvia fase 0
        startPhase(0);
        const firstConfig = window._capturedDriverConfig();

        // Avanza allo step 1
        firstConfig.steps[0].onNextClick();

        // Simula X
        firstConfig.onDestroyed();
        expect(document.getElementById('tour-exit-overlay')).not.toBeNull();

        // Clicca "Continua tour"
        document.getElementById('tour-exit-resume').click();

        // Il secondo driver deve essere avviato con drive(1)
        const secondDriverInstance = window.driver.js.driver.mock.results[1].value;
        expect(secondDriverInstance.drive).toHaveBeenCalledWith(1);
    });
});
