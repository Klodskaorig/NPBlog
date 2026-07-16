<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

require_once 'background_functions.php';

if (!isset($_FILES['background']) || !isset($_POST['postId'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$postId = intval($_POST['postId']);
$file = $_FILES['background'];
$mode = isset($_POST['mode']) ? $_POST['mode'] : 'cover';
$scope = isset($_POST['scope']) ? $_POST['scope'] : 'content';

// Проверяем код ошибки загрузки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize в php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
        UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
    ];
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки фона (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// Проверяем режим отображения
$allowedModes = ['cover', 'contain', 'repeat'];
if (!in_array($mode, $allowedModes)) {
    $mode = 'cover';
}

// Проверяем область фона
$allowedScopes = ['content', 'fullpage'];
if (!in_array($scope, $allowedScopes)) {
    $scope = 'content';
}

// Проверяем тип файла
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}

// Создаем папку backgrounds если её нет
$backgroundsDir = getDataPath('backgrounds/');
if (!is_dir($backgroundsDir)) {
    if (!@mkdir($backgroundsDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для фонов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($backgroundsDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для фонов недоступна для записи.']);
    exit;
}

// Генерируем имя файла
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($extension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}

$filename = 'bg-' . $postId . '.' . $extension;
if (!preg_match('/^bg-\d+\.[a-z]+$/', $filename)) {
    echo json_encode(['success' => false, 'error' => 'Некорректное имя файла']);
    exit;
}
$filepath = validateSafePath($backgroundsDir, $filename);

// Удаляем старый фон если есть
$oldFiles = glob($backgroundsDir . 'bg-' . $postId . '.*');
if (is_array($oldFiles)) {
    foreach ($oldFiles as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
}

// Загружаем новый файл
if (@move_uploaded_file($file['tmp_name'], $filepath)) {
    // Сохраняем настройки в post_backgrounds.json
    $bgSettings = [
        'background' => $filename,
        'backgroundMode' => $mode,
        'backgroundScope' => $scope
    ];
    
    // Сохраняем настройки подложки если есть
    $existingSettings = getPostBackground($postId);
    if ($existingSettings) {
        if (isset($existingSettings['overlayEnabled'])) {
            $bgSettings['overlayEnabled'] = $existingSettings['overlayEnabled'];
        }
        if (isset($existingSettings['overlayColor'])) {
            $bgSettings['overlayColor'] = $existingSettings['overlayColor'];
        }
        if (isset($existingSettings['overlayOpacity'])) {
            $bgSettings['overlayOpacity'] = $existingSettings['overlayOpacity'];
        }
    }
    
    setPostBackground($postId, $bgSettings);
    
    // Применяем фон к HTML файлу статьи
    $metaFile = getDataPath('blog/posts-meta.json');
    if (file_exists($metaFile)) {
        $meta = json_decode(@file_get_contents($metaFile), true);
        if (is_array($meta)) {
            foreach ($meta as $post) {
                if ($post['id'] == $postId && isset($post['filename'])) {
                    $htmlFile = validateSafePath(getDataPath('blog/'), $post['filename']);
                    applyBackgroundToHtml($htmlFile, $bgSettings);
                    break;
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
}
?>
