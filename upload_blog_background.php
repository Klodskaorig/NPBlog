<?php
header('Content-Type: application/json');

if (!isset($_FILES['background'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует файл']);
    exit;
}

$file = $_FILES['background'];
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
$backgroundsDir = 'data/backgrounds/';
if (!is_dir($backgroundsDir)) {
    mkdir($backgroundsDir, 0755, true);
}

// Генерируем имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'blog-bg.' . $extension;
$filepath = $backgroundsDir . $filename;

// Удаляем старый фон если есть
$oldFiles = glob($backgroundsDir . 'blog-bg.*');
foreach ($oldFiles as $oldFile) {
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

// Загружаем новый файл
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Читаем текущие настройки вида
    $settingsFile = 'data/blog-view-settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (!is_array($settings)) {
            $settings = [];
        }
    }
    
    // Обновляем настройки фона
    $settings['background'] = $filename;
    $settings['backgroundMode'] = $mode;
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
}
?>
