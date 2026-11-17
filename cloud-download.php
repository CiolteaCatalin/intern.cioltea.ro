<?php
require __DIR__.'/includes/bootstrap.php';
require_login();
require_member($pdo);
if (!digistorage_configured($config)) { http_response_code(503); exit('Digi Storage nu este configurat.'); }
try {
    $relative = (string)($_GET['path'] ?? '');
    $isDir = str_ends_with(rawurldecode($relative), '/');
    $relative = digistorage_relative_path($relative, $isDir);
    if ($relative === '') throw new RuntimeException('Fișier invalid.');
    $link = digistorage_download_link($config, $relative, $isDir);
    header('Cache-Control: no-store');
    header('Location: ' . $link, true, 302);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    exit(e($e->getMessage()));
}
