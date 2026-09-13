<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Everything meant to be tweaked lives here; no other file should need editing
 * to rename the panes, change languages or move the storage folder.
 */
return [
    'app_name' => 'Side By Side Editor',
    'app_short_name' => 'sbseditor',
    'credit_name' => 'Digital Ideas',
    'credit_url' => 'https://digitalideas.io',

    // How long the client waits after the last keystroke before saving
    // to this browser's localStorage.
    'autosave_delay_ms' => 900,

    // Largest HTML payload accepted for a single document.
    'max_document_bytes' => 512 * 1024,

    // Codes must be valid BCP 47 primary language subtags.
    'languages' => [
        'de' => 'German',
        'en' => 'English',
        'es' => 'Spanish',
        'it' => 'Italian',
        'fr' => 'French',
    ],

    // The panes, rendered left to right. Keys become localStorage ids and
    // must therefore match [A-Za-z0-9_-]+.
    'panes' => [
        'left' => [
            'label' => 'Left',
            'lang' => 'en',
            'placeholder' => 'Write this variation here…',
        ],
        'right' => [
            'label' => 'Right',
            'lang' => 'de',
            'placeholder' => 'Write this variation here…',
        ],
    ],
];
