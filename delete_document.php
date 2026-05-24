<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['filePath'])) {
        $filePath = $data['filePath'];
        
        if (file_exists($filePath) && strpos(realpath($filePath), realpath('data/files/')) === 0) {
            if (unlink($filePath)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось удалить файл']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Файл не найден']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Не указан путь к файлу']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный запрос']);
}
?>
