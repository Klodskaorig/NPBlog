<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['filePath'])) {
        $filePath = validateSafePath(getDataPath('files/'), $data['filePath']);
        
        if (file_exists($filePath)) {
            if (@unlink($filePath)) {
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
