<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['name'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['name']);

if ($name === 'main') {
    echo json_encode(['success' => false, 'error' => 'Нельзя удалить главный системный шаблон']);
    exit;
}

$templatesDir = getDataPath('blog/templates/');
$settingsFile = $templatesDir . 'settings.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['success' => false, 'error' => 'Настройки шаблонов не найдены']);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true) ?: [];

if (($settings['default'] ?? 'main') === $name) {
    echo json_encode(['success' => false, 'error' => 'Нельзя удалить шаблон, который установлен по умолчанию']);
    exit;
}

$path = '';
if (isset($settings['templates'][$name])) {
    $path = isset($settings['templates'][$name]['path']) ? $settings['templates'][$name]['path'] : '';
    unset($settings['templates'][$name]);
}

// Remove from post template mappings (fallback to default)
if (isset($settings['post_templates'])) {
    foreach ($settings['post_templates'] as $postId => $tpl) {
        if ($tpl === $name) {
            unset($settings['post_templates'][$postId]);
        }
    }
}

@file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Delete template folder or file
if (!empty($path)) {
    $templateFile = validateSafePath($templatesDir, $path);
    $templateSubdir = dirname($templateFile);
    
    // Safety check: ensure subdirectory is inside $templatesDir and is not $templatesDir itself
    $realSubdir = realpath($templateSubdir);
    $realTemplatesDir = realpath($templatesDir);
    if ($realSubdir && $realTemplatesDir && strpos($realSubdir, $realTemplatesDir) === 0 && $realSubdir !== $realTemplatesDir) {
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
        rrmdir($templateSubdir);
    } else {
        if (file_exists($templateFile)) {
            @unlink($templateFile);
        }
    }
} else {
    $templateFile = validateSafePath($templatesDir, $name . '.html');
    if (file_exists($templateFile)) {
        @unlink($templateFile);
    }
}

echo json_encode(['success' => true]);
?>
