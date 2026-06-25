<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют данные']);
    exit;
}

$title = $data['title'];
$content = $data['content'];

// Создаем папку draft если её нет
if (!file_exists('draft')) {
    mkdir('draft', 0755, true);
}

// Генерируем уникальное имя файла
$timestamp = time();
$filename = $timestamp . '.json';
$filepath = 'draft/' . $filename;

// Сохраняем черновик
$draft = [
    'title' => $title,
    'content' => $content,
    'timestamp' => $timestamp,
    'date' => date('Y-m-d H:i:s', $timestamp)
];

if (file_put_contents($filepath, json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'timestamp' => $timestamp
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
}
