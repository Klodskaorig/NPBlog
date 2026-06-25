<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$settingsFile = 'editor_settings.json';

if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    if (isset($settings['password_hash'])) {
        $settings['password_set'] = !empty($settings['password_hash']);
        unset($settings['password_hash']);
    } else {
        $settings['password_set'] = false;
    }
    echo json_encode(['success' => true, 'settings' => $settings]);
} else {
    // Возвращаем настройки по умолчанию
    echo json_encode([
        'success' => true, 
        'settings' => [
            'hideEditorModeButtons' => false,
            'amoledTheme' => false,
            'enableUndoRedo' => false,
            'smoothTyping' => false,
            'enableMarkdown' => false,
            'autosaveEnabled' => false,
            'autosaveInterval' => 60,
            'tutorialCompleted' => false,
            'contentWidth' => 920
        ]
    ]);
}
?>
