<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['name']) || !isset($data['title']) || !isset($data['code'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['name']);
$title = htmlspecialchars($data['title']);
$description = htmlspecialchars($data['description'] ?? '');
$code = $data['code'];

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Неверное имя шаблона']);
    exit;
}

// Validate placeholders
$missingPlaceholders = validateTemplateCode($code);
if (!empty($missingPlaceholders)) {
    echo json_encode([
        'success' => false,
        'error' => 'В шаблоне отсутствуют необходимые плейсхолдеры: ' . implode(', ', $missingPlaceholders),
        'missing' => $missingPlaceholders
    ]);
    exit;
}

$templatesDir = getDataPath('blog/templates/');
if (!is_dir($templatesDir)) {
    if (!@mkdir($templatesDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для шаблонов. Проверьте права доступа.']);
        exit;
    }
    @chmod($templatesDir, 0777);
}

if (!is_writable($templatesDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для шаблонов недоступна для записи.']);
    exit;
}

$templateFile = $templatesDir . $name . '.html';
if (@file_put_contents($templateFile, $code) === false) {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл шаблона']);
    exit;
}
@chmod($templateFile, 0666);

$settingsFile = $templatesDir . 'settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
}

if (!isset($settings['templates'])) {
    $settings['templates'] = [];
}

// Keep is_system flag if already exists
$isSystem = isset($settings['templates'][$name]['is_system']) ? $settings['templates'][$name]['is_system'] : false;

$settings['templates'][$name] = [
    'title' => $title,
    'description' => $description,
    'is_system' => $isSystem
];

if (@file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки шаблонов']);
    exit;
}
@chmod($settingsFile, 0666);

echo json_encode(['success' => true]);
?>
