<?php
header('Content-Type: application/json');

$videoDir = 'data/files/videos/';

if (!file_exists($videoDir)) {
    echo json_encode(['success' => true, 'files' => []]);
    exit;
}

$files = [];
$items = scandir($videoDir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    
    $filePath = $videoDir . $item;
    
    if (is_file($filePath)) {
        $files[] = [
            'name' => $item,
            'path' => '/' . $filePath,
            'size' => filesize($filePath)
        ];
    }
}

// Сортируем по дате изменения (новые первыми)
usort($files, function($a, $b) use ($videoDir) {
    return filemtime($videoDir . $b['name']) - filemtime($videoDir . $a['name']);
});

echo json_encode(['success' => true, 'files' => $files]);
?>
