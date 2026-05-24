<?php
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
    echo json_encode(['success' => false, 'error' => 'Произошла ошибка при загрузке: ' . $fileError]);
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
$uploadDir = 'data/fonts/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать директорию для шрифтов']);
        exit;
    }
}

// Перемещаем файл
$destination = $uploadDir . $fileName;
if (move_uploaded_file($fileTmpName, $destination)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл шрифта']);
}
?>
