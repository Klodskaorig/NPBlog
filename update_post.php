<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id']) || !isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$postId = $data['id'];

$allowedTags = '<b><i><u><s><sup><sub><h2><ul><li><a><p><br><img><pre><span><div><iframe><audio><source><center><details><summary><mark>';

$content = $data['content'];

// Функция для красивого форматирования HTML структуры
function formatArticleContent($html) {
    $blockTags = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'iframe', 'audio', 'center', 'details', 'summary', 'pre', 'blockquote', 'hr'];
    $tagsRegex = implode('|', $blockTags);
    
    $formatted = preg_replace('/(<(?:' . $tagsRegex . ')(?:\s+[^>]*)?>)/i', "\n$1", $html);
    $formatted = preg_replace('/(<\/(?:' . $tagsRegex . ')>)/i', "$1\n", $formatted);
    
    $lines = explode("\n", $formatted);
    $cleanLines = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        $cleanLines[] = "        " . $trimmed;
    }
    
    return "\n" . implode("\n", $cleanLines) . "\n    ";
}

$cleanContent = formatArticleContent($content);
$metaFile = 'data/blog/posts-meta.json';
if (!file_exists($metaFile)) {
    echo json_encode(['success' => false, 'error' => 'Метаданные не найдены']);
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true);
$postIndex = -1;

// Ищем статью по ID
foreach ($meta as $index => $item) {
    if ($item['id'] == $postId) {
        $postIndex = $index;
        break;
    }
}

if ($postIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Статья не найдена']);
    exit;
}

// Сохраняем оригинальную дату создания
$originalDate = $meta[$postIndex]['date'];
$currentDate = date('d.m.Y H:i');

// Обновляем HTML файл статьи (перенесено ниже с использованием шаблона)




// Получаем шаблон
$templateFile = 'data/blog/template_post.html';
if (!file_exists($templateFile)) {
    echo json_encode(['success' => false, 'error' => 'Файл шаблона template_post.html не найден']);
    exit;
}

$articleHtml = file_get_contents($templateFile);

// Подготавливаем данные
$title = htmlspecialchars($data['title']);
$displayDate = $originalDate . ' (отредактировано)';

// Добавляем пользовательские шрифты
require_once 'get_custom_fonts_css.php';
$customFontsCss = getCustomFontsCss();

// Заменяем плейсхолдеры в шаблоне
$articleHtml = str_replace('{{POST_ID}}', $postId, $articleHtml);
$articleHtml = str_replace('{{TITLE}}', $title, $articleHtml);
$articleHtml = str_replace('{{DATE}}', $displayDate, $articleHtml);
$articleHtml = str_replace('{{CONTENT}}', $cleanContent, $articleHtml);
$articleHtml = str_replace('{{CUSTOM_FONTS}}', $customFontsCss, $articleHtml);
$articleHtml = str_replace('{{BODY_STYLE}}', '', $articleHtml);
$articleHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $articleHtml);
$articleHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $articleHtml);

// Сохраняем обновленный файл
$filename = 'data/blog/' . $meta[$postIndex]['filename'];
file_put_contents($filename, $articleHtml);

// Создаем бэкап перед обновлением
$backupDir = 'data_backup/' . $postId . '/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Определяем следующий номер бэкапа
$existingBackups = glob($backupDir . $postId . '-*.html');
$maxBackupNumber = 0;
foreach ($existingBackups as $backup) {
    if (preg_match('/' . $postId . '-(\d+)\.html$/', $backup, $match)) {
        $backupNum = intval($match[1]);
        if ($backupNum > $maxBackupNumber) {
            $maxBackupNumber = $backupNum;
        }
    }
}
$nextBackupNumber = $maxBackupNumber + 1;

// Сохраняем бэкап
$backupFilename = $backupDir . $postId . '-' . $nextBackupNumber . '.html';
file_put_contents($backupFilename, $articleHtml);

// Сохраняем метаданные бэкапа
$backupMetaFile = 'data_backup/backup-meta.json';
$backupMeta = [];
if (file_exists($backupMetaFile)) {
    $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
}

if (!isset($backupMeta[$postId])) {
    $backupMeta[$postId] = [
        'postId' => $postId,
        'postTitle' => $data['title'],
        'backups' => []
    ];
}

$backupMeta[$postId]['postTitle'] = $data['title'];
$backupMeta[$postId]['backups'][] = [
    'backupNumber' => $nextBackupNumber,
    'filename' => $postId . '-' . $nextBackupNumber . '.html',
    'date' => $currentDate,
    'title' => $data['title']
];

file_put_contents($backupMetaFile, json_encode($backupMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Обновляем posts-meta.json (сохраняем оригинальную дату)
$meta[$postIndex]['title'] = $data['title'];
// Дату НЕ обновляем - она остается оригинальной
file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
?>
