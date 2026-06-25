<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$smilesDir = getDataPath('smiles/');
$sets = [];

if (is_dir($smilesDir)) {
    // Получаем список всех подпапок в папке smiles
    $subDirs = array_filter(glob($smilesDir . '*'), 'is_dir');
    foreach ($subDirs as $dir) {
        $setName = basename($dir);
        $gifFiles = glob($dir . '/*.gif');
        
        $urls = [];
        foreach ($gifFiles as $file) {
            $urls[] = getDataUrl('smiles/' . $setName . '/' . basename($file));
        }
        
        $sets[$setName] = $urls;
    }
}

echo json_encode(['success' => true, 'sets' => $sets]);
?>
