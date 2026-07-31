<?php
// Скрипт для запуску оновлення залишків і цін через CRON
// Його можна запускати через планувальник завдань (cron) або через консоль PHP CLI

ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ERROR);

echo "Запуск CRON оновлення залишків і цін: " . date('Y-m-d H:i:s') . "\n";

// Симулюємо відсутність product_ids, щоб відбулося повне оновлення (всіх товарів)
$_GET['product_ids'] = '';

try {
    // Підключаємо основний файл оновлення
    require_once(__DIR__ . '/update_products_price_stock.php');
} catch (Exception $e) {
    echo "Помилка під час виконання CRON: " . $e->getMessage() . "\n";
}

echo "\nCRON завершено: " . date('Y-m-d H:i:s') . "\n";
