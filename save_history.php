<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['history']) || !is_array($data['history'])) {
    echo json_encode(['success' => false, 'error' => 'Неверные данные']);
    exit;
}

$historyFile = 'history.json';

// Сохраняем историю в файл
$result = file_put_contents($historyFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if ($result === false) {
    echo json_encode(['success' => false, 'error' => 'Ошибка записи файла']);
} else {
    echo json_encode(['success' => true]);
}
