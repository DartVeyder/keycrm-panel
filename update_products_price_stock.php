<?php
ini_set('max_execution_time', 0); // 5 хвилин
set_time_limit(0); // Альтернативний спосіб
ini_set('display_errors', 1);  // Включаємо відображення помилок
error_reporting(E_ERROR);      // Виводимо тільки фатальні помилки

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

$listProducts = $keyCrm->listProducts();
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

if(PRESTASHOP){

    $prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_update_products_price_stock.xlsx','update');

    if(PRESTASHOP_UPDATE_PRICE){
        $startImport = $prestaImport->startUpdatePriceStock();
    }
}

if(KASTA) {
    $kasta->listBarcodes();

    $inBarcodes = $kasta->listBarcodes();

    $itemsDataStock = $kasta->formatDataStock($listProducts,$inBarcodes);
    $itemsDataPrice = $kasta->formatDataPrice($listProducts,$inBarcodes );

    $updateStock = $kasta->updateStock( $itemsDataStock );
    $updatePrice = $kasta->updatePrice($itemsDataPrice);

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


}
