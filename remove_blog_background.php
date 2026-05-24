<?php
header('Content-Type: application/json');

$backgroundsDir = 'data/backgrounds/';

// Удаляем фоновые файлы блога
$oldFiles = glob($backgroundsDir . 'blog-bg.*');
foreach ($oldFiles as $oldFile) {
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

// Читаем текущие настройки вида
$settingsFile = 'data/blog-view-settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!is_array($settings)) {
        $settings = [];
    }
}

// Удаляем настройки фона
if (isset($settings['background'])) {
    unset($settings['background']);
}
if (isset($settings['backgroundMode'])) {
    unset($settings['backgroundMode']);
}

file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
?>
