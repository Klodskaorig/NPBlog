<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

// Удаляем файл глобального фона
$backgroundsDir = getDataPath('backgrounds/');
$files = glob($backgroundsDir . 'global-bg.*');
foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

// Обновляем глобальные настройки
$settingsFile = getDataPath('global-settings.json');
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    unset($settings['background']);
    unset($settings['backgroundMode']);
    unset($settings['backgroundScope']);
    
    if (empty($settings)) {
        unlink($settingsFile);
    } else {
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Очищаем opcode cache если включен
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($settingsFile, true);
}

// Удаляем фон из всех статей (только если у них нет своего фона)
$metaFile = getDataPath('blog/posts-meta.json');
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    
    foreach ($meta as $post) {
        // Удаляем только если у статьи нет своего фона
        if (!isset($post['background']) && isset($post['filename'])) {
            $htmlFile = getDataPath('blog/') . $post['filename'];
            if (file_exists($htmlFile)) {
                $html = file_get_contents($htmlFile);
                
                // Удаляем wrapper с фоном
                $html = preg_replace(
                    '/<div class="content-wrapper" style="[^"]*">(\s*<h1>.*<a href="(?:\.\.\/\.\.\/data\/|\.\.\/)blog\.html" class="back-link">.*?<\/a>)\s*<\/div>/s',
                    '$1',
                    $html
                );
                
                // Удаляем стиль body с фоном
                $html = preg_replace('/<body style="[^"]*">/', '<body>', $html);
                
                file_put_contents($htmlFile, $html);
            }
        }
    }
}

echo json_encode(['success' => true, 'deleted' => !file_exists($settingsFile)]);
?>
