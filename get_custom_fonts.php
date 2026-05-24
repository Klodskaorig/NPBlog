<?php
header('Content-Type: application/json');

$fontsDir = 'data/fonts/';
$fonts = [];

if (is_dir($fontsDir)) {
    $files = scandir($fontsDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        // Поддерживаемые форматы шрифтов
        if (in_array($ext, ['ttf', 'otf', 'woff', 'woff2'])) {
            $fontName = pathinfo($file, PATHINFO_FILENAME);
            $fonts[] = [
                'name' => $fontName,
                'file' => $file,
                'path' => $fontsDir . $file,
                'format' => $ext
            ];
        }
    }
}

echo json_encode(['success' => true, 'fonts' => $fonts]);
?>
