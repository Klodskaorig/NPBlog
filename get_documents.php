<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$uploadDir = getDataPath('files/');
$files = [];

if (is_dir($uploadDir)) {
    $items = scandir($uploadDir);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..') {
            $filePath = $uploadDir . $item;
            if (is_file($filePath)) {
                $files[] = [
                    'name' => $item,
                    'path' => $filePath,
                    'url' => getDataUrl('files/' . $item),
                    'size' => filesize($filePath),
                    'mtime' => filemtime($filePath)
                ];
            }
        }
    }
    
    usort($files, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
}

echo json_encode(['success' => true, 'files' => $files]);
?>
