<?php
set_time_limit(0); // Знімає обмеження часу виконання

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/PrestaImportV2.php');
require_once ('class/KeyCrmV2.php');

$keyCrm = new KeyCrmV2();
$prestaImport = new PrestaImportV2();

 $prestaImport->generateListProductsXLSX($keyCrm->listProducts(), 'uploads/prestashop_import_products.xlsx');
