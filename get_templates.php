<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

initTemplatesSystem();

$templatesDir = getDataPath('blog/templates/');
$settingsFile = $templatesDir . 'settings.json';

$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
}

$responseTemplates = [];
if (isset($settings['templates']) && is_array($settings['templates'])) {
    foreach ($settings['templates'] as $name => $meta) {
        $path = isset($meta['path']) ? $meta['path'] : '';
        if (empty($path)) {
            if ($name === 'main') {
                $path = 'NPBlog/main.html';
            } else {
                $path = $name . '.html';
            }
        }
        $templateFile = $templatesDir . $path;
        $code = '';
        if (file_exists($templateFile)) {
            $code = @file_get_contents($templateFile) ?: '';
        }
        
        $responseTemplates[] = [
            'name' => $name,
            'title' => $meta['title'],
            'description' => $meta['description'] ?? '',
            'is_system' => $meta['is_system'] ?? false,
            'code' => $code
        ];
    }
}

// Load post metadata for template assignment
$metaFile = getDataPath('blog/posts-meta.json');
$posts = [];
if (file_exists($metaFile)) {
    $posts = json_decode(file_get_contents($metaFile), true) ?: [];
}

echo json_encode([
    'success' => true,
    'templates' => $responseTemplates,
    'default' => $settings['default'] ?? 'main',
    'post_templates' => $settings['post_templates'] ?? new stdClass(),
    'posts' => $posts
]);
?>
