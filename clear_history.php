<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$historyFile = 'history.json';

// Очищаем файл истории
$result = file_put_contents($historyFile, json_encode(['history' => [], 'index' => -1], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if ($result === false) {
    echo json_encode(['success' => false, 'error' => 'Ошибка очистки файла']);
} else {
    echo json_encode(['success' => true]);
}
