<?php
ini_set('max_execution_time', 0); // 5 хвилин
set_time_limit(0); // Альтернативний спосіб
ini_set('display_errors', 1);  // Включаємо відображення помилок
error_reporting(E_ERROR);      // Виводимо тільки фатальні помилки
$startTime = microtime(true);
require_once('vendor/autoload.php');
require_once('class/SyncLogger.php');

require_once('config.php');
require_once ('class/MySQLDB.php');
require_once('class/Base.php');
require_once('class/KeyCrmV2.php');
require_once('class/Prestashop.php');
require_once('class/Rozetka.php');
require_once('class/Prom.php');
require_once('class/PrestaImportV2.php');
require_once('class/KastaV2.php');
require_once ('class/IntertopV2.php');

function setProgress($percent, $text) {
    file_put_contents(__DIR__ . '/sync_progress.json', json_encode(['percent' => $percent, 'text' => $text], JSON_UNESCAPED_UNICODE));
}

function checkStopFlag() {
    if (file_exists(__DIR__ . '/stop.flag')) {
        echo "\n[ABORTED] Виконання зупинено користувачем!\n";
        unlink(__DIR__ . '/stop.flag');
        exit;
    }
}

$logger = new SyncLogger(__DIR__ . '/logs', true, true, 'update_stock_');
$logger->separator('СТАРТ ОНОВЛЕННЯ ЗАЛИШКІВ І ЦІН');
$logger->info('Сайт призначення: ' . PRESTASHOP_SITE_URL);
$logger->info('Етап 1: Ініціалізація оновлення...');
setProgress(5, "Ініціалізація оновлення...");
checkStopFlag();

$keyCrm = new KeyCrmV2();
$prestaImport = new PrestaImportV2();
$kasta = new KastaV2();
$intertop = new IntertopV2();
$prestashop = new Prestashop();
$rozetka = new Rozetka();
$prom = new Prom();

$fileNameXLSX = __DIR__ . '/uploads/prestashop_update_products_price_stock.xlsx';

$product_ids  = $_GET['product_ids'] ?? '';

if(empty($product_ids)){
    echo "Обновлення всіх залишків і цін\n";
    $logger->info('Етап 2: Отримання списку всіх товарів з KeyCRM...');
    setProgress(10, "Отримання списку всіх товарів з KeyCRM...");
}else{
    echo "Обновлення залишків і цін при зміні статусу\n";
    $fileNameXLSX = __DIR__ . '/uploads/prestashop_update_products_price_stock_change_status.xlsx';
    $logger->info('Етап 2: Отримання змінених товарів з KeyCRM...');
    setProgress(10, "Отримання змінених товарів з KeyCRM...");
}

$listProducts = $keyCrm->listProducts($product_ids );

checkStopFlag();

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

$logger->info('Етап 3: Формування XLSX файлу для PrestaShop...');
setProgress(40, "Формування XLSX файлу для PrestaShop...");

if(PRESTASHOP){
    checkStopFlag();
    $prestaImport->generateListProductsXLSX($listProducts, $fileNameXLSX ,'update', $logger);

    if(PRESTASHOP_UPDATE_PRICE){
        checkStopFlag();
        $logger->info('Етап 4: Відправка даних на сайт (PrestaShop)...');
        setProgress(70, "Відправка даних на сайт (PrestaShop)... Це може зайняти кілька хвилин.");
        if(empty($product_ids)){
            echo "Обновлення всіх залишків і цін на сайті\n";
            $startImport = $prestaImport->startUpdatePriceStock($logger);
        }else{
            echo "Обновлення залишків і цін на сайті при зміні статусу\n";
            $startImport = $prestaImport->startUpdatePriceStockChangeStatus($logger);
        }
    }
}

checkStopFlag();
$logger->info('Етап 5: Оновлення інших маркетплейсів (Kasta, Intertop)...');
setProgress(85, "Оновлення інших маркетплейсів (Kasta, Intertop)...");

if(isset($product_ids)) {

    if(KASTA) {
        $kasta->listBarcodes();

        $inBarcodes = $kasta->listBarcodes();

        $itemsDataStock = $kasta->formatDataStock($listProducts,$inBarcodes);
        $itemsDataPrice = $kasta->formatDataPrice($listProducts,$inBarcodes );

        $updateStock = $kasta->updateStock( $itemsDataStock );

        $updatePrice = $kasta->updatePrice($itemsDataPrice);

    }
    if (INTERTOP) {

        $response = [];
        $intertop->productsKeycrm = array_column($listProducts, null, 'sku');;

        $offers = $intertop->getDataToUpdateQuantity();

        $groups = array_chunk($offers, 1000);
        $totalGroups = count($groups);
        $currentGroup = 0;

        foreach ($groups as $group) {
            $currentGroup++;
            setProgress(85 + round(($currentGroup / $totalGroups) * 10), "Оновлення Intertop: партія $currentGroup з $totalGroups...");
            $updateQuantity = $intertop->updateQuantity($group);
            $updatePrice = $intertop->updatePrice($group);
            $response['Quantity'] = $updateQuantity;
            $response['Price'] = $updatePrice;
        }
    }
}

setProgress(100, "Оновлення завершено!");

$endTime = microtime(true);
$executionTime = $endTime - $startTime;
echo "Час виконання скрипта: " . round($executionTime, 4) . " секунд\n";
