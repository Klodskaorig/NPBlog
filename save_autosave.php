<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$title = $_POST['title'] ?? null;
$content = $_POST['content'] ?? null;

if ($title === null || $content === null) {
    $input = file_get_contents('php://input');
    if ($input) {
        $data = json_decode($input, true);
        $title = $data['title'] ?? null;
        $content = $data['content'] ?? null;
    }
}

if ($title === null || $content === null) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют данные']);
    exit;
}

// Создаем папку autosave если её нет
if (!file_exists('autosave')) {
    mkdir('autosave', 0755, true);
}

// Генерируем уникальный ID на основе заголовка и времени
$titleSlug = preg_replace('/[^a-zA-Zа-яА-ЯёЁ0-9_-]/u', '_', $title);
if (function_exists('mb_substr')) {
    $titleSlug = mb_substr($titleSlug, 0, 50); // Ограничиваем длину
} else {
    $titleSlug = substr($titleSlug, 0, 50); // Фоллбэк
}
$timestamp = time();
$id = $titleSlug . '_' . $timestamp;

$filepath = 'autosave/autosave_' . $id . '.json';

$autosave = [
    'id' => $id,
    'title' => $title,
    'content' => $content,
    'timestamp' => $timestamp,
    'date' => date('Y-m-d H:i:s', $timestamp)
];

if (file_put_contents($filepath, json_encode($autosave, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    echo json_encode([
        'success' => true,
        'id' => $id,
        'timestamp' => $timestamp
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
}
