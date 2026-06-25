<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['fontFile'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был передан']);
    exit;
}

$file = $_FILES['fontFile'];
$fileName = basename($file['name']);
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];

if ($fileError !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize в php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
        UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
    ];
    $errorMsg = isset($uploadErrors[$fileError]) ? $uploadErrors[$fileError] : 'Ошибка загрузки шрифта (' . $fileError . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// Проверка расширения файла
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExts = ['ttf', 'otf', 'woff', 'woff2'];

if (!in_array($fileExt, $allowedExts)) {
    echo json_encode(['success' => false, 'error' => 'Неверный формат файла. Разрешены только ttf, otf, woff, woff2']);
    exit;
}

// Создаем папку data/fonts/ если её нет
$uploadDir = getDataPath('fonts/');
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать директорию для шрифтов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для шрифтов недоступна для записи.']);
    exit;
}

// Перемещаем файл
$destination = $uploadDir . $fileName;
if (@move_uploaded_file($fileTmpName, $destination)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл шрифта']);
}
?>
