import { createLocalStore } from './api.js?v=9';
import { bindToolbar } from './toolbar.js';
import { initSplitter } from './splitter.js';
import { bindPairing, createPairHighlight } from './pair.js?v=8';

const bootstrap = JSON.parse(document.getElementById('app-bootstrap').textContent);
const store = createLocalStore(bootstrap.maxDocumentBytes);
const languages = bootstrap.languages ?? {};
const appStatus = document.querySelector('[data-app-status]');
const panes = new Map();
const TIPTAP = 'https://esm.sh';
const VERSION = '3.31.3';

initSplitter(
    document.querySelector('[data-workspace]'),
    document.querySelector('[data-splitter]'),
);

window.addEventListener('beforeunload', flushDirty);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        flushDirty();
    }
});

start().catch((error) => {
    console.error(error);
    setAppStatus(error.message || 'Editors failed to load');
    for (const shell of document.querySelectorAll('[data-editor]')) {
        shell.innerHTML = `<p class="editor-fallback">${escapeHtml(error.message || 'The editor could not start.')}</p>`;
    }
});

async function start() {
    setAppStatus('Loading editors…');

    const [coreModule, starterKitModule, extensionsModule, textAlignModule, pmState, pmView] = await Promise.all([
        import(`${TIPTAP}/@tiptap/core@${VERSION}`),
        import(`${TIPTAP}/@tiptap/starter-kit@${VERSION}`),
        import(`${TIPTAP}/@tiptap/extensions@${VERSION}`),
        import(`${TIPTAP}/@tiptap/extension-text-align@${VERSION}`),
        import(`${TIPTAP}/@tiptap/pm@${VERSION}/state`),
        import(`${TIPTAP}/@tiptap/pm@${VERSION}/view`),
    ]);

    const Editor = coreModule.Editor;
    const StarterKit = starterKitModule.default ?? starterKitModule.StarterKit;
    const { CharacterCount, Placeholder } = extensionsModule;
    const TextAlign = textAlignModule.default ?? textAlignModule.TextAlign;
    const pairDeps = {
        Extension: coreModule.Extension,
        Plugin: pmState.Plugin,
        PluginKey: pmState.PluginKey,
        Decoration: pmView.Decoration,
        DecorationSet: pmView.DecorationSet,
    };

    if (!Editor || !StarterKit || !Placeholder || !CharacterCount || !TextAlign || !pairDeps.Decoration || !pairDeps.DecorationSet) {
        throw new Error('Tiptap modules loaded incompletely.');
    }

    for (const pane of bootstrap.panes) {
        mountPane(pane, { Editor, StarterKit, Placeholder, CharacterCount, TextAlign, pairDeps });
    }

    bindPairing(panes);
    setAppStatus('Ready');
}

function mountPane(pane, { Editor, StarterKit, Placeholder, CharacterCount, TextAlign, pairDeps }) {
    const pair = createPairHighlight(pairDeps);
    const mount = document.querySelector(`[data-editor="${pane.id}"]`);
    const toolbar = document.querySelector(`[data-toolbar="${pane.id}"]`);
    const status = document.querySelector(`[data-status="${pane.id}"]`);
    const counts = document.querySelector(`[data-counts="${pane.id}"]`);

    if (!mount || !toolbar) {
        return;
    }

    const langSelect = document.querySelector(`[data-lang="${pane.id}"]`);
    const paneRoot = document.querySelector(`[data-pane="${pane.id}"]`);
    const saved = store.read(pane.id);
    const lang = resolveLanguage(saved?.lang, pane.lang || 'en');
    const html = saved?.html ?? pane.html ?? '';

    if (langSelect) {
        langSelect.value = lang;
    }
    paneRoot?.setAttribute('lang', lang);

    const state = {
        id: pane.id,
        dirty: false,
        saving: false,
        timer: 0,
        lang,
        lastSaved: html,
        lastSavedLang: lang,
        status,
        counts,
        paneRoot,
    };

    const editor = new Editor({
        element: mount,
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3] },
                link: { openOnClick: false, autolink: true },
            }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({
                placeholder: pane.placeholder || 'Start writing…',
            }),
            CharacterCount,
            pair.extension,
        ],
        content: html,
        editorProps: {
            attributes: {
                class: 'rte-content',
                lang,
                dir: pane.dir || 'ltr',
                spellcheck: 'true',
            },
        },
        onCreate({ editor: instance }) {
            updateCounts(state, instance);
        },
        onUpdate({ editor: instance, transaction }) {
            updateCounts(state, instance);
            if (transaction.docChanged) {
                markDirty(state);
                scheduleSave(state, instance);
            }
        },
    });

    bindToolbar(toolbar, editor);
    state.editor = editor;
    state.setPairedIndex = pair.setPairedIndex;
    panes.set(pane.id, state);

    langSelect?.addEventListener('change', () => {
        applyLanguage(state, editor, langSelect.value);
        markDirty(state);
        scheduleSave(state, editor);
    });
}

function applyLanguage(state, editor, lang) {
    state.lang = lang;
    state.paneRoot?.setAttribute('lang', lang);
    editor.setOptions({
        editorProps: {
            attributes: {
                class: 'rte-content',
                lang,
                dir: state.paneRoot?.getAttribute('dir') || 'ltr',
                spellcheck: 'true',
            },
        },
    });
    editor.view.dom.setAttribute('lang', lang);
}

function markDirty(state) {
    state.dirty = true;
    setPaneStatus(state, 'Unsaved', 'unsaved');
    setAppStatus('Editing…');
}

function scheduleSave(state, editor) {
    window.clearTimeout(state.timer);
    state.timer = window.setTimeout(() => {
        savePane(state, editor);
    }, bootstrap.autosaveDelayMs);
}

function savePane(state, editor) {
    const html = editor.getHTML();
    if (!state.dirty || (html === state.lastSaved && state.lang === state.lastSavedLang)) {
        state.dirty = false;
        setPaneStatus(state, 'Saved', 'saved');
        return;
    }

    persistPane(state, html);
}

function persistPane(state, html) {
    try {
        store.write(state.id, html, state.lang);
        state.lastSaved = html;
        state.lastSavedLang = state.lang;
        state.dirty = false;
        setPaneStatus(state, 'Saved', 'saved');
        setAppStatus('Saved in this browser');
    } catch (error) {
        setPaneStatus(state, 'Save failed', 'error');
        setAppStatus(error.message || 'Save failed');
    }
}

function flushDirty() {
    for (const state of panes.values()) {
        window.clearTimeout(state.timer);
        if (!state.editor || !state.dirty) {
            continue;
        }
        persistPane(state, state.editor.getHTML());
    }
}

function resolveLanguage(lang, fallback) {
    if (typeof lang === 'string' && Object.hasOwn(languages, lang)) {
        return lang;
    }
    return fallback;
}

function updateCounts(state, editor) {
    if (!state.counts) {
        return;
    }
    const characters = editor.storage.characterCount.characters();
    const words = editor.storage.characterCount.words();
    state.counts.textContent = `${formatCount(words, 'word')} · ${formatCount(characters, 'character')}`;
}

function formatCount(value, noun) {
    return `${value} ${noun}${value === 1 ? '' : 's'}`;
}

function setPaneStatus(state, label, tone) {
    if (!state.status) {
        return;
    }
    state.status.textContent = label;
    state.status.dataset.tone = tone;
}

function setAppStatus(label) {
    if (appStatus) {
        appStatus.textContent = label;
    }
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
