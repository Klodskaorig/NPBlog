<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$allowedTags = '<b><i><u><s><sup><sub><h2><ul><li><a><p><br><img><pre><span><div><iframe><audio><source><center><details><summary><mark>';

$content = $data['content'];

// Функция для красивого форматирования HTML структуры с сохранением блоков <pre>
function formatArticleContent($html) {
    // 1. Извлекаем блоки <pre>, чтобы полностью сохранить их форматирование и пробелы
    $preBlocks = [];
    $formatted = preg_replace_callback('/(<pre[^>]*>[\s\S]*?<\/pre>)/i', function($matches) use (&$preBlocks) {
        $preBlocks[] = $matches[0];
        return '___PRE_PLACEHOLDER_' . (count($preBlocks) - 1) . '___';
    }, $html);

    $blockTags = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'iframe', 'audio', 'center', 'details', 'summary', 'blockquote', 'hr'];
    $tagsRegex = implode('|', $blockTags);
    
    $formatted = preg_replace('/(<(?:' . $tagsRegex . ')(?:\s+[^>]*)?>)/i', "\n$1", $formatted);
    $formatted = preg_replace('/(<\/(?:' . $tagsRegex . ')>)/i', "$1\n", $formatted);
    
    $lines = explode("\n", $formatted);
    $cleanLines = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        $cleanLines[] = "        " . $trimmed;
    }
    
    $finalHtml = "\n" . implode("\n", $cleanLines) . "\n    ";

    // 2. Восстанавливаем блоки <pre> без каких-либо изменений
    foreach ($preBlocks as $index => $preBlock) {
        $finalHtml = str_replace('___PRE_PLACEHOLDER_' . $index . '___', $preBlock, $finalHtml);
    }

    return $finalHtml;
}

$cleanContent = formatArticleContent($content);
$blogDir = 'data/blog/';
if (!is_dir($blogDir)) {
    mkdir($blogDir, 0755, true);
}

$maxId = 0;
$files = glob($blogDir . 'post-*.html');
foreach ($files as $file) {
    if (preg_match('/post-(\d+)\.html$/', $file, $match)) {
        $id = intval($match[1]);
        if ($id > $maxId) {
            $maxId = $id;
        }
    }
}

$nextId = $maxId + 1;
$date = date('d.m.Y H:i');

// Получаем шаблон
$templateFile = 'data/blog/template_post.html';
if (!file_exists($templateFile)) {
    echo json_encode(['success' => false, 'error' => 'Файл шаблона template_post.html не найден']);
    exit;
}

$articleHtml = file_get_contents($templateFile);

// Подготавливаем данные
$title = htmlspecialchars($data['title']);

// Добавляем пользовательские шрифты
require_once 'get_custom_fonts_css.php';
$customFontsCss = getCustomFontsCss();

// Заменяем плейсхолдеры в шаблоне
$articleHtml = str_replace('{{POST_ID}}', $nextId, $articleHtml);
$articleHtml = str_replace('{{TITLE}}', $title, $articleHtml);
$articleHtml = str_replace('{{DATE}}', $date, $articleHtml);
$articleHtml = str_replace('{{CONTENT}}', $cleanContent, $articleHtml);
$articleHtml = str_replace('{{CUSTOM_FONTS}}', $customFontsCss, $articleHtml);
$articleHtml = str_replace('{{BODY_STYLE}}', '', $articleHtml);
$articleHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $articleHtml);
$articleHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $articleHtml);

// Сохраняем файл статьи
$filename = $blogDir . 'post-' . $nextId . '.html';
file_put_contents($filename, $articleHtml);

// Создаем бэкап новой статьи
$backupDir = 'data_backup/' . $nextId . '/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupNumber = 1;
$backupFilename = $backupDir . $nextId . '-' . $backupNumber . '.html';
file_put_contents($backupFilename, $articleHtml);

// Сохраняем метаданные бэкапа
$backupMetaFile = 'data_backup/backup-meta.json';
$backupMeta = [];
if (file_exists($backupMetaFile)) {
    $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
}

if (!isset($backupMeta[$nextId])) {
    $backupMeta[$nextId] = [
        'postId' => $nextId,
        'postTitle' => $data['title'],
        'backups' => []
    ];
}

$backupMeta[$nextId]['backups'][] = [
    'backupNumber' => $backupNumber,
    'filename' => $nextId . '-' . $backupNumber . '.html',
    'date' => $date,
    'title' => $data['title']
];

file_put_contents($backupMetaFile, json_encode($backupMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Обновляем posts-meta.json для статического хостинга
$metaFile = $blogDir . 'posts-meta.json';
$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
}

$meta[] = [
    'id' => $nextId,
    'title' => $data['title'],
    'date' => $date,
    'filename' => 'post-' . $nextId . '.html'
];

file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true, 'id' => $nextId]);
?>
