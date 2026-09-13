const MARK_ACTIONS = {
    bold: (editor) => editor.chain().focus().toggleBold().run(),
    italic: (editor) => editor.chain().focus().toggleItalic().run(),
    underline: (editor) => editor.chain().focus().toggleUnderline().run(),
    strike: (editor) => editor.chain().focus().toggleStrike().run(),
    code: (editor) => editor.chain().focus().toggleCode().run(),
};

export function bindToolbar(root, editor) {
    const buttons = [...root.querySelectorAll('[data-action]')];
    const headingSelect = root.querySelector('select[data-action="heading"]');
    const popover = root.querySelector('.link-popover');
    const hrefInput = popover?.querySelector('input[name="href"]');

    const run = (action) => {
        switch (action) {
            case 'undo':
                editor.chain().focus().undo().run();
                break;
            case 'redo':
                editor.chain().focus().redo().run();
                break;
            case 'bulletList':
                editor.chain().focus().toggleBulletList().run();
                break;
            case 'orderedList':
                editor.chain().focus().toggleOrderedList().run();
                break;
            case 'blockquote':
                editor.chain().focus().toggleBlockquote().run();
                break;
            case 'codeBlock':
                editor.chain().focus().toggleCodeBlock().run();
                break;
            case 'horizontalRule':
                editor.chain().focus().setHorizontalRule().run();
                break;
            case 'alignLeft':
                editor.chain().focus().setTextAlign('left').run();
                break;
            case 'alignCenter':
                editor.chain().focus().setTextAlign('center').run();
                break;
            case 'alignRight':
                editor.chain().focus().setTextAlign('right').run();
                break;
            case 'alignJustify':
                editor.chain().focus().setTextAlign('justify').run();
                break;
            case 'link':
                openLinkPopover();
                break;
            case 'unlink':
                editor.chain().focus().unsetLink().run();
                hideLinkPopover();
                break;
            default:
                MARK_ACTIONS[action]?.(editor);
        }
        sync();
    };

    const openLinkPopover = () => {
        if (!popover || !hrefInput) {
            return;
        }
        hrefInput.value = editor.getAttributes('link').href ?? '';
        popover.hidden = false;
        hrefInput.focus();
        hrefInput.select();
    };

    const hideLinkPopover = () => {
        if (popover) {
            popover.hidden = true;
        }
    };

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button || !root.contains(button) || button.tagName === 'SELECT') {
            return;
        }
        run(button.dataset.action);
    });

    headingSelect?.addEventListener('change', () => {
        const value = headingSelect.value;
        if (value === 'paragraph') {
            editor.chain().focus().setParagraph().run();
        } else {
            editor.chain().focus().setHeading({ level: Number(value) }).run();
        }
        sync();
    });

    popover?.addEventListener('submit', (event) => {
        event.preventDefault();
        const href = hrefInput.value.trim();
        if (!href) {
            editor.chain().focus().unsetLink().run();
        } else {
            editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        }
        hideLinkPopover();
        sync();
    });

    document.addEventListener('pointerdown', (event) => {
        if (popover && !popover.hidden && !root.contains(event.target)) {
            hideLinkPopover();
        }
    });

    const sync = () => {
        for (const button of buttons) {
            const action = button.dataset.action;
            let active = false;

            if (['bold', 'italic', 'underline', 'strike', 'code'].includes(action)) {
                active = editor.isActive(action);
            } else if (action === 'bulletList') {
                active = editor.isActive('bulletList');
            } else if (action === 'orderedList') {
                active = editor.isActive('orderedList');
            } else if (action === 'blockquote') {
                active = editor.isActive('blockquote');
            } else if (action === 'codeBlock') {
                active = editor.isActive('codeBlock');
            } else if (action === 'link') {
                active = editor.isActive('link');
            } else if (action === 'alignLeft') {
                active = editor.isActive({ textAlign: 'left' });
            } else if (action === 'alignCenter') {
                active = editor.isActive({ textAlign: 'center' });
            } else if (action === 'alignRight') {
                active = editor.isActive({ textAlign: 'right' });
            } else if (action === 'alignJustify') {
                active = editor.isActive({ textAlign: 'justify' });
            }

            button.classList.toggle('is-active', active);
            if (button.hasAttribute('aria-pressed') || button.tagName === 'BUTTON') {
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
        }

        if (headingSelect) {
            const heading = editor.getAttributes('heading');
            headingSelect.value = editor.isActive('heading') && heading.level
                ? String(heading.level)
                : 'paragraph';
        }
    };

    editor.on('transaction', sync);
    editor.on('selectionUpdate', sync);
    sync();

    return { sync };
}
