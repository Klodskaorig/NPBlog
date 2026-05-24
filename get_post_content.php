<?php
// Отключаем вывод ошибок в браузер
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        echo json_encode(['success' => false, 'error' => 'Пустой запрос']);
        exit;
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Ошибка парсинга JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID статьи не указан']);
        exit;
    }
    
    $postId = $data['id'];

    // Загружаем метаданные
    $metaFile = 'data/blog/posts-meta.json';
    if (!file_exists($metaFile)) {
        echo json_encode(['success' => false, 'error' => 'Метаданные не найдены']);
        exit;
    }

    $meta = json_decode(file_get_contents($metaFile), true);
    
    if (!$meta) {
        echo json_encode(['success' => false, 'error' => 'Ошибка чтения метаданных']);
        exit;
    }
    
    $post = null;

    // Ищем статью по ID
    foreach ($meta as $item) {
        if ($item['id'] == $postId) {
            $post = $item;
            break;
        }
    }

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Статья не найдена']);
        exit;
    }

    // Читаем файл статьи
    $filename = 'data/blog/' . $post['filename'];
    if (!file_exists($filename)) {
        echo json_encode(['success' => false, 'error' => 'Файл статьи не найден: ' . $filename]);
        exit;
    }

    $content = file_get_contents($filename);

    // Парсим HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Извлекаем заголовок
    $titleNode = $xpath->query('//h1')->item(0);
    $title = $titleNode ? $titleNode->textContent : '';

    // Извлекаем контент
    // Сначала пробуем новый формат (<article id="npblog-post-content">)
    $contentNode = $xpath->query('//article[@id="npblog-post-content"]')->item(0);
    
    // Если не найдено, пробуем старый формат (<div class="content">)
    if (!$contentNode) {
        $contentNode = $xpath->query('//div[@class="content"]')->item(0);
    }
    
    $rawContent = '';
    if ($contentNode) {
        foreach ($contentNode->childNodes as $child) {
            $rawContent .= $dom->saveHTML($child);
        }
    }

    echo json_encode([
        'success' => true,
        'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
        'content' => html_entity_decode($rawContent, ENT_QUOTES, 'UTF-8')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Исключение: ' . $e->getMessage()]);
}