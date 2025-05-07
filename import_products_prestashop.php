<?php


require_once('vendor/autoload.php');
require_once('class/Base.php');
require_once('config.php');
require_once('class/Prestashop.php');
require_once('class/KeyCrmV2.php');
require_once ('class/PrestaImportV2.php');


$keyCrm = new KeyCrmV2();
$listProducts = $keyCrm->listProducts();

$prestaImport = new PrestaImportV2();
$prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_import_products.xlsx','import');

if (PRESTASHOP_IMPORT_PRODUCT){
    $startImport = $prestaImport->startImport();
}
