<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

if (!isset($_FILES['background'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует файл']);
    exit;
}

$file = $_FILES['background'];

// Проверяем код ошибки загрузки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize в php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
        UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
    ];
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки фона (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$mode = isset($_POST['mode']) ? $_POST['mode'] : 'cover';

// Проверяем режим отображения
$allowedModes = ['cover', 'contain', 'repeat'];
if (!in_array($mode, $allowedModes)) {
    $mode = 'cover';
}

// Проверяем тип файла
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}

// Создаем папку backgrounds если её нет
$backgroundsDir = getDataPath('backgrounds/');
if (!is_dir($backgroundsDir)) {
    if (!@mkdir($backgroundsDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для фонов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($backgroundsDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для фонов недоступна для записи.']);
    exit;
}

// Генерируем имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'blog-bg.' . $extension;
$filepath = $backgroundsDir . $filename;

// Удаляем старый фон если есть
$oldFiles = glob($backgroundsDir . 'blog-bg.*');
if (is_array($oldFiles)) {
    foreach ($oldFiles as $oldFile) {
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
    }
}

// Загружаем новый файл
if (@move_uploaded_file($file['tmp_name'], $filepath)) {
    // Читаем текущие настройки вида
    $settingsFile = getDataPath('blog-view-settings.json');
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(@file_get_contents($settingsFile), true);
        if (!is_array($settings)) {
            $settings = [];
        }
    }
    
    // Обновляем настройки фона
    $settings['background'] = $filename;
    $settings['backgroundMode'] = $mode;
    
    @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
}
?>
