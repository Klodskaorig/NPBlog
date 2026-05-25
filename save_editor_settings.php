<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$settingsFile = 'editor_settings.json';

// Загружаем существующие настройки
$existingSettings = [];
if (file_exists($settingsFile)) {
    $content = file_get_contents($settingsFile);
    $existingSettings = json_decode($content, true) ?: [];
}

// Обновляем настройки
if (isset($data['hideEditorModeButtons'])) {
    $existingSettings['hideEditorModeButtons'] = (bool)$data['hideEditorModeButtons'];
}

if (isset($data['amoledTheme'])) {
    $existingSettings['amoledTheme'] = (bool)$data['amoledTheme'];
}

if (isset($data['enableUndoRedo'])) {
    $existingSettings['enableUndoRedo'] = (bool)$data['enableUndoRedo'];
}

if (isset($data['autosaveEnabled'])) {
    $existingSettings['autosaveEnabled'] = (bool)$data['autosaveEnabled'];
}

if (isset($data['autosaveInterval'])) {
    $existingSettings['autosaveInterval'] = (int)$data['autosaveInterval'];
}

if (isset($data['tutorialCompleted'])) {
    $existingSettings['tutorialCompleted'] = (bool)$data['tutorialCompleted'];
}

// Сохраняем настройки
if (file_put_contents($settingsFile, json_encode($existingSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки']);
}
?>
