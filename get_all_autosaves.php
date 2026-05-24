<?php
header('Content-Type: application/json');

$files = glob('autosave/autosave_*.json');

if (empty($files)) {
    echo json_encode(['success' => true, 'autosaves' => []]);
    exit;
}

$autosaves = [];

foreach ($files as $filepath) {
    $content = file_get_contents($filepath);
    $autosave = json_decode($content, true);
    
    if ($autosave) {
        $autosaves[] = [
            'id' => $autosave['id'],
            'title' => $autosave['title'],
            'timestamp' => $autosave['timestamp'],
            'date' => $autosave['date']
        ];
    }
}

// Сортируем по времени (последние первыми)
usort($autosaves, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

echo json_encode(['success' => true, 'autosaves' => $autosaves]);
