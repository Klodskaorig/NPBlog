<?php
require_once __DIR__ . '/security_bootstrap.php';
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

if (isset($data['smoothTyping'])) {
    $existingSettings['smoothTyping'] = (bool)$data['smoothTyping'];
}

if (isset($data['headerBottomPosition'])) {
    $existingSettings['headerBottomPosition'] = (bool)$data['headerBottomPosition'];
}

if (isset($data['contentWidth'])) {
    $existingSettings['contentWidth'] = (int)$data['contentWidth'];
}

if (isset($data['enableMarkdown'])) {
    $existingSettings['enableMarkdown'] = (bool)$data['enableMarkdown'];
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

if (isset($data['data_path'])) {
    $existingSettings['data_path'] = trim($data['data_path']);
}

if (isset($data['password_enabled'])) {
    $passwordEnabled = (bool)$data['password_enabled'];
    $hasOldPassword = !empty($existingSettings['password_hash']);
    
    if ($hasOldPassword) {
        $isChangingOrDisabling = (!$passwordEnabled) || !empty($data['new_password']);
        if ($isChangingOrDisabling) {
            $oldPassword = isset($data['old_password']) ? $data['old_password'] : '';
            if (empty($oldPassword) || !password_verify($oldPassword, $existingSettings['password_hash'])) {
                echo json_encode(['success' => false, 'error' => 'Неверный старый пароль']);
                exit;
            }
        }
    }
    
    if (!$passwordEnabled) {
        $existingSettings['password_hash'] = '';
        $existingSettings['failed_attempts'] = 0;
        $existingSettings['lockout_until'] = 0;
    } else {
        if (!empty($data['new_password'])) {
            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $existingSettings['password_hash'] = password_hash($data['new_password'], $algo);
            $existingSettings['failed_attempts'] = 0;
            $existingSettings['lockout_until'] = 0;
        } else if (empty($existingSettings['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Необходимо указать новый пароль для включения защиты']);
            exit;
        }
    }
}

// Сохраняем настройки
if (file_put_contents($settingsFile, json_encode($existingSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки']);
}
?>
