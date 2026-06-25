<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['enabled']) || !isset($data['interval'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют данные']);
    exit;
}

$settings = [
    'enabled' => (bool)$data['enabled'],
    'interval' => (int)$data['interval']
];

$settingsFile = 'autosave-settings.json';

if (file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения настроек']);
}
