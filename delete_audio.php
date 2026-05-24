<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Имя файла не указано']);
    exit;
}

$filename = basename($data['filename']); // Защита от path traversal
$audioDir = 'data/files/audio/';
$filePath = $audioDir . $filename;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'Файл не найден']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении файла']);
}
?>
