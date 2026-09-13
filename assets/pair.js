/**
 * Pair the current top-level block in one editor with the same-index block
 * in the other via ProseMirror decorations (so the yellow highlight survives
 * redraws) and keep both block tops at the same viewport height.
 */

const PLUGIN_KEY_NAME = 'pairHighlight';

let syncing = false;

export function createPairHighlight({ Extension, Plugin, PluginKey, Decoration, DecorationSet }) {
    const pluginKey = new PluginKey(`${PLUGIN_KEY_NAME}-${Math.random().toString(36).slice(2)}`);

    const extension = Extension.create({
        name: PLUGIN_KEY_NAME,

        addProseMirrorPlugins() {
            return [
                new Plugin({
                    key: pluginKey,
                    state: {
                        init: (_config, state) => pairState(state.doc, -1, Decoration, DecorationSet),
                        apply(tr, current, _oldState, state) {
                            const meta = tr.getMeta(pluginKey);
                            if (meta && Number.isInteger(meta.index)) {
                                return pairState(state.doc, meta.index, Decoration, DecorationSet);
                            }
                            if (tr.docChanged) {
                                return pairState(state.doc, current.index, Decoration, DecorationSet);
                            }
                            return {
                                index: current.index,
                                decorations: current.decorations.map(tr.mapping, tr.doc),
                            };
                        },
                    },
                    props: {
                        decorations(state) {
                            return this.getState(state).decorations;
                        },
                    },
                }),
            ];
        },
    });

    const setPairedIndex = (editor, index) => {
        const tr = editor.state.tr.setMeta(pluginKey, { index }).setMeta('addToHistory', false);
        editor.view.dispatch(tr);
    };

    return { extension, setPairedIndex };
}

function pairState(doc, index, Decoration, DecorationSet) {
    return {
        index,
        decorations: decorationsFor(doc, index, Decoration, DecorationSet),
    };
}

function decorationsFor(doc, index, Decoration, DecorationSet) {
    if (index < 0 || index >= doc.childCount) {
        return DecorationSet.empty;
    }

    const pos = blockStart(doc, index);
    const node = doc.child(index);
    return DecorationSet.create(doc, [
        Decoration.node(pos, pos + node.nodeSize, { class: 'is-paired' }),
    ]);
}

export function bindPairing(panes) {
    let frame = 0;
    let pending = null;

    const flush = () => {
        frame = 0;
        const origin = pending;
        pending = null;
        if (!origin?.editor || !origin.setPairedIndex) {
            return;
        }

        const index = currentBlockIndex(origin.editor);
        origin.pairedIndex = index;
        syncing = true;

        for (const state of panes.values()) {
            if (state.editor && state.setPairedIndex) {
                state.setPairedIndex(state.editor, index);
            }
        }

        requestAnimationFrame(() => {
            alignPair(origin, panes);
            syncing = false;
        });
    };

    const syncFrom = (origin) => {
        if (syncing) {
            return;
        }
        pending = origin;
        if (!frame) {
            frame = requestAnimationFrame(flush);
        }
    };

    for (const state of panes.values()) {
        const editor = state.editor;
        if (!editor) {
            continue;
        }

        editor.on('selectionUpdate', () => {
            if (!syncing && editor.isFocused) {
                syncFrom(state);
            }
        });

        editor.on('update', ({ transaction }) => {
            if (!syncing && editor.isFocused && transaction.docChanged) {
                syncFrom(state);
            }
        });

        editor.on('focus', () => {
            if (!syncing) {
                syncFrom(state);
            }
        });
    }
}

export function currentBlockIndex(editor) {
    const { $from } = editor.state.selection;
    const last = Math.max(0, editor.state.doc.childCount - 1);
    return Math.min($from.index(0), last);
}

function blockStart(doc, index) {
    let pos = 0;
    for (let i = 0; i < index; i += 1) {
        pos += doc.child(i).nodeSize;
    }
    return pos;
}

function blockDom(editor, index) {
    if (index < 0 || index >= editor.state.doc.childCount) {
        return null;
    }
    return editor.view.nodeDOM(blockStart(editor.state.doc, index));
}

function alignPair(origin, panes) {
    const sourceBlock = blockDom(origin.editor, origin.pairedIndex);
    const sourceShell = origin.editor.view.dom.closest('.editor-shell');
    if (!sourceBlock || !sourceShell) {
        return;
    }

    const sourceTop = sourceBlock.getBoundingClientRect().top;

    for (const state of panes.values()) {
        if (state === origin) {
            continue;
        }

        const otherBlock = blockDom(state.editor, origin.pairedIndex);
        const otherShell = state.editor.view.dom.closest('.editor-shell');
        if (!otherBlock || !otherShell) {
            continue;
        }

        const delta = otherBlock.getBoundingClientRect().top - sourceTop;
        if (Math.abs(delta) < 1) {
            continue;
        }

        otherShell.scrollTop += delta;
    }
}
