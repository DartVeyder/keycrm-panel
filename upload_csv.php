<?php

use League\Csv\Reader;
use Shuchkin\SimpleXLSXGen;
use Shuchkin\SimpleXLSX;


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// $blockedIps = ['172.70.250.23'];

// if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $blockedIps)) {
//     http_response_code(403);
//     echo json_encode(["error" => "Access denied for IP " . $_SERVER['REMOTE_ADDR']]);
    
//     // Записуємо у лог спробу доступу
//     $logDir = __DIR__ . '/logs/';
//     if (!is_dir($logDir)) {
//         mkdir($logDir, 0777, true);
//     }
//     $logFile = $logDir . 'log_upload_csv_1c.txt';
//     file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] BLOCKED ACCESS from " . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);
    
//     exit;
// }

require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');
require_once('class/MySQLDB.php');

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);


// === ЛОГ ЗАПУСКУ СКРИПТА ===
$logDir = __DIR__ . '/logs/';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . 'log_upload_csv_1c.txt';
$logMessage = sprintf(
    "[%s] Script started by %s (%s method)\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown IP',
    $_SERVER['REQUEST_METHOD'] ?? 'unknown'
);
file_put_contents($logFile, $logMessage, FILE_APPEND);


// Перевірка методу
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST method allowed."]);
    exit;
}

// Перевірка, чи файл передано
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["error" => "No file uploaded or upload error."]);
    exit;
}

// Перевірка MIME типу
$allowedMimeTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
if (!in_array($_FILES['file']['type'], $allowedMimeTypes)) {
    http_response_code(415);
    echo json_encode(["error" => "Invalid file type. Only CSV allowed."]);
    exit;
}

// Створення унікального імені файлу
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$historyDir = $uploadDir . 'history_products_1c/';
if (!is_dir($historyDir)) {
    mkdir($historyDir, 0777, true);
}

$originalName = basename($_FILES['file']['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$uniqueName = 'products_1c.' . $extension;
$destination = $uploadDir . $uniqueName;

// Ім’я файлу для історії
$timestamp = date('Ymd_His');
$historyFile = $historyDir . "products_1c_{$timestamp}." . $extension;


// Переміщення файлу
if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {

    // Копіюємо у папку історії
    copy($destination, $historyFile);

    // Логування завантаження
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] File uploaded: {$originalName}, saved as {$uniqueName}, history copy: " . basename($historyFile) . "\n", FILE_APPEND);

    $prestashop = new Prestashop();
    $getPreorderProducts = $prestashop->getPreorderProducts();
    //$preorderProducts = array_column($getPreorderProducts['response'], null, 'reference');

    $csv = Reader::createFromPath('uploads/products_1c.csv', 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);

    // Створюємо масив, де ключем є SKU, а значенням — масив з кількістю та оптовою ціною
    $data1C = [];
    foreach ($csv->getRecords() as $record) {
        $data1C[$record['SKU']] = [
            'quantity' => $record['Quantity'] ?? 0,
            'whole_price' => $record['Whole price'] ?? 0 // Додаємо оптову ціну з CSV
        ];
    }
 
    if ($xlsx = SimpleXLSX::parse('uploads/prestashop_update_products_price_stock.xlsx')) {
        $rows = $xlsx->rows();
        
        // Додаємо заголовки для нових колонок у масив $rows
        $rows[0][] = 'quantity_1c';
        $rows[0][] = 'whole_price';
        $rows[0][] = 'whole_quantity_1c';

        foreach ($rows as $i => &$row) {
            if ($i > 0) {
                $sku = $row[2];
                $product1C = $data1C[$sku] ?? null;
                $price = $row[4];
                $isPreorder = $row[20];
                $quantity = $row[6];

                    // 1. Логіка для quantity_1c
                if( $isPreorder){
                    $row[] = $quantity ; 
                }else{
                    $row[] = $product1C['quantity'] ?? 0; 
                }
                

                if( $product1C['whole_price']){
                      // 2. Логіка для whole_price
                    $whole_price = $product1C['whole_price'] ?? 0;
                    $row[] = $price - $whole_price;
                } else {
                    $row[] = 0;
                }
              
                if($product1C['whole_price'] <= 0){
                    $row[] = 0;
                }else{
                    $row[] = $product1C['quantity'] ?? 0; 
                }

                // Форматування значень для SQL
                $values = array_map(function($v) {
                    if ($v === null || $v === '') return "''";
                    if (is_numeric($v)) return $v;
                    return "'" . addslashes($v) . "'";
                }, $row);

                // Додаємо whole_price в перелік колонок INSERT
                $sql = "INSERT INTO products_log (
                    keycrm_parent_id, keycrm_id, sku, parent_sku, price, discount_price, quantity,
                    size, color, is_active, is_added, product_name,
                    short_description, description, images, main_category,
                    subcategory_1, image, is_default, is_preorder, created_at, 
                    quantity_1c, whole_price
                ) VALUES (" . implode(",", $values) . ")";
           
                $db->query($sql);  
            }
        }
    } else {
        echo SimpleXLSX::parseError();
    }

    SimpleXLSXGen::fromArray($rows)->saveAs('uploads/prestashop_update_products_price_stock_1c.xlsx');

    // === Запуск імпорту через cron-URL ===
    $cronUrl = "https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport?settings=11&id_shop_group=1&id_shop=1&secure_key=30aa0bdb68fa671e64a2ba3a4016aec0&action=importProducts";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $cronUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Логування завершення
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Import executed. HTTP: {$httpCode}\n", FILE_APPEND);

    echo json_encode([
        "success" => true,
        "message" => "File uploaded successfully. Import executed.",
        "file" => $uniqueName,
        "path" => "/uploads/" . $uniqueName,
        "path_xlsx" => "/uploads/products_1c.xlsx",
        "history" => "/uploads/history/" . basename($historyFile),
        "import_response" => $response,
        "import_status" => $httpCode
    ]);

} else {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to move uploaded file\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["error" => "Failed to move uploaded file."]);
}

