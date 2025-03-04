<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/KeyCrmV2.php');
require_once('class/PrestaImportV2.php');
require_once('class/KastaV2.php');
require_once ('class/IntertopV2.php');

$keyCrm = new KeyCrmV2();

$listProducts = $keyCrm->listProducts();


if(PRESTASHOP){
    $prestaImport = new PrestaImportV2();
    $prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_update_products_price_stock.xlsx','update');

    if(PRESTASHOP_UPDATE_PRICE){
        $startImport = $prestaImport->startUpdatePriceStock();
    }
}

if(KASTA) {
    $kasta = new KastaV2();
    $kasta->listBarcodes();

    $inBarcodes = $kasta->listBarcodes();

    $itemsDataStock = $kasta->formatDataStock($listProducts,$inBarcodes);
    $itemsDataPrice = $kasta->formatDataPrice($listProducts,$inBarcodes );

    $updateStock = $kasta->updateStock( $itemsDataStock );
    $updatePrice = $kasta->updatePrice($itemsDataPrice);

}

if(INTERTOP){
    $intertop = new IntertopV2();
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
