<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/MySQLDB.php');

// 🔹 Приклад використання
//$db = new SQLiteDB("uploads/twice_data.sqlite");
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
// 🔹 Створення таблиці "products"
$db->createTable("analitic_products_stock", [
    "id INT AUTO_INCREMENT PRIMARY KEY",
    "keycrm_offer_id INT NULL",
    "keycrm_product_id INT NULL",
    "sku VARCHAR(255) NULL",
    "parent_sku VARCHAR(255) NULL",
    "name VARCHAR(255) NULL",
    "category VARCHAR(255) NULL",
    "price DECIMAL(10, 2) NULL",  // Used DECIMAL for price to store exact values
    "keycrm_stock INT NULL",
    "rozetka_stock INT NULL",
    "prom_stock INT NULL",
    "intertop_stock INT NULL",
    "prestashop_stock INT NULL",
    "kasta_stock INT NULL",
    "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
]);
