<?php

require_once('vendor/autoload.php');
require_once('config.php');
require_once("class/MySQLDB.php");
require_once "class/Api.php";

// Підключення до БД
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
$api = new Api($db);

// Читаємо параметри GET
$type = $_GET['type'] ?? '';
$sku  = $_GET['sku'] ?? '';

switch ($type) {
    case 'quantity':
        if ($sku) {
            $api->getQuantityBySku($sku);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Передайте параметр ?sku=ABC123"
            ]);
        }
        break;

    default:
        echo json_encode([
            "status" => "error",
            "message" => "Невідомий тип запиту"
        ]);
        break;
}
