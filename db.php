<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/SQLiteDB.php');

// 🔹 Приклад використання
$db = new SQLiteDB("uploads/twice_data.sqlite");

// 🔹 Створення таблиці "products"
if (!$db->tableExists("products")) {
    $db->createTable("products", [
        "id INTEGER PRIMARY KEY AUTOINCREMENT",
        "keycrm_offer_id INTEGER NULL ",
        "keycrm_product_id INTEGER NULL ",
        "sku TEXT NULL",
        "parent_sku TEXT NULL",
        "name TEXT  NULL",
        "category TEXT NULL",
        "price REAL NULL",
        "keycrm_stock INTEGER NULL ",
        "rozetka_stock INTEGER NULL ",
        "prom_stock INTEGER NULL ",
        "intertop_stock INTEGER NULL ",
        "prestashop_stock INTEGER  NULL",
        "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ]);
}
