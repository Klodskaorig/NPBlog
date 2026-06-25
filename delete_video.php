<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Имя файла не указано']);
    exit;
}

$filename = basename($data['filename']); // Защита от path traversal
$videoDir = getDataPath('files/videos/');
$filePath = $videoDir . $filename;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'Файл не найден']);
    exit;
}

if (@unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении файла']);
}
?>
