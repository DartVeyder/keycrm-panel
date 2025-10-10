<?php

use League\Csv\Reader;
use Shuchkin\SimpleXLSXGen;
use Shuchkin\SimpleXLSX;


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');
require_once('class/MySQLDB.php');

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);


// === ЛОГ ЗАПУСКУ СКРИПТА ===
$logFile = __DIR__ . '/logs/log_upload_csv_1c.txt';
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

$originalName = basename($_FILES['file']['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$uniqueName = 'products_1c.' . $extension;
$destination = $uploadDir . $uniqueName;

// Переміщення файлу
if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {

    $prestashop = new Prestashop();
    $getPreorderProducts = $prestashop->getPreorderProducts();
    //$preorderProducts = array_column($getPreorderProducts['response'], null, 'reference');

    $csv = Reader::createFromPath('uploads/products_1c.csv', 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0); // перший рядок як заголовки

    $records = $csv->getRecords();

    $data1C = array_column( iterator_to_array($csv->getRecords()), 'Quantity','SKU') ;
    
    if ( $xlsx = SimpleXLSX::parse('uploads/prestashop_update_products_price_stock.xlsx') ) {
        $rows = $xlsx->rows();
        // Вивести усі рядки з першого аркуша
        $rows[0][] = 'quantity_1c';
        foreach ($rows as $i => &$row) {
            if ($i > 0) {
                if($row[19] == 1){
                    $row[] = 20;
                } else {
                    $row[] = $data1C[$row[2]] ?? ''; 
                }
                $values = array_map(function($v) {
                if ($v === null || $v === '') return "''"; // порожні лапки для пустих значень
                if (is_numeric($v)) return $v;            // числа без лапок
                return "'" . addslashes($v) . "'";        // екранізація рядків
            }, $row);
                        $sql = "INSERT INTO products_log (
                keycrm_parent_id, keycrm_id, sku, parent_sku, price, discount_price, quantity,
                size, color, is_active, is_added, product_name,
                short_description, description, images, main_category,
                subcategory_1, image, is_default, is_preorder, created_at,quantity_1c
            ) VALUES (" . implode(",", $values) . ")"; 
           
              $db->query($sql);  
            }
             
                      
            
        }



    } else {
        echo SimpleXLSX::parseError();
    }
    SimpleXLSXGen::fromArray($rows)->saveAs('uploads/prestashop_update_products_price_stock_1c.xlsx');


 //Запуск імпорту через cron-URL
    $cronUrl = "https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport?settings=11&id_shop_group=1&id_shop=1&secure_key=30aa0bdb68fa671e64a2ba3a4016aec0&action=importProducts";

    // Варіант через file_get_contents (просто, але без таймаутів)
    // @file_get_contents($cronUrl);

    // Варіант через cURL (рекомендовано)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $cronUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        "success" => true,
        "message" => "File uploaded successfully. Import executed.",
        "file" => $uniqueName,
        "path" => "/uploads/" . $uniqueName,
        "path_xlsx" => "/uploads/products_1c.xlsx",
        "import_response" => $response,
        "import_status" => $httpCode
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to move uploaded file."]);
}
