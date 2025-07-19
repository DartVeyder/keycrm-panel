<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/MySQLDB.php');

// 🔹 Приклад використання
//$db = new SQLiteDB("uploads/twice_data.sqlite");
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
/*
$db->insertOrUpdate("analitic_products_stock", [
    "keycrm_offer_id" => 11502,
    "keycrm_product_id" => 1,
    "sku" => "sku",
    "parent_sku" => "parent_sku",
    "name" => "name",
    "category" => "category",
    "price" => 1000,
    "keycrm_stock" => 10
], "keycrm_offer_id");
*/
// 🔹 Створення таблиці "products"
$db->createTable("analitic_products_stock", [
    "id INT AUTO_INCREMENT PRIMARY KEY",
    "keycrm_offer_id INT ",
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
    "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "UNIQUE (keycrm_offer_id)"
]);

//$db->createTable("marketplaces", [
//    "id INT AUTO_INCREMENT PRIMARY KEY",
//    "name VARCHAR(255) NOT NULL",
//    "active TINYINT(1) DEFAULT 1",
//    "is_update TINYINT(1) DEFAULT 1",
//    "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
//    "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
//]);
