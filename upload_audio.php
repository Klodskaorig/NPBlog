<?php
header('Content-Type: application/json');

if (!isset($_FILES['audio'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был загружен']);
    exit;
}

$file = $_FILES['audio'];
$uploadDir = 'data/files/audio/';

// Создаем директорию если её нет
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Проверяем тип файла
$allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла. Разрешены только аудио файлы.']);
    exit;
}

// Генерируем безопасное имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
// Удаляем только опасные символы, сохраняя кириллицу
$safeName = preg_replace('/[\/\\\:*?"<>|]/', '_', $baseName);
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
        'path' => '/' . $targetPath
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении файла']);
}
?>
