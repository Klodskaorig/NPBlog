<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$setName = $_POST['setName'] ?? '';
$setName = preg_replace('/[^a-zA-Z0-9_\-\sА-Яа-яЁё]/u', '', $setName);
$setName = trim($setName);

if (empty($setName) || $setName === '.' || $setName === '..') {
    echo json_encode(['success' => false, 'error' => 'Неверное название набора']);
    exit;
}

$targetDir = validateSafePath(getDataPath('smiles/'), $setName);

if (is_dir($targetDir)) {
    // Удаляем все файлы внутри папки
    $files = glob($targetDir . '*');
    if (is_array($files)) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    // Удаляем директорию набора
    if (@rmdir($targetDir)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось удалить папку набора. Возможно, недостаточно прав или папка не пуста.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Набор не найден']);
}
?>
