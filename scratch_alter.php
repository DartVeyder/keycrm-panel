<?php
require 'config.php';
require 'class/MySQLDB.php';
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

// 1. Додаємо колонку, якщо її ще немає
try {
    $db->query("ALTER TABLE check_products_cache ADD COLUMN product_type VARCHAR(20) DEFAULT 'regular' AFTER sync_date");
    echo "Колонку product_type успішно додано.\n";
} catch (Exception $e) {
    echo "Колонка product_type вже існує.\n";
}

// 2. Оновлюємо існуючі записи (дефекти)
$db->query("UPDATE check_products_cache SET product_type = 'defect' WHERE sku LIKE '%8888_%' OR sku LIKE '8888%' OR sku LIKE '%88_%' OR sku LIKE '88%'");
echo "Дефекти оновлено.\n";

// 3. Оновлюємо існуючі записи (взірці)
$db->query("UPDATE check_products_cache SET product_type = 'sample' WHERE 
    name_1c REGEXP '(^|_| )В[0-9]*($|[^\\p{L}])' OR 
    name_keycrm REGEXP '(^|_| )В[0-9]*($|[^\\p{L}])' OR
    sku LIKE '%В_%' OR sku LIKE '%В%' OR size REGEXP '(^|_| )В[0-9]*($|[^\\p{L}])'");
echo "Взірці оновлено.\n";

echo "База даних успішно оновлена!\n";
