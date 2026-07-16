<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$setName = $_POST['setName'] ?? '';
// Разрешаем буквы, цифры, дефис, подчеркивание, пробелы и кириллицу
$setName = preg_replace('/[^a-zA-Z0-9_\-\sА-Яа-яЁё]/u', '', $setName);
$setName = trim($setName);

if (empty($setName) || $setName === '.' || $setName === '..') {
    echo json_encode(['success' => false, 'error' => 'Неверное название набора']);
    exit;
}

$smilesBaseDir = getDataPath('smiles/');
if (!file_exists($smilesBaseDir)) {
    if (!@mkdir($smilesBaseDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать директорию для смайлов: ' . $smilesBaseDir]);
        exit;
    }
}

if (!is_writable($smilesBaseDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория смайлов не доступна для записи: ' . $smilesBaseDir]);
    exit;
}

$targetDir = $smilesBaseDir . $setName . '/';
if (!file_exists($targetDir)) {
    if (!@mkdir($targetDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для набора: ' . $setName]);
        exit;
    }
}

if (!is_writable($targetDir)) {
    echo json_encode(['success' => false, 'error' => 'Папка набора смайлов не доступна для записи: ' . $setName]);
    exit;
}

if (empty($_FILES['smiles']) || empty($_FILES['smiles']['name'])) {
    echo json_encode(['success' => false, 'error' => 'Файлы не были загружены']);
    exit;
}

$files = $_FILES['smiles'];
$uploadedCount = 0;

// Приводим все параметры $_FILES к массивам, чтобы поддержать как единичные файлы, так и массивы
$names = is_array($files['name']) ? $files['name'] : [$files['name']];
$tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
$errors = is_array($files['error']) ? $files['error'] : [$files['error']];

foreach ($names as $i => $name) {
    $tmpName = $tmpNames[$i];
    $error = $errors[$i] ?? UPLOAD_ERR_OK;
    
    if ($error !== UPLOAD_ERR_OK) continue;
    if (empty($tmpName) || !is_uploaded_file($tmpName)) continue;

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'gif') continue;

    // Очищаем имя файла от вредоносных символов
    $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($name, PATHINFO_FILENAME));
    if (empty($baseName)) {
        $baseName = 'smile';
    }
    
    $safeName = $baseName . '.gif';
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.gif$/', $safeName)) {
        continue;
    }
    $destPath = validateSafePath($targetDir, $safeName);
    
    $counter = 1;
    while (file_exists($destPath)) {
        $candidateName = $baseName . '_' . $counter . '.gif';
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.gif$/', $candidateName)) {
            break;
        }
        $destPath = validateSafePath($targetDir, $candidateName);
        $counter++;
    }

    if (@move_uploaded_file($tmpName, $destPath)) {
        $uploadedCount++;
    }
}

if ($uploadedCount === 0) {
    echo json_encode(['success' => false, 'error' => 'Не удалось загрузить ни одного GIF-смайла']);
    exit;
}

echo json_encode(['success' => true, 'count' => $uploadedCount]);
?>
