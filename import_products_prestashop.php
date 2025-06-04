<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

require_once('vendor/autoload.php');
require_once('class/Base.php');
require_once('config.php');
require_once('class/Prestashop.php');
require_once('class/KeyCrmV2.php');
require_once ('class/PrestaImportV2.php');

if(empty($product_ids)){
    $product_ids  = $_GET['product_ids'] ?? '';
}

$keyCrm = new KeyCrmV2();
$listProducts = $keyCrm->listProducts($product_ids);

$prestaImport = new PrestaImportV2();
$prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_import_products.xlsx','import');

if (PRESTASHOP_IMPORT_PRODUCT){
    $startImport = $prestaImport->startImport();
}
