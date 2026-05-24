<?php
header('Content-Type: application/json');

$uploadDir = 'data/files/';
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
                    'size' => filesize($filePath)
                ];
            }
        }
    }
}

echo json_encode(['success' => true, 'files' => $files]);
?>
