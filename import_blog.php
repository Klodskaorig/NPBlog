<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// ==========================================
// BLOGGER IMPORT
// ==========================================

function fetchBloggerPosts($blogId, $apiKey, $maxResults = 500) {
    $url = "https://www.googleapis.com/blogger/v3/blogs/{$blogId}/posts";
    $params = [
        'key' => $apiKey,
        'maxResults' => $maxResults,
        'fetchBodies' => 'true',
        'fetchImages' => 'false',
        'status' => 'live'
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL Error: {$error}"];
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = isset($errorData['error']['message']) ? $errorData['error']['message'] : "HTTP Error: {$httpCode}";
        return ['success' => false, 'error' => $errorMsg];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['items'])) {
        return ['success' => false, 'error' => 'Не удалось получить записи блога'];
    }
    
    $posts = [];
    foreach ($data['items'] as $item) {
        $posts[] = [
            'id' => $item['id'],
            'title' => $item['title'],
            'content' => $item['content'],
            'published' => $item['published'],
            'updated' => $item['updated'],
            'url' => $item['url'],
            'author' => isset($item['author']['displayName']) ? $item['author']['displayName'] : ''
        ];
    }
    
    return ['success' => true, 'posts' => $posts, 'total' => count($posts)];
}

// ==========================================
// WORDPRESS IMPORT (via REST API)
// ==========================================

function fetchWordpressPosts($siteUrl, $perPage = 100, $page = 1) {
    // Normalize URL
    $siteUrl = rtrim($siteUrl, '/');
    
    // Try to discover REST API endpoint
    $apiUrl = "{$siteUrl}/wp-json/wp/v2/posts";
    
    $params = [
        'per_page' => $perPage,
        'page' => $page,
        'status' => 'publish',
        '_embed' => 'true'
    ];
    
    $fullUrl = $apiUrl . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL Error: {$error}"];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP Error: {$httpCode}. Проверьте URL сайта."];
    }
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    $data = json_decode($body, true);
    
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Не удалось получить записи блога'];
    }
    
    // Parse total pages from headers
    $totalPages = 1;
    if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $headers, $matches)) {
        $totalPages = (int)$matches[1];
    }
    
    $totalPosts = 0;
    if (preg_match('/X-WP-Total:\s*(\d+)/i', $headers, $matches)) {
        $totalPosts = (int)$matches[1];
    }
    
    $posts = [];
    foreach ($data as $item) {
        $posts[] = [
            'id' => $item['id'],
            'title' => isset($item['title']['rendered']) ? $item['title']['rendered'] : 'Без названия',
            'content' => isset($item['content']['rendered']) ? $item['content']['rendered'] : '',
            'excerpt' => isset($item['excerpt']['rendered']) ? $item['excerpt']['rendered'] : '',
            'published' => $item['date'],
            'modified' => $item['modified'],
            'link' => $item['link'],
            'slug' => $item['slug']
        ];
    }
    
    return [
        'success' => true, 
        'posts' => $posts, 
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $totalPosts
    ];
}

// ==========================================
// WORDPRESS XML-RPC IMPORT (for private sites)
// ==========================================

function fetchWordpressXmlRpc($siteUrl, $username, $password) {
    $siteUrl = rtrim($siteUrl, '/');
    $xmlRpcUrl = "{$siteUrl}/xmlrpc.php";
    
    $xml = '<?xml version="1.0"?>
<methodCall>
    <methodName>wp.getPosts</methodName>
    <params>
        <param><value><int>0</int></value></param>
        <param><value><string>' . htmlspecialchars($username) . '</string></value></param>
        <param><value><string>' . htmlspecialchars($password) . '</string></value></param>
        <param><value><struct>
            <member><name>post_status</name><value><string>publish</string></value></member>
            <member><name>number</name><value><int>100</int></value></member>
        </struct></value></param>
    </params>
</methodCall>';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $xmlRpcUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL Error: {$error}"];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP Error: {$httpCode}"];
    }
    
    // Parse XML response
    $xml = simplexml_load_string($response);
    if ($xml === false) {
        return ['success' => false, 'error' => 'Ошибка разбора XML ответа'];
    }
    
    $posts = [];
    foreach ($xml->params->param->value->array->data->value as $item) {
        $struct = $item->struct;
        $post = [];
        
        foreach ($struct->member as $member) {
            $name = (string)$member->name;
            $value = (string)$member->value;
            $post[$name] = $value;
        }
        
        $posts[] = [
            'id' => isset($post['post_id']) ? $post['post_id'] : '',
            'title' => isset($post['post_title']) ? $post['post_title'] : 'Без названия',
            'content' => isset($post['post_content']) ? $post['post_content'] : '',
            'published' => isset($post['post_date']) ? $post['post_date'] : '',
            'link' => isset($post['link']) ? $post['link'] : ''
        ];
    }
    
    return ['success' => true, 'posts' => $posts, 'total' => count($posts)];
}

