<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');
require_once('class/PrestaImportV2.php');
require_once('class/KeyCrmV2.php');
require_once('class/IntertopV2.php');

$keyCrm = new KeyCrmV2();
$listProducts = $keyCrm->listProducts();

if(PRESTASHOP){
    $prestaImport = new PrestaImportV2();
    $prestaImport->generateListProductsXLSX($listProducts, 'uploads/prestashop_import_products.xlsx','import');

    if (PRESTASHOP_IMPORT_PRODUCT){
        $startImport = $prestaImport->startImport();
    }
}

if(INTERTOP){
    $intertop = new IntertopV2();
    $intertop->create($listProducts);
}

if (KASTA){
    $kasta = new KastaV2();

    $grouped =$kasta->grouped($listProducts ) ;

   $kasta->generateDataCreateProducts($grouped);
}
