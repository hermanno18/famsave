<?php
/**
 * Serves uploaded proof files after auth check.
 * Prevents direct access to /uploads/ directory.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$filename = basename($_GET['f'] ?? '');
if (!$filename) { http_response_code(400); exit; }

$path = UPLOAD_PATH . '/' . $filename;
if (!file_exists($path)) { http_response_code(404); exit; }

// Validate it's actually one of our files (no path traversal)
$real = realpath($path);
if (!$real || strpos($real, realpath(UPLOAD_PATH)) !== 0) {
    http_response_code(403); exit;
}

$mime = mime_content_type($real);
if (!in_array($mime, ['image/jpeg','image/png','image/webp','application/pdf'], true)) {
    http_response_code(403); exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=3600');
readfile($real);
