<?php

use Shuchkin\SimpleXLSXGen;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
 header("Content-Type: application/json");

require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');

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
    $preorderProducts = array_column($getPreorderProducts['response'], null, 'reference');

    $products1c = [];
    if (($handle = fopen('uploads/'.$uniqueName, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, ';')) !== false) {
            if( $preorderProducts [$data[0]] ){
                $data[3] = $preorderProducts [$data[0]]['pre_order_product_quantity_limit'];
            }
            $products1c[] = $data;
        }
        fclose($handle);
    }

    SimpleXLSXGen::fromArray($products1c)->saveAs('uploads/products_1c.xlsx');

// Запуск імпорту через cron-URL
    $cronUrl = "https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport?settings=10&id_shop_group=1&id_shop=1&secure_key=30aa0bdb68fa671e64a2ba3a4016aec0&action=importProducts";

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
