<?php

declare(strict_types=1);

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => false,
    'error' => 'Documents are stored in this browser, not on the server.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
