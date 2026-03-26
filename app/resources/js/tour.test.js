/**
 * Test suite — Tour guidato Studio3GHD
 *
 * Testa il core del tour (buildTourCore) usando Vitest + jsdom.
 * Navigazione diretta in onNextClick — nessuna dipendenza da onDestroyed.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { buildTourCore } from './tour.js';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeDriver(config) {
    const instance = {
        drive:          vi.fn(),
        destroy:        vi.fn(),
        moveNext:       vi.fn(),
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

function makeTourCore(overrides = {}) {
    return buildTourCore({
        phases: makePhasesFixture(),
        PHASE_KEY: 'pk',
        WELCOME_KEY: 'wk',
        onNavigate: vi.fn(),
        onCompleteTour: vi.fn(),
        ...overrides,
    });
}

// ─── Setup globale ─────────────────────────────────────────────────────────────

beforeEach(() => {
    sessionStorage.clear();

    Object.defineProperty(window, 'location', {
        value: { pathname: '/admin/products', href: '' },
        writable: true,
        configurable: true,
    });

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
    vi.restoreAllMocks();
});

// ─── isOnPhasePage ─────────────────────────────────────────────────────────────

describe('isOnPhasePage', () => {
    it('ritorna true se il pathname include il match della fase', () => {
        window.location.pathname = '/admin/products';
        const { isOnPhasePage } = makeTourCore();
        expect(isOnPhasePage(makePhasesFixture()[0])).toBe(true);
    });

    it('ritorna false se il pathname include excludeMatch', () => {
        window.location.pathname = '/admin/products/create';
        const { isOnPhasePage } = makeTourCore();
        expect(isOnPhasePage(makePhasesFixture()[0])).toBe(false);
    });

    it('ritorna false se il pathname non include il match', () => {
        window.location.pathname = '/admin/categories';
        const { isOnPhasePage } = makeTourCore();
        expect(isOnPhasePage(makePhasesFixture()[0])).toBe(false);
    });
});

// ─── buildSteps ───────────────────────────────────────────────────────────────

describe('buildSteps', () => {
    it('ritorna gli step della fase senza welcome se WELCOME_KEY non è impostata', () => {
        const { buildSteps } = makeTourCore();
        const steps = buildSteps(0);
        expect(steps).toHaveLength(3);
        expect(steps[0].popover.title).toBe('Step A');
    });

    it('prepende il welcome step se WELCOME_KEY === "1" e fase === 0', () => {
        sessionStorage.setItem('wk', '1');
        const { buildSteps } = makeTourCore();
        const steps = buildSteps(0);
        expect(steps).toHaveLength(4);
        expect(steps[0].popover.title).toBe('Ciao Peppe Greco');
    });

    it('rimuove WELCOME_KEY dopo averlo usato', () => {
        sessionStorage.setItem('wk', '1');
        const { buildSteps } = makeTourCore();
        buildSteps(0);
        expect(sessionStorage.getItem('wk')).toBeNull();
    });

    it('NON prepende welcome step su fase > 0 anche se WELCOME_KEY è impostata', () => {
        sessionStorage.setItem('wk', '1');
        const { buildSteps } = makeTourCore();
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
        const { startPhase } = makeTourCore({ onNavigate });
        startPhase(0);
        expect(onNavigate).toHaveBeenCalledWith('/admin/products');
        expect(sessionStorage.getItem('pk')).toBe('0');
    });

    it('salva PHASE_KEY prima di navigare', () => {
        window.location.pathname = '/admin/categories';
        const { startPhase } = makeTourCore();
        startPhase(1);
        expect(sessionStorage.getItem('pk')).toBe('1');
    });

    it('chiama completeTour se l\'indice supera il numero di fasi', () => {
        const onCompleteTour = vi.fn();
        const { startPhase } = makeTourCore({ onCompleteTour });
        startPhase(99);
        expect(onCompleteTour).toHaveBeenCalledOnce();
    });
});

// ─── startPhase — driver ──────────────────────────────────────────────────────

describe('startPhase — avvio driver', () => {
    it('avvia il driver con drive(0) se non c\'è resumeAtStep', () => {
        const { startPhase } = makeTourCore();
        startPhase(0);
        const driverInstance = window.driver.js.driver.mock.results[0].value;
        expect(driverInstance.drive).toHaveBeenCalledWith(0);
    });

    it('avvia il driver con drive(stepIndex) se viene passato resumeAtStep', () => {
        const { startPhase } = makeTourCore();
        startPhase(0, 2);
        const driverInstance = window.driver.js.driver.mock.results[0].value;
        expect(driverInstance.drive).toHaveBeenCalledWith(2);
    });

    it('tutti gli step hanno onNextClick e onPrevClick iniettati', () => {
        const { startPhase } = makeTourCore();
        startPhase(0);
        const config = window._capturedDriverConfig();
        config.steps.forEach((step) => {
            expect(typeof step.onNextClick).toBe('function');
            expect(typeof step.onPrevClick).toBe('function');
        });
    });
});

// ─── startPhase — completamento naturale ─────────────────────────────────────

describe('startPhase — completamento naturale', () => {
    it('naviga direttamente alla fase successiva quando done button sull\'ultimo step', () => {
        const onNavigate = vi.fn();
        const { startPhase } = makeTourCore({ onNavigate });
        startPhase(0);
        const config = window._capturedDriverConfig();
        const lastStep = config.steps[config.steps.length - 1];
        lastStep.onNextClick(); // navigazione diretta nell'handler
        expect(onNavigate).toHaveBeenCalledWith('/admin/suppliers');
        expect(sessionStorage.getItem('pk')).toBe('1');
    });

    it('chiama completeTour quando done button sull\'ultimo step dell\'ultima fase', () => {
        window.location.pathname = '/admin/suppliers';
        const onCompleteTour = vi.fn();
        const { startPhase } = makeTourCore({ onCompleteTour });
        startPhase(1);
        const config = window._capturedDriverConfig();
        const lastStep = config.steps[config.steps.length - 1];
        lastStep.onNextClick();
        expect(onCompleteTour).toHaveBeenCalledOnce();
    });

    it('chiama moveNext su step non-ultimo', () => {
        const { startPhase } = makeTourCore();
        startPhase(0);
        const config = window._capturedDriverConfig();
        config.steps[0].onNextClick();
        const driverInstance = window.driver.js.driver.mock.results[0].value;
        expect(driverInstance.moveNext).toHaveBeenCalledOnce();
    });
});
