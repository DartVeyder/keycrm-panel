<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/PrestaImportV2.php');
require_once('class/KeyCrmV2.php');

$keyCrm = new KeyCrmV2();
$prestaImport = new PrestaImportV2();

$listProducts = $keyCrm->listProducts();

$prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_update_products_price_stock.xlsx','update');

if(PRESTASHOP_UPDATE_PRICE){
    $startImport = $prestaImport->startUpdatePriceStock();
}
