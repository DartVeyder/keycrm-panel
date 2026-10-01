<?php
ini_set('max_execution_time', 0); // 5 хвилин
set_time_limit(0); // Альтернативний спосіб
ini_set('display_errors', 1);  // Включаємо відображення помилок
error_reporting(E_ERROR);      // Виводимо тільки фатальні помилки
require_once('vendor/autoload.php');
require_once('class/Base.php');
require_once('config.php');
require_once('class/Prestashop.php');
require_once('class/KeyCrmV2.php');
require_once('class/PrestaImportV2.php');
require_once('class/SyncLogger.php');

$logger = new SyncLogger(__DIR__ . '/logs', true, true, 'presta_import_');

$logger->separator("СТАРТ ІМПОРТУ ТОВАРІВ В PRESTASHOP");
$logger->info("Сайт призначення: " . PRESTASHOP_SITE_URL);
$logger->info("Етап 1: Ініціалізація...");

if (empty($product_ids)) {
    $product_ids = $_GET['product_ids'] ?? '';
}

$logger->info("Етап 2: Отримання товарів з KeyCRM...", $product_ids ? "ID: $product_ids" : "Всі товари");
$keyCrm       = new KeyCrmV2();
$listProducts = $keyCrm->listProducts($product_ids);
$logger->success("Отримано " . count($listProducts) . " товарів з KeyCRM");

$logger->info("Етап 3: Генерація XLSX файлу для PrestaShop...");
$prestaImport = new PrestaImportV2();
$prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_import_products.xlsx', 'import', $logger);

if (PRESTASHOP_IMPORT_PRODUCT) {
    $logger->info("Етап 4: Глобальне налаштування PRESTASHOP_IMPORT_PRODUCT увімкнено. Запуск імпорту.");
    $startImport = $prestaImport->startImport($logger);
    
} else {
    $logger->warning("Етап 4: Імпорт вимкнено (PRESTASHOP_IMPORT_PRODUCT = false)");
}
$logger->separator("ЗАВЕРШЕННЯ ІМПОРТУ");
