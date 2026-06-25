<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не выбран']);
    exit;
}

$file = $_FILES['file'];

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
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки файла (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$uploadDir = getDataPath('files/');

// Создаем папку если не существует
if (!file_exists($uploadDir)) {
    if (!@mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для файлов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для файлов недоступна для записи.']);
    exit;
}

// Проверяем размер файла (максимум 50MB)
$maxSize = 50 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Файл слишком большой (максимум 50MB)']);
    exit;
}

// Генерируем безопасное имя файла
$originalName = basename($file['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$baseName = pathinfo($originalName, PATHINFO_FILENAME);

// Очищаем имя файла от опасных символов
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.а-яА-ЯёЁ]/u', '_', $baseName);
$fileName = $safeName . '.' . $extension;

// Проверяем, существует ли файл с таким именем
$counter = 1;
while (file_exists($uploadDir . $fileName)) {
    $fileName = $safeName . '_' . $counter . '.' . $extension;
    $counter++;
}

$targetPath = $uploadDir . $fileName;

if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true,
        'filename' => $fileName,
        'originalName' => $originalName,
        'size' => $file['size'],
        'path' => $targetPath
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при загрузке файла']);
}
?>
