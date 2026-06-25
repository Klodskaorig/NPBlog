<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

if (empty($_FILES['image']) || empty($_FILES['image']['name'])) {
    echo json_encode(['success' => false, 'error' => 'Файлы не были загружены']);
    exit;
}

$uploadsDir = getDataPath('uploads');
if (!file_exists($uploadsDir)) {
    if (!@mkdir($uploadsDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для загрузок. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadsDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для загрузок недоступна для записи.']);
    exit;
}

$files = $_FILES['image'];
$gridLayout = $_POST['gridLayout'] ?? '';
$width = intval($_POST['width']);
$widthUnit = $_POST['widthUnit'] ?? 'px';

$allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
$uploadedUrls = [];

// Приводим к массивам для поддержки различных форматов
$names = is_array($files['name']) ? $files['name'] : [$files['name']];
$tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
$errors = is_array($files['error']) ? $files['error'] : [$files['error']];

foreach ($names as $i => $name) {
    $tmp_name = $tmpNames[$i];
    $error = $errors[$i] ?? UPLOAD_ERR_OK;
    
    if ($error !== UPLOAD_ERR_OK) continue;
    if (empty($tmp_name) || !is_uploaded_file($tmp_name)) continue;

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) continue;

    $newFileName = uniqid() . '.' . $ext;
    $uploadPath = getDataPath('uploads/') . $newFileName;

    if (@move_uploaded_file($tmp_name, $uploadPath)) {
        $uploadedUrls[] = getDataUrl('uploads/' . $newFileName);
    }
}

if (count($uploadedUrls) === 0) {
    echo json_encode(['success' => false, 'error' => 'Не удалось загрузить ни одно изображение']);
    exit;
}

echo json_encode([
    'success' => true,
    'urls' => $uploadedUrls,
    'gridLayout' => $gridLayout
]);
?>