<?php
header('Content-Type: application/json');

$uploadDir = 'data/files/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $targetPath = $uploadDir . $fileName;
        
        // Проверяем, существует ли файл
        $counter = 1;
        $fileInfo = pathinfo($fileName);
        while (file_exists($targetPath)) {
            $fileName = $fileInfo['filename'] . '_' . $counter . '.' . $fileInfo['extension'];
            $targetPath = $uploadDir . $fileName;
            $counter++;
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode([
                'success' => true,
                'fileName' => $fileName,
                'filePath' => $targetPath,
                'fileSize' => filesize($targetPath)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный запрос']);
}
?>
