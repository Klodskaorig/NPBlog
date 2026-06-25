<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указан файл']);
    exit;
}

$filename = basename($data['filename']);
$filepath = 'draft/' . $filename;

if (file_exists($filepath)) {
    if (unlink($filepath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка удаления файла']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Файл не найден']);
}
