<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

app_boot();

$config = app_config();
$panes = app_pane_bootstrap();
$appName = (string) ($config['app_name'] ?? 'Side By Side Editor');
$creditName = (string) ($config['credit_name'] ?? 'Digital Ideas');
$creditUrl = (string) ($config['credit_url'] ?? 'https://digitalideas.io');
$languages = app_languages();

$bootstrap = [
    'autosaveDelayMs' => (int) ($config['autosave_delay_ms'] ?? 900),
    'maxDocumentBytes' => (int) ($config['max_document_bytes'] ?? 512 * 1024),
    'languages' => $languages,
    'panes' => $panes,
];

$bootstrapJson = json_encode(
    $bootstrap,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
);

/**
 * @param array<string, mixed> $pane
 */
function render_toolbar(array $pane): void
{
    $id = htmlspecialchars((string) $pane['id'], ENT_QUOTES, 'UTF-8');
    ?>
    <div class="toolbar" data-toolbar="<?= $id ?>" role="toolbar" aria-label="Formatting">
        <div class="toolbar-group">
            <button type="button" data-action="undo" title="Undo (Ctrl+Z)" aria-label="Undo">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 8h8.2a4 4 0 1 1 0 8H10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M7 5 4 8l3 3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" data-action="redo" title="Redo (Ctrl+Shift+Z)" aria-label="Redo">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M16 8H7.8a4 4 0 1 0 0 8H10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M13 5l3 3-3 3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <label class="toolbar-select">
            <span class="sr-only">Block type</span>
            <select data-action="heading">
                <option value="paragraph">Paragraph</option>
                <option value="1">Heading 1</option>
                <option value="2">Heading 2</option>
                <option value="3">Heading 3</option>
            </select>
        </label>
        <div class="toolbar-group">
            <button type="button" data-action="bold" title="Bold (Ctrl+B)" aria-label="Bold"><strong>B</strong></button>
            <button type="button" data-action="italic" title="Italic (Ctrl+I)" aria-label="Italic"><em>I</em></button>
            <button type="button" data-action="underline" title="Underline (Ctrl+U)" aria-label="Underline"><span class="u">U</span></button>
            <button type="button" data-action="strike" title="Strikethrough" aria-label="Strikethrough"><s>S</s></button>
            <button type="button" data-action="code" title="Inline code" aria-label="Inline code">&lt;/&gt;</button>
        </div>
        <div class="toolbar-group">
            <button type="button" data-action="bulletList" title="Bullet list" aria-label="Bullet list">
                <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="6" r="1.3"/><circle cx="4" cy="10" r="1.3"/><circle cx="4" cy="14" r="1.3"/><path d="M8 6h8M8 10h8M8 14h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <button type="button" data-action="orderedList" title="Numbered list" aria-label="Numbered list">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3.4 4.8h1.8V8H3.8"/><path d="M3.3 11.2h2.4L3.3 14.6h2.5"/><path d="M8 6h8M8 10h8M8 14h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <button type="button" data-action="blockquote" title="Quote" aria-label="Quote">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 14.5 7.4 5h2.2L6.2 14.5H4Zm6.6 0L14 5h2.2l-3.4 9.5h-2.2Z"/></svg>
            </button>
            <button type="button" data-action="codeBlock" title="Code block" aria-label="Code block">{ }</button>
            <button type="button" data-action="horizontalRule" title="Horizontal rule" aria-label="Horizontal rule">―</button>
        </div>
        <div class="toolbar-group">
            <button type="button" data-action="alignLeft" title="Align left" aria-label="Align left">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M3 9h10M3 13h14M3 17h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <button type="button" data-action="alignCenter" title="Align center" aria-label="Align center">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M5 9h10M3 13h14M6 17h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <button type="button" data-action="alignRight" title="Align right" aria-label="Align right">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M7 9h10M3 13h14M9 17h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
            <button type="button" data-action="alignJustify" title="Justify" aria-label="Justify">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M3 9h14M3 13h14M3 17h14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
        </div>
        <div class="toolbar-group">
            <button type="button" data-action="link" title="Link" aria-label="Link">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M8.2 11.8a3.2 3.2 0 0 1 0-4.5l2-2a3.2 3.2 0 0 1 4.5 4.5l-1 1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M11.8 8.2a3.2 3.2 0 0 1 0 4.5l-2 2a3.2 3.2 0 1 1-4.5-4.5l1-1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
        </div>
        <form class="link-popover" hidden>
            <label>
                <span class="sr-only">URL</span>
                <input type="url" name="href" placeholder="https://" autocomplete="off">
            </label>
            <button type="submit">Apply</button>
            <button type="button" data-action="unlink">Remove</button>
        </form>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&display=swap">
</head>
<body>
    <div class="app">
        <header class="app-header">
            <div class="app-brand">
                <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="app-credit">
                    by <a href="<?= htmlspecialchars($creditUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($creditName, ENT_QUOTES, 'UTF-8') ?></a>
                </p>
            </div>
            <p class="app-status" data-app-status>Ready</p>
        </header>
        <main class="workspace" data-workspace>
            <?php foreach ($panes as $index => $pane): ?>
                <?php
                $id = htmlspecialchars((string) $pane['id'], ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars((string) $pane['label'], ENT_QUOTES, 'UTF-8');
                $lang = htmlspecialchars((string) $pane['lang'], ENT_QUOTES, 'UTF-8');
                $dir = htmlspecialchars((string) $pane['dir'], ENT_QUOTES, 'UTF-8');
                ?>
                <?php if ($index > 0): ?>
                    <div class="splitter" data-splitter role="separator" aria-orientation="vertical" aria-label="Resize editors" tabindex="0"></div>
                <?php endif; ?>
                <section class="pane pane-<?= $id ?>" data-pane="<?= $id ?>" lang="<?= $lang ?>" dir="<?= $dir ?>">
                    <header class="pane-header">
                        <div>
                            <h2><?= $label ?></h2>
                            <label class="pane-lang">
                                <span class="sr-only">Language</span>
                                <select data-lang="<?= $id ?>">
                                    <?php foreach ($languages as $code => $name): ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $pane['lang'] ? ' selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="pane-meta">
                            <p class="pane-status" data-status="<?= $id ?>">Saved</p>
                            <p class="pane-counts" data-counts="<?= $id ?>">0 words · 0 characters</p>
                        </div>
                    </header>
                    <?php render_toolbar($pane); ?>
                    <div class="editor-shell" data-editor="<?= $id ?>"></div>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
    <script id="app-bootstrap" type="application/json"><?= $bootstrapJson ?></script>
    <script type="module" src="assets/app.js?v=9"></script>
</body>
</html>
