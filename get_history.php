<?php
header('Content-Type: application/json');

$historyFile = 'history.json';

if (!file_exists($historyFile)) {
    echo json_encode(['success' => true, 'history' => [], 'index' => -1]);
    exit;
}

$content = file_get_contents($historyFile);
$data = json_decode($content, true);

if ($data === null) {
    echo json_encode(['success' => true, 'history' => [], 'index' => -1]);
} else {
    echo json_encode(['success' => true, 'history' => $data['history'] ?? [], 'index' => $data['index'] ?? -1]);
}
