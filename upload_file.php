<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не выбран']);
    exit;
}

$file = $_FILES['file'];
$uploadDir = 'data/files/';

// Создаем папку если не существует
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
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

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
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