// ==========================================
// BLOGGER ATOM/RSS FEED IMPORT
// ==========================================

function fetchBloggerFeed($feedUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $feedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL Error: {$error}"];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP Error: {$httpCode}"];
    }
    
    // Try to parse as Atom first
    $xml = @simplexml_load_string($response);
    if ($xml === false) {
        return ['success' => false, 'error' => 'Ошибка разбора RSS/Atom фида'];
    }
    
    $posts = [];
    
    // Check if Atom feed
    if (isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $namespaces = $entry->getNamespaces(true);
            $content = '';
            
            if (isset($entry->content)) {
                $content = (string)$entry->content;
            } elseif (isset($entry->summary)) {
                $content = (string)$entry->summary;
            }
            
            // Get the alternate link
            $link = '';
            foreach ($entry->link as $l) {
                $attrs = $l->attributes();
                if (isset($attrs['rel']) && (string)$attrs['rel'] === 'alternate') {
                    $link = (string)$attrs['href'];
                    break;
                }
            }
            
            $posts[] = [
                'id' => (string)$entry->id,
                'title' => (string)$entry->title,
                'content' => $content,
                'published' => (string)$entry->published,
                'updated' => (string)$entry->updated,
                'link' => $link
            ];
        }
    }
    // RSS 2.0 feed
    elseif (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $content = '';
            
            // Try content:encoded first
            $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
            if (isset($contentNs->encoded)) {
                $content = (string)$contentNs->encoded;
            } else {
                $content = (string)$item->description;
            }
            
            $posts[] = [
                'id' => (string)$item->guid,
                'title' => (string)$item->title,
                'content' => $content,
                'published' => (string)$item->pubDate,
                'link' => (string)$item->link
            ];
        }
    }
    
    if (empty($posts)) {
        return ['success' => false, 'error' => 'Не найдено записей в фиде'];
    }
    
    return ['success' => true, 'posts' => $posts, 'total' => count($posts)];
}

// ==========================================
// SAVE IMPORTED POST
// ==========================================

function saveImportedPost($title, $content, $originalDate = null, $source = 'import') {
    $blogDir = getDataPath('blog/');
    if (!is_dir($blogDir)) {
        mkdir($blogDir, 0777, true);
    }
    
    // Get next post ID
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
    
    // Use original date if provided, otherwise current date
    if ($originalDate) {
        try {
            $dt = new DateTime($originalDate);
            $date = $dt->format('d.m.Y H:i');
        } catch (Exception $e) {
            $date = date('d.m.Y H:i');
        }
    } else {
        $date = date('d.m.Y H:i');
    }
    
    // Format content
    $content = formatArticleContent($content);
    
    // Get template
    require_once 'templates_helper.php';
    $templateFile = getTemplatePath();
    if (!file_exists($templateFile)) {
        return ['success' => false, 'error' => 'Файл шаблона не найден'];
    }
    
    $articleHtml = getTemplateHtml($templateFile);
    
    // Prepare data
    $title = htmlspecialchars($title);
    
    // Add custom fonts
    require_once 'get_custom_fonts_css.php';
    $customFontsCss = getCustomFontsCss();
    
    // Add SEO meta tags
    require_once 'seo_helper.php';
    $seoMetaBlock = generateSeoMetaTagsBlock($nextId, $title, $content);
    
    // Replace placeholders
    $articleHtml = str_replace('{{POST_ID}}', $nextId, $articleHtml);
    $articleHtml = str_replace('{{TITLE}}', $title, $articleHtml);
    $articleHtml = str_replace('{{DATE}}', $date, $articleHtml);
    $articleHtml = str_replace('{{META_TAGS}}', $seoMetaBlock, $articleHtml);
    $articleHtml = replaceCustomFontsPlaceholder($articleHtml, $customFontsCss);
    
    $editorSettingsFile = __DIR__ . '/editor_settings.json';
    $editorSettings = [];
    if (file_exists($editorSettingsFile)) {
        $editorSettings = json_decode(file_get_contents($editorSettingsFile), true) ?: [];
    }
    $contentWidth = isset($editorSettings['contentWidth']) ? (int)$editorSettings['contentWidth'] : 920;
    $bodyStyle = "style=\"max-width: {$contentWidth}px;\"";
    $articleHtml = str_replace('{{BODY_STYLE}}', $bodyStyle, $articleHtml);
    $articleHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $articleHtml);
    $articleHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $articleHtml);
    
    // Insert content
    $wrappedContent = $content;
    if (strpos($articleHtml, 'id="npblog-post-content"') === false) {
        $wrappedContent = '<article id="npblog-post-content" class="content">' . $content . '</article>';
    }
    $articleHtml = str_replace('{{CONTENT}}', $wrappedContent, $articleHtml);
    
    // Save post file
    $filename = validateSafePath($blogDir, 'post-' . $nextId . '.html');
    file_put_contents($filename, $articleHtml);
    
    // Update posts-meta.json
    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
    $meta = [];
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
    }
    
    $meta[] = [
        'id' => $nextId,
        'title' => $title,
        'date' => $date,
        'filename' => 'post-' . $nextId . '.html'
    ];
    
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return ['success' => true, 'id' => $nextId, 'title' => $title, 'date' => $date];
}

