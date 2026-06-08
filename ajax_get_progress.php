<?php
$file = __DIR__ . '/sync_progress.json';

header('Content-Type: application/json; charset=utf-8');

if (file_exists($file)) {
    $content = file_get_contents($file);
    echo $content;
} else {
    echo json_encode(['percent' => 0, 'text' => 'Ініціалізація...']);
}
