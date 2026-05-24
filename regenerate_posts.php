<?php
// Скрипт для регенерации старых статей (со старым HTML-кодом) в новый шаблонный формат
header('Content-Type: text/plain; charset=utf-8');

$blogDir = 'data/blog/';
$templateFile = 'data/blog/template_post.html';

if (!file_exists($templateFile)) {
    die("Ошибка: Файл шаблона $templateFile не найден.\n");
}

$templateHtml = file_get_contents($templateFile);

require_once 'get_custom_fonts_css.php';
$customFontsCss = getCustomFontsCss();

$files = glob($blogDir . 'post-*.html');
$convertedCount = 0;
$skippedCount = 0;

echo "Начинаем конвертацию старых статей...\n\n";

foreach ($files as $file) {
    if (!preg_match('/post-(\d+)\.html$/', $file, $match)) {
        continue;
    }
    
    $postId = intval($match[1]);
    $html = file_get_contents($file);
    
    // Проверяем, является ли статья уже шаблонной
    if (strpos($html, '<meta name="post-id"') !== false || strpos($html, 'assets/blog-post.css') !== false) {
        echo "Статья #$postId уже использует новый формат. Пропуск.\n";
        $skippedCount++;
        continue;
    }
    
    // Пытаемся извлечь данные из старой статьи
    // Извлекаем заголовок (h1)
    $title = "Без названия";
    if (preg_match('/<h1>(.*?)<\/h1>/s', $html, $titleMatch)) {
        $title = trim(strip_tags($titleMatch[1]));
    } else if (preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch)) {
        $title = trim(strip_tags($titleMatch[1]));
    }
    
    // Извлекаем дату
    $date = date('d.m.Y H:i');
    if (preg_match('/<div class="date">.*?(\d{2}\.\d{2}\.\d{4}\s\d{2}:\d{2}).*?<\/div>/s', $html, $dateMatch)) {
        $date = trim($dateMatch[1]);
        if (strpos($dateMatch[0], '(отредактировано)') !== false) {
            $date .= ' (отредактировано)';
        }
    }
    
    // Извлекаем контент
    $content = "";
    // У старых статей контент находится внутри <div class="content"> или <article class="content">
    if (preg_match('/<div class="content">(.*?)<\/div>\s*<a href="\.\.\/\.\.\/data\/blog\.html" class="back-link">/s', $html, $contentMatch)) {
        $content = trim($contentMatch[1]);
    } else if (preg_match('/<div class="content">(.*?)<\/div>\s*<a href/s', $html, $contentMatch)) {
        $content = trim($contentMatch[1]);
    } else {
        // Если не удалось извлечь контент стандартным способом
        echo "ПРЕДУПРЕЖДЕНИЕ: Не удалось извлечь контент из статьи #$postId. Пропуск.\n";
        $skippedCount++;
        continue;
    }
    
    // Создаем новый HTML на основе шаблона
    $newHtml = str_replace('{{POST_ID}}', $postId, $templateHtml);
    $newHtml = str_replace('{{TITLE}}', htmlspecialchars($title), $newHtml);
    $newHtml = str_replace('{{DATE}}', htmlspecialchars($date), $newHtml);
    $newHtml = str_replace('{{CONTENT}}', "\n        " . $content . "\n    ", $newHtml);
    $newHtml = str_replace('{{CUSTOM_FONTS}}', $customFontsCss, $newHtml);
    $newHtml = str_replace('{{BODY_STYLE}}', '', $newHtml);
    $newHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $newHtml);
    $newHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $newHtml);
    
    // Создаем резервную копию оригинала
    $backupPath = $file . '.legacy.bak';
    copy($file, $backupPath);
    
    // Сохраняем новую статью
    file_put_contents($file, $newHtml);
    
    echo "Статья #$postId успешно конвертирована (оригинал сохранен как .legacy.bak).\n";
    $convertedCount++;
}

echo "\nКонвертация завершена!\n";
echo "Конвертировано статей: $convertedCount\n";
echo "Пропущено: $skippedCount\n";
?>