function formatArticleContent($html) {
    // Remove Blogger/WordPress specific markup
    $html = preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $html);
    $html = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $html);
    $html = preg_replace('/<div\s+class=["\']share[^"\']*["\'][\s\S]*?<\/div>/i', '', $html);
    $html = preg_replace('/<div\s+class=["\']related[^"\']*["\'][\s\S]*?<\/div>/i', '', $html);
    
    // Remove empty paragraphs
    $html = preg_replace('/<p>\s*<\/p>/i', '', $html);
    $html = preg_replace('/<p>\s*<br\s*\/?>\s*<\/p>/i', '', $html);
    
    // Convert WordPress blocks
    $html = preg_replace('/<!--\s*\/?wp:[^\>]*-->/i', '', $html);
    
    // Remove excessive line breaks
    $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html);
    
    // Clean up WordPress image captions
    $html = preg_replace('/<div\s+class=["\']wp-caption[^"\']*["\'][^>]*>([\s\S]*?)<p\s+class=["\']wp-caption-text["\'][^>]*>([\s\S]*?)<\/p>[\s\S]*?<\/div>/i', '$1<p>$2</p>', $html);
    
    // Format HTML structure
    $blockTags = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'iframe', 'audio', 'center', 'details', 'summary', 'blockquote', 'hr'];
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

// ==========================================
// MAIN ACTION ROUTER
// ==========================================

switch ($action) {
    case 'fetch_blogger':
        $blogId = isset($_POST['blogId']) ? trim($_POST['blogId']) : '';
        $apiKey = isset($_POST['apiKey']) ? trim($_POST['apiKey']) : '';
        
        if (empty($blogId)) {
            echo json_encode(['success' => false, 'error' => 'ID блога не указан']);
            exit;
        }
        
        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'error' => 'API ключ не указан']);
            exit;
        }
        
        $result = fetchBloggerPosts($blogId, $apiKey);
        echo json_encode($result);
        break;
        
    case 'fetch_wordpress':
        $siteUrl = isset($_POST['siteUrl']) ? trim($_POST['siteUrl']) : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        
        if (empty($siteUrl)) {
            echo json_encode(['success' => false, 'error' => 'URL сайта не указан']);
            exit;
        }
        
        // Validate URL
        if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'Некорректный URL сайта']);
            exit;
        }
        
        $result = fetchWordpressPosts($siteUrl, 100, $page);
        echo json_encode($result);
        break;
        
    case 'fetch_wordpress_xmlrpc':
        $siteUrl = isset($_POST['siteUrl']) ? trim($_POST['siteUrl']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($siteUrl) || empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Не все данные указаны']);
            exit;
        }
        
        $result = fetchWordpressXmlRpc($siteUrl, $username, $password);
        echo json_encode($result);
        break;
        
    case 'fetch_feed':
        $feedUrl = isset($_POST['feedUrl']) ? trim($_POST['feedUrl']) : '';
        
        if (empty($feedUrl)) {
            echo json_encode(['success' => false, 'error' => 'URL фида не указан']);
            exit;
        }
        
        if (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'Некорректный URL фида']);
            exit;
        }
        
        $result = fetchBloggerFeed($feedUrl);
        echo json_encode($result);
        break;
        
    case 'import_posts':
        $postsJson = isset($_POST['posts']) ? $_POST['posts'] : '';
        
        if (empty($postsJson)) {
            echo json_encode(['success' => false, 'error' => 'Нет данных для импорта']);
            exit;
        }
        
        $posts = json_decode($postsJson, true);
        if (!is_array($posts) || empty($posts)) {
            echo json_encode(['success' => false, 'error' => 'Некорректные данные постов']);
            exit;
        }
        
        $results = [
            'success' => true,
            'imported' => 0,
            'failed' => 0,
            'posts' => []
        ];
        
        foreach ($posts as $post) {
            $title = isset($post['title']) ? $post['title'] : 'Без названия';
            $content = isset($post['content']) ? $post['content'] : '';
            $date = isset($post['published']) ? $post['published'] : null;
            
            $result = saveImportedPost($title, $content, $date);
            
            if ($result['success']) {
                $results['imported']++;
                $results['posts'][] = [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'status' => 'imported'
                ];
            } else {
                $results['failed']++;
                $results['posts'][] = [
                    'title' => $title,
                    'status' => 'failed',
                    'error' => $result['error']
                ];
            }
        }
        
        // Regenerate RSS feed after import
        require_once __DIR__ . '/rss_helper.php';
        generateRssFeed();
        
        echo json_encode($results);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        break;
}
?>
