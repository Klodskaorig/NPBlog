<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['template_name']) || !isset($data['mode'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$templateName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['template_name']);
$mode = $data['mode']; // 'default' or 'post'

$templatesDir = getDataPath('blog/templates/');
$settingsFile = $templatesDir . 'settings.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['success' => false, 'error' => 'Настройки шаблонов не найдены']);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true) ?: [];

$templateFile = $templatesDir . $templateName . '.html';
if (!file_exists($templateFile)) {
    echo json_encode(['success' => false, 'error' => 'Файл шаблона не найден']);
    exit;
}

if ($mode === 'default') {
    // Make default and apply to all
    $settings['default'] = $templateName;
    
    // Clear all individual post template assignments so they fallback to the new default template
    $settings['post_templates'] = new stdClass();
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Regenerate all posts
    $metaFile = getDataPath('blog/posts-meta.json');
    $posts = [];
    if (file_exists($metaFile)) {
        $posts = json_decode(file_get_contents($metaFile), true) ?: [];
    }
    
    $successCount = 0;
    $failCount = 0;
    foreach ($posts as $post) {
        $id = $post['id'];
        if (regeneratePostWithTemplate($id, $templateFile)) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Шаблон успешно установлен по умолчанию и применен ко всем статьям. Успешно пересоздано: $successCount, ошибок: $failCount."
    ]);
    
} else if ($mode === 'post') {
    $postId = isset($data['post_id']) ? intval($data['post_id']) : null;
    if ($postId === null) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID статьи']);
        exit;
    }
    
    // Assign template to specific post
    if (!isset($settings['post_templates'])) {
        $settings['post_templates'] = [];
    }
    
    $settings['post_templates'][$postId] = $templateName;
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Regenerate that specific post
    if (regeneratePostWithTemplate($postId, $templateFile)) {
        echo json_encode(['success' => true, 'message' => 'Шаблон успешно применен к выбранной статье']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось пересобрать HTML файл статьи. Проверьте ее целостность.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неизвестный режим']);
}
?>
