<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['name'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['name']);

if ($name === 'main') {
    echo json_encode(['success' => false, 'error' => 'Нельзя удалить главный системный шаблон']);
    exit;
}

$templatesDir = getDataPath('blog/templates/');
$settingsFile = $templatesDir . 'settings.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['success' => false, 'error' => 'Настройки шаблонов не найдены']);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true) ?: [];

if (($settings['default'] ?? 'main') === $name) {
    echo json_encode(['success' => false, 'error' => 'Нельзя удалить шаблон, который установлен по умолчанию']);
    exit;
}

// Remove from settings templates
if (isset($settings['templates'][$name])) {
    unset($settings['templates'][$name]);
}

// Remove from post template mappings (fallback to default)
if (isset($settings['post_templates'])) {
    foreach ($settings['post_templates'] as $postId => $tpl) {
        if ($tpl === $name) {
            unset($settings['post_templates'][$postId]);
        }
    }
}

@file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Delete HTML file
$templateFile = $templatesDir . $name . '.html';
if (file_exists($templateFile)) {
    @unlink($templateFile);
}

echo json_encode(['success' => true]);
?>
