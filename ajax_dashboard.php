<?php
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) { $bytes = number_format($bytes / 1073741824, 2) . ' GB'; }
    elseif ($bytes >= 1048576) { $bytes = number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { $bytes = number_format($bytes / 1024, 2) . ' KB'; }
    elseif ($bytes > 1) { $bytes = $bytes . ' bytes'; }
    elseif ($bytes == 1) { $bytes = $bytes . ' byte'; }
    else { $bytes = '0 bytes'; }
    return $bytes;
}

function addScriptLog($scriptName, $status, $duration) {
    $file = __DIR__ . '/logs/script_history.json';
    $history = [];
    if (file_exists($file)) {
        $history = json_decode(file_get_contents($file), true) ?: [];
    }
    
    array_unshift($history, [
        'script' => $scriptName,
        'date' => date('Y-m-d H:i:s'),
        'duration' => $duration,
        'status' => $status
    ]);
    
    // Зберігаємо тільки останні 1000 записів для економії місця
    $history = array_slice($history, 0, 1000);
    file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE));
}

if ($action === 'get_script_history') {
    $file = __DIR__ . '/logs/script_history.json';
    $history = [];
    if (file_exists($file)) {
        $history = json_decode(file_get_contents($file), true) ?: [];
    }
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

if ($action === 'get_logs') {
    $logDir = __DIR__ . '/logs';
    $logs = [];
    if (is_dir($logDir)) {
        foreach (scandir($logDir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $logDir . '/' . $file;
                if (is_file($filePath)) {
                    $logs[] = [
                        'name' => $file,
                        'size' => formatSizeUnits(filesize($filePath)),
                        'mtime' => date('Y-m-d H:i:s', filemtime($filePath))
                    ];
                }
            }
        }
    }
    echo json_encode(['success' => true, 'logs' => $logs]);
    exit;
}

if ($action === 'read_log') {
    $file = $_GET['file'] ?? '';
    $file = basename($file); // Prevent directory traversal
    $filePath = __DIR__ . '/logs/' . $file;
    if (file_exists($filePath) && is_file($filePath)) {
        $content = file_get_contents($filePath);
        if (strlen($content) > 100000) {
            $content = "... (truncated) ...\n" . substr($content, -100000);
        }
        echo json_encode(['success' => true, 'content' => htmlspecialchars($content)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found.']);
    }
    exit;
}

if ($action === 'clear_log') {
    $file = $_GET['file'] ?? '';
    $file = basename($file);
    $filePath = __DIR__ . '/logs/' . $file;
    if (file_exists($filePath) && is_file($filePath)) {
        file_put_contents($filePath, '');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found.']);
    }
    exit;
}

if ($action === 'clear_stop_flag') {
    $flag = __DIR__ . '/stop.flag';
    if (file_exists($flag)) {
        unlink($flag);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'set_stop_flag') {
    file_put_contents(__DIR__ . '/stop.flag', 'stop');
    // Оновлюємо прогрес, щоб фронтенд відразу побачив
    file_put_contents(__DIR__ . '/sync_progress.json', json_encode(['percent' => 100, 'text' => 'Переривання процесу...'], JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get_uploads') {
    $currentDir = $_GET['dir'] ?? '';
    // Basic protection against path traversal
    $currentDir = trim(str_replace('..', '', $currentDir), '/');
    
    $items = [];
    
    if (empty($currentDir)) {
        // Virtual root
        $items[] = [
            'type' => 'folder',
            'name' => 'uploads',
            'path' => 'uploads',
            'size' => '-',
            'mtime' => '-'
        ];
        $items[] = [
            'type' => 'folder',
            'name' => 'xlsx',
            'path' => 'xlsx',
            'size' => '-',
            'mtime' => '-'
        ];
    } else {
        $realPath = __DIR__ . '/' . $currentDir;
        if (is_dir($realPath)) {
            // Add "go up" directory if we are inside a folder
            $parentDir = dirname($currentDir);
            if ($parentDir === '.') $parentDir = '';
            
            $items[] = [
                'type' => 'folder',
                'name' => '..',
                'path' => $parentDir,
                'size' => '-',
                'mtime' => '-'
            ];
            
            foreach (scandir($realPath) as $item) {
                if ($item !== '.' && $item !== '..') {
                    $itemPath = $realPath . '/' . $item;
                    $relPath = $currentDir . '/' . $item;
                    
                    if (is_dir($itemPath)) {
                        $items[] = [
                            'type' => 'folder',
                            'name' => $item,
                            'path' => $relPath,
                            'size' => '-',
                            'mtime' => date('Y-m-d H:i:s', filemtime($itemPath))
                        ];
                    } else if (is_file($itemPath)) {
                        $items[] = [
                            'type' => 'file',
                            'name' => $item,
                            'path' => $relPath,
                            'size' => formatSizeUnits(filesize($itemPath)),
                            'mtime' => date('Y-m-d H:i:s', filemtime($itemPath))
                        ];
                    }
                }
            }
        }
    }
    
    // Sort items: folders first (with '..' at the very top), then files sorted by date descending
    usort($items, function($a, $b) {
        if ($a['name'] === '..') return -1;
        if ($b['name'] === '..') return 1;
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'folder' ? -1 : 1;
        }
        if ($a['mtime'] === '-' || $b['mtime'] === '-') {
            return strcmp($a['name'], $b['name']);
        }
        return strtotime($b['mtime']) - strtotime($a['mtime']);
    });
    
    echo json_encode(['success' => true, 'items' => $items, 'current_dir' => $currentDir]);
    exit;
}

if ($action === 'delete_upload') {
    $path = $_GET['path'] ?? '';
    if ((strpos($path, 'uploads/') === 0 || strpos($path, 'xlsx/') === 0) && strpos($path, '..') === false) {
        $filePath = __DIR__ . '/' . $path;
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid path.']);
    }
    exit;
}

if ($action === 'run_script') {
    $script = $_GET['script'] ?? '';
    $script = basename($script); 
    $allowedScripts = [
        'cron_refund.php', 'cron_shipping_dates.php', 'cron_update_products.php',
        'update_products_price_stock.php', 'update_stock.php', 'update_intertop.php',
        'check_products.php', 'presta_import.php', 'presta_update_price.php',
        'import_products.php', 'import_products_intertop.php', 'import_products_kasta.php',
        'import_products_prestashop.php', 'test.php'
    ];
    
    if (in_array($script, $allowedScripts)) {
        $scriptPath = __DIR__ . '/' . $script;
        if (file_exists($scriptPath)) {
            ob_start();
            $startTime = microtime(true);
            try {
                // Use shell_exec to prevent script's exit() from killing this request
                $output = shell_exec("php " . escapeshellarg($scriptPath) . " 2>&1");
            } catch (Exception $e) {
                $output = "Error: " . $e->getMessage();
            }
            $duration = round(microtime(true) - $startTime);
            
            $buffer = ob_get_clean();
            $output = $buffer . "\n" . $output;
            
            $status = 'success';
            if (strpos($output, '[ABORTED]') !== false) {
                $status = 'aborted';
            } elseif (strpos(strtolower($output), 'error') !== false || strpos(strtolower($output), 'fatal') !== false || strpos(strtolower($output), 'помилка') !== false || strpos(strtolower($output), 'exception') !== false) {
                $status = 'error';
            }
            
            addScriptLog($script, $status, $duration);
            
            echo json_encode(['success' => true, 'output' => htmlspecialchars(trim($output))]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Script not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Script not allowed.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
