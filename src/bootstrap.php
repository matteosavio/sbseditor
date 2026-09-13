<?php

declare(strict_types=1);

require_once __DIR__ . '/Sanitizer.php';
require_once __DIR__ . '/DocumentStore.php';

/**
 * @return array<string, mixed>
 */
function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $loaded = require dirname(__DIR__) . '/config.php';
        if (!is_array($loaded)) {
            throw new RuntimeException('config.php must return an array.');
        }
        $config = $loaded;
    }

    return $config;
}

function app_boot(): void
{
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $secure,
        'use_strict_mode' => true,
    ]);

    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function app_store(): DocumentStore
{
    $config = app_config();
    $panes = $config['panes'] ?? [];

    if (!is_array($panes) || $panes === []) {
        throw new RuntimeException('At least one pane must be configured.');
    }

    return new DocumentStore(
        (string) $config['storage_dir'],
        $panes,
        app_languages(),
        (int) $config['max_document_bytes']
    );
}

/**
 * @return array<string, string>
 */
function app_languages(): array
{
    $languages = app_config()['languages'] ?? [];
    if (!is_array($languages) || $languages === []) {
        throw new RuntimeException('At least one language must be configured.');
    }

    $clean = [];
    foreach ($languages as $code => $label) {
        if (!is_string($code) || preg_match('/^[a-z]{2}$/', $code) !== 1 || !is_string($label) || $label === '') {
            continue;
        }
        $clean[$code] = $label;
    }

    if ($clean === []) {
        throw new RuntimeException('No valid languages are configured.');
    }

    return $clean;
}

function app_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function app_json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_csrf_ok(?string $token): bool
{
    $expected = $_SESSION['csrf'] ?? '';
    return is_string($expected)
        && $expected !== ''
        && is_string($token)
        && hash_equals($expected, $token);
}

/**
 * @return list<array<string, mixed>>
 */
function app_pane_bootstrap(): array
{
    $config = app_config();
    $panes = [];

    foreach ($config['panes'] as $id => $pane) {
        $panes[] = [
            'id' => $id,
            'label' => (string) ($pane['label'] ?? $id),
            'lang' => (string) ($pane['lang'] ?? 'en'),
            'dir' => (string) ($pane['dir'] ?? 'ltr'),
            'placeholder' => (string) ($pane['placeholder'] ?? ''),
            'html' => '',
        ];
    }

    return $panes;
}
