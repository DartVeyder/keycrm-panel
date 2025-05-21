<?php
ini_set('max_execution_time', 0); // 5 хвилин
set_time_limit(0); // Альтернативний спосіб
ini_set('display_errors', 1);  // Включаємо відображення помилок
error_reporting(E_ERROR);      // Виводимо тільки фатальні помилки
$startTime = microtime(true);
require_once('vendor/autoload.php');

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


$keyCrm = new KeyCrmV2();
$prestaImport = new PrestaImportV2();
$kasta = new KastaV2();
$intertop = new IntertopV2();
$prestashop = new Prestashop();
$rozetka = new Rozetka();
$prom = new Prom();

$fileNameXLSX = 'uploads/prestashop_update_products_price_stock.xlsx';

if(empty($product_ids)){
    $product_ids  = $_GET['product_ids'] ?? '';
    echo "Обновлення всіх залишків і цін ";
}else{
    echo "Обновлення залишків і цін при зміні статусу";
    $fileNameXLSX = 'uploads/prestashop_update_products_price_stock_change_status.xlsx';
}


$listProducts = $keyCrm->listProducts($product_ids );

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);


if(PRESTASHOP){

    $prestaImport->generateListProductsXLSX($listProducts, $fileNameXLSX ,'update');

    if(PRESTASHOP_UPDATE_PRICE){
        if(empty($product_ids)){
            echo "Обновлення всіх залишків і цін на сайті";
            $startImport = $prestaImport->startUpdatePriceStock();
        }else{
            echo "Обновлення залишків і цін на сайті при зміні статусу";
            $startImport = $prestaImport->startUpdatePriceStockChangeStatus();
        }
    }
}

echo "<pre>";
if(KASTA) {
    $kasta->listBarcodes();

    $inBarcodes = $kasta->listBarcodes();

    $itemsDataStock = $kasta->formatDataStock($listProducts,$inBarcodes);
    $itemsDataPrice = $kasta->formatDataPrice($listProducts,$inBarcodes );

    $updateStock = $kasta->updateStock( $itemsDataStock );
    print_r($updateStock) ;
    $updatePrice = $kasta->updatePrice($itemsDataPrice);
    print_r($updatePrice) ;
}

if(INTERTOP){

    $response = [];
    $intertop->productsKeycrm = array_column($listProducts , null,'sku'); ;

    $offers = $intertop->getDataToUpdateQuantity() ;

    $groups = array_chunk($offers, 1000);

    foreach ($groups  as $group){
        $updateQuantity = $intertop->updateQuantity($group ) ;
        $updatePrice = $intertop->updatePrice($group ) ;
        $response['Quantity'] =  $updateQuantity;
        $response['Price'] =  $updatePrice;
    }
    print_r($response) ;
}


$endTime = microtime(true);
$executionTime = $endTime - $startTime;
echo "Час виконання скрипта: " . round($executionTime, 4) . " секунд\n";
