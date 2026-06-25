<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$audioDir = getDataPath('files/audio/');

if (!file_exists($audioDir)) {
    echo json_encode(['success' => true, 'files' => []]);
    exit;
}

$files = [];
$items = scandir($audioDir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    
    $filePath = $audioDir . $item;
    
    if (is_file($filePath)) {
        $files[] = [
            'name' => $item,
            'path' => getDataUrl('files/audio/' . $item),
            'size' => filesize($filePath)
        ];
    }
}

// Сортируем по дате изменения (новые первыми)
usort($files, function($a, $b) use ($audioDir) {
    return filemtime($audioDir . $b['name']) - filemtime($audioDir . $a['name']);
});

echo json_encode(['success' => true, 'files' => $files]);
?>
