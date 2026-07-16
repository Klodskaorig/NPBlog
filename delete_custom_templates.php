<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

$templatesDir = getDataPath('blog/templates/');
$settingsFile = $templatesDir . 'settings.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['success' => true, 'message' => 'Настройки шаблонов отсутствуют, нечего удалять']);
    exit;
}

$settings = json_decode(@file_get_contents($settingsFile), true) ?: [];

if (!isset($settings['templates']) || !is_array($settings['templates'])) {
    echo json_encode(['success' => true]);
    exit;
}

if (!function_exists('rrmdir')) {
    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object))
                        rrmdir($dir . "/" . $object);
                    else
                        @unlink($dir . "/" . $object);
                }
            }
            @rmdir($dir);
        }
    }
}

$realTemplatesDir = realpath($templatesDir);

foreach ($settings['templates'] as $name => $meta) {
    if ($name === 'main' || !empty($meta['is_system'])) {
        continue;
    }
    
    $path = isset($meta['path']) ? $meta['path'] : '';
    unset($settings['templates'][$name]);
    
    // Remove from post template mappings
    if (isset($settings['post_templates'])) {
        foreach ($settings['post_templates'] as $postId => $tpl) {
            if ($tpl === $name) {
                unset($settings['post_templates'][$postId]);
            }
        }
    }
    
    if (!empty($path)) {
        $templateFile = $templatesDir . $path;
        $templateSubdir = dirname($templateFile);
        $realSubdir = realpath($templateSubdir);
        
        if ($realSubdir && $realTemplatesDir && strpos($realSubdir, $realTemplatesDir) === 0 && $realSubdir !== $realTemplatesDir) {
            rrmdir($templateSubdir);
        } else {
            if (file_exists($templateFile)) {
                @unlink($templateFile);
            }
        }
    } else {
        $templateFile = $templatesDir . $name . '.html';
        if (file_exists($templateFile)) {
            @unlink($templateFile);
        }
    }
}

// Reset default template to main
$settings['default'] = 'main';

@file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
@chmod($settingsFile, 0666);

echo json_encode(['success' => true]);
?>
