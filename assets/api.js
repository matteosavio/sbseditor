/**
 * Persist each pane in this browser only. HTML and language are stored as
 * separate localStorage strings so the document itself is never JSON.
 */

const PREFIX = 'sbseditor.';

export function createLocalStore(maxBytes = 512 * 1024) {
    const htmlKey = (pane) => `${PREFIX}${pane}.html`;
    const langKey = (pane) => `${PREFIX}${pane}.lang`;

    function read(pane) {
        try {
            const html = localStorage.getItem(htmlKey(pane));
            const lang = localStorage.getItem(langKey(pane));
            if (html === null && lang === null) {
                return null;
            }
            return {
                html: html ?? '',
                lang: lang ?? '',
            };
        } catch {
            return null;
        }
    }

    function write(pane, html, lang) {
        if (html.length > maxBytes) {
            throw new Error('Document is too large to save in this browser.');
        }

        try {
            localStorage.setItem(htmlKey(pane), html);
            localStorage.setItem(langKey(pane), lang);
        } catch {
            throw new Error('Could not save in this browser. Storage may be full or blocked.');
        }
    }

    return { read, write };
}
