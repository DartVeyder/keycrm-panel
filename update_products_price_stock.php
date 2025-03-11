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


$promProducts = $prom->products();
$promProducts = array_column($promProducts, 'quantity_in_stock','sku');

$rozetkaProducts = $rozetka->products();
$rozetkaProducts = array_column($rozetkaProducts, 'stock_quantity','article');

$intertopProducts = $intertop->readProductsFromJson('uploads/intertop_products.json');
$intertopProducts  = array_column($intertopProducts['data'], 'quantity','barcode');

$kasta_products = $kasta->productsStock();

$ps_combinations = $prestashop->getCombinations('[id,reference,quantity]');
$ps_combinations = array_column($ps_combinations, 'quantity','reference');

foreach ($listProducts as $offer){

    $data = [
        'keycrm_offer_id' => $offer['id'],
        'keycrm_product_id' => $offer['product_id'],
        'sku' =>$offer['sku'],
        'parent_sku' => $offer['product']['parentSku'],
        'name' => $offer['product']['name'],
        'category' => $offer['product']['category']['full_name'],
        'price' => $offer['price'],
        'keycrm_stock' => $offer['stock'],
        'prestashop_stock' => $ps_combinations[$offer['sku']],
        'kasta_stock' => $kasta_products[$offer['sku']],
        'rozetka_stock' => $rozetkaProducts[$offer['sku']],
        'prom_stock' => $promProducts[$offer['sku']],
        'intertop_stock' => $intertopProducts[$offer['sku']],
    ];

    $db->insertOrUpdate("analitic_products_stock", $data , "keycrm_offer_id = ?", [$offer['id']]);

}
