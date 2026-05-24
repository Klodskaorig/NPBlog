<?php
header('Content-Type: application/json; charset=utf-8');

$settingsFile = 'editor_settings.json';

if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    echo json_encode(['success' => true, 'settings' => $settings]);
} else {
    // Возвращаем настройки по умолчанию
    echo json_encode([
        'success' => true, 
        'settings' => [
            'hideEditorModeButtons' => false,
            'enableUndoRedo' => false,
            'autosaveEnabled' => false,
            'autosaveInterval' => 60,
            'tutorialCompleted' => false
        ]
    ]);
}
?>
