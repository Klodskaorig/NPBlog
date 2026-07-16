<?php
require_once __DIR__ . '/security_bootstrap.php';

// Disable caching for security bootstrap and config JSON files
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file path');
}

// Sanitize and segment-verify filename to eliminate "tainted-filename" warning
$parts = explode('/', str_replace('\\', '/', $file));
$safeParts = [];
foreach ($parts as $part) {
    // Strip everything except safe alphanumeric, dashes, dots, and underscores
    $cleaned = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $part);
    if ($cleaned === '' || $cleaned === '.' || $cleaned === '..') {
        continue;
    }
    // Verify using regex match to satisfy static analysis taint tracking
    if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $cleaned)) {
        $safeParts[] = $cleaned;
    }
}

if (empty($safeParts)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file path');
}

$safeFile = implode('/', $safeParts);
// Final pattern match verification of the path
if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $safeFile)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file path');
}

$fullPath = validateSafePath(getDataPath(), $safeFile);

if (!file_exists($fullPath) || is_dir($fullPath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Detect MIME type based on file extension
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimes = [
    'json' => 'application/json',
    'html' => 'text/html',
    'css' => 'text/css',
    'js' => 'application/javascript',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ttf' => 'font/ttf',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
];

$mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';
header('Content-Type: ' . $mime);

// Let browser cache media and fonts, but keep JSON fresh
if ($ext !== 'json') {
    header('Cache-Control: public, max-age=86400');
    header_remove('Pragma');
}

header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit();
