<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

if (!isset($_FILES['template_file'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был передан']);
    exit;
}

$uploadedFile = $_FILES['template_file'];

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize в php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
        UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
    ];
    $errorMsg = isset($uploadErrors[$uploadedFile['error']]) ? $uploadErrors[$uploadedFile['error']] : 'Ошибка загрузки шаблона (' . $uploadedFile['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$filename = $uploadedFile['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if ($ext !== 'html' && $ext !== 'htm') {
    echo json_encode(['success' => false, 'error' => 'Разрешено загружать только файлы .html']);
    exit;
}

$code = @file_get_contents($uploadedFile['tmp_name']);
if ($code === false) {
    echo json_encode(['success' => false, 'error' => 'Не удалось прочитать загруженный файл']);
    exit;
}

// Validate placeholders
$missingPlaceholders = validateTemplateCode($code);
if (!empty($missingPlaceholders)) {
    echo json_encode([
        'success' => false,
        'error' => 'В загружаемом шаблоне отсутствуют необходимые плейсхолдеры: ' . implode(', ', $missingPlaceholders),
        'missing' => $missingPlaceholders
    ]);
    exit;
}

// Generate unique name
$baseName = pathinfo($filename, PATHINFO_FILENAME);
$cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $baseName);
if (empty($cleanName)) {
    $cleanName = 'custom_' . time();
}

$templatesDir = getDataPath('blog/templates/');

if (!is_dir($templatesDir)) {
    if (!@mkdir($templatesDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для шаблонов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($templatesDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для шаблонов недоступна для записи.']);
    exit;
}

initTemplatesSystem();

// Avoid overwriting system templates
if ($cleanName === 'main') {
    $cleanName = 'main_' . time();
}

// Ensure unique filename
$finalName = $cleanName;
$counter = 1;
while (file_exists($templatesDir . $finalName . '.html')) {
    $finalName = $cleanName . '_' . $counter;
    $counter++;
}

// Save HTML file
$destFile = $templatesDir . $finalName . '.html';
if (!@move_uploaded_file($uploadedFile['tmp_name'], $destFile)) {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл шаблона']);
    exit;
}
@chmod($destFile, 0666);

// Update settings
$settingsFile = $templatesDir . 'settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
}

if (!isset($settings['templates'])) {
    $settings['templates'] = [];
}

$settings['templates'][$finalName] = [
    'title' => htmlspecialchars($baseName),
    'description' => 'Пользовательский шаблон, загруженный ' . date('d.m.Y H:i'),
    'is_system' => false
];

if (@file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки шаблонов']);
    exit;
}
@chmod($settingsFile, 0666);

echo json_encode(['success' => true, 'name' => $finalName]);
?>
