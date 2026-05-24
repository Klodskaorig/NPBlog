<?php
header('Content-Type: application/json; charset=utf-8');

$uploadDir = 'data/files/';

if (!file_exists($uploadDir)) {
    echo json_encode(['success' => true, 'files' => []]);
    exit;
}

$files = [];
$items = scandir($uploadDir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    
    $filePath = $uploadDir . $item;
    
    if (is_file($filePath)) {
        $files[] = [
            'name' => $item,
            'size' => filesize($filePath),
            'path' => $filePath,
            'modified' => filemtime($filePath)
        ];
    }
}

// Сортируем по дате изменения (новые первыми)
usort($files, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

echo json_encode(['success' => true, 'files' => $files]);
?>
