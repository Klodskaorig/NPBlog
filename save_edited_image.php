<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

$imageData = $_POST['image_data'] ?? '';
if (empty($imageData)) {
    echo json_encode(['success' => false, 'error' => 'Данные изображения пусты']);
    exit;
}

// Извлекаем тип и base64
if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
    $imageData = substr($imageData, strpos($imageData, ',') + 1);
    $type = strtolower($type[1]); // jpeg, png, etc

    if ($type === 'jpeg') {
        $type = 'jpg';
    }

    if (!in_array($type, ['jpg', 'png', 'gif'])) {
        echo json_encode(['success' => false, 'error' => 'Недопустимый формат изображения']);
        exit;
    }

    $imageData = base64_decode($imageData);
    if ($imageData === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка декодирования base64']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный формат данных']);
    exit;
}

if (!file_exists('data/uploads')) {
    mkdir('data/uploads', 0777, true);
}

$newFileName = 'edit_' . uniqid() . '.' . $type;
$uploadPath = 'data/uploads/' . $newFileName;

if (file_put_contents($uploadPath, $imageData)) {
    echo json_encode([
        'success' => true,
        'url' => '/data/uploads/' . $newFileName
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл на сервере']);
}
