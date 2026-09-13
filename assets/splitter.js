const STORAGE_KEY = 'variation-editor-split';
const MIN_RATIO = 0.22;
const MAX_RATIO = 0.78;

export function initSplitter(workspace, splitter) {
    if (!workspace || !splitter) {
        return;
    }

    const panes = [...workspace.querySelectorAll('[data-pane]')];
    if (panes.length < 2) {
        return;
    }

    let ratio = readRatio();
    applyRatio(workspace, ratio);

    let dragging = false;

    const startDrag = (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }
        dragging = true;
        splitter.classList.add('is-dragging');
        splitter.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    };

    const moveDrag = (event) => {
        if (!dragging) {
            return;
        }
        const rect = workspace.getBoundingClientRect();
        const stacked = window.matchMedia('(max-width: 880px)').matches;
        const position = stacked ? event.clientY - rect.top : event.clientX - rect.left;
        const size = stacked ? rect.height : rect.width;
        ratio = clamp((position - 5) / Math.max(size, 1));
        applyRatio(workspace, ratio);
    };

    const endDrag = () => {
        if (!dragging) {
            return;
        }
        dragging = false;
        splitter.classList.remove('is-dragging');
        try {
            localStorage.setItem(STORAGE_KEY, String(ratio));
        } catch {
            // Private mode or quota: keep the in-memory ratio only.
        }
    };

    splitter.addEventListener('pointerdown', startDrag);
    window.addEventListener('pointermove', moveDrag);
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);

    splitter.addEventListener('keydown', (event) => {
        const step = event.shiftKey ? 0.08 : 0.03;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            ratio = clamp(ratio - step);
            applyRatio(workspace, ratio);
            event.preventDefault();
        }
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            ratio = clamp(ratio + step);
            applyRatio(workspace, ratio);
            event.preventDefault();
        }
    });

    window.addEventListener('resize', () => applyRatio(workspace, ratio));
}

function applyRatio(workspace, ratio) {
    workspace.style.setProperty('--split', `${(ratio * 100).toFixed(2)}%`);
}

function clamp(value) {
    return Math.min(MAX_RATIO, Math.max(MIN_RATIO, value));
}

function readRatio() {
    try {
        const stored = Number.parseFloat(localStorage.getItem(STORAGE_KEY) ?? '');
        if (Number.isFinite(stored)) {
            return clamp(stored);
        }
    } catch {
        // Ignore storage errors.
    }
    return 0.5;
}
