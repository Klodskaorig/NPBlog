<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['title'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует заголовок']);
    exit;
}

$settingsFile = 'data/blog-view-settings.json';
$settings = [
    'title' => $data['title']
];

file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
?>
