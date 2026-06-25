<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

require_once 'background_functions.php';

if (!isset($_FILES['background'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует файл']);
    exit;
}

$file = $_FILES['background'];

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
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки глобального фона (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$mode = isset($_POST['mode']) ? $_POST['mode'] : 'cover';
$scope = isset($_POST['scope']) ? $_POST['scope'] : 'content';

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

// Gенерируем имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'global-bg.' . $extension;
$filepath = $backgroundsDir . $filename;

// Удаляем старый глобальный фон если есть
$oldFiles = glob($backgroundsDir . 'global-bg.*');
if (is_array($oldFiles)) {
    foreach ($oldFiles as $oldFile) {
        @unlink($oldFile);
    }
}

// Загружаем новый файл
if (@move_uploaded_file($file['tmp_name'], $filepath)) {
    // Сохраняем глобальные настройки
    $settingsFile = getDataPath('global-settings.json');
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
    }
    $settings['background'] = $filename;
    $settings['backgroundMode'] = $mode;
    $settings['backgroundScope'] = $scope;
    @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Удаляем все индивидуальные фоны статей из post_backgrounds.json
    $backgrounds = loadBackgrounds();
    $newBackgrounds = [];
    
    if (is_array($backgrounds)) {
        foreach ($backgrounds as $postId => $bgSettings) {
            // Удаляем файлы индивидуальных фонов
            if (isset($bgSettings['background'])) {
                $bgFile = getDataPath('backgrounds/') . $bgSettings['background'];
                if (file_exists($bgFile) && strpos($bgSettings['background'], 'bg-') === 0) {
                    @unlink($bgFile);
                }
            }
            
            // Сохраняем только настройки подложки если они есть
            $newSettings = [];
            if (isset($bgSettings['overlayEnabled'])) {
                $newSettings['overlayEnabled'] = $bgSettings['overlayEnabled'];
            }
            if (isset($bgSettings['overlayColor'])) {
                $newSettings['overlayColor'] = $bgSettings['overlayColor'];
            }
            if (isset($bgSettings['overlayOpacity'])) {
                $newSettings['overlayOpacity'] = $bgSettings['overlayOpacity'];
            }
            
            if (!empty($newSettings)) {
                $newBackgrounds[$postId] = $newSettings;
            }
        }
    }
    
    // Сохраняем очищенные настройки
    saveBackgrounds($newBackgrounds);
    
    // Применяем глобальный фон ко всем статьям
    $metaFile = getDataPath('blog/posts-meta.json');
    if (file_exists($metaFile)) {
        $meta = json_decode(@file_get_contents($metaFile), true);
        
        if (is_array($meta)) {
            foreach ($meta as $post) {
                if (isset($post['filename'])) {
                    $htmlFile = getDataPath('blog/') . $post['filename'];
                    if (file_exists($htmlFile)) {
                        // Получаем настройки подложки если есть
                        $postBgSettings = isset($newBackgrounds[$post['id']]) ? $newBackgrounds[$post['id']] : [];
                        
                        // Добавляем глобальный фон
                        $postBgSettings['background'] = $filename;
                        $postBgSettings['backgroundMode'] = $mode;
                        $postBgSettings['backgroundScope'] = $scope;
                        
                        applyBackgroundToHtml($htmlFile, $postBgSettings);
                    }
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
}
?>
