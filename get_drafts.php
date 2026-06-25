<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$drafts = [];

if (file_exists('draft')) {
    $files = glob('draft/*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $draft = json_decode($content, true);
        
        if ($draft) {
            $draft['filename'] = basename($file);
            $drafts[] = $draft;
        }
    }
    
    // Сортируем по времени (новые первыми)
    usort($drafts, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
}

echo json_encode(['success' => true, 'drafts' => $drafts]);
