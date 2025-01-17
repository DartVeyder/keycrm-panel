<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/KeyCrm.php');
require_once ('class/Kasta.php');

echo "<pre>";
$keyCrm = new KeyCrm();
$kasta = new Kasta();
$text = date("Y-m-d H:i:s"). " start cron "  ;
$kasta->saveLog($text , 'logs/cron.txt');

$products = $keyCrm->products();
// $productIds = array_unique(array_column($products, 'product_id')) ;
//$listProductsCustomFields = $keyCrm->listProductsCustomFields('filter[product_id]=' . implode(',', $productIds));
$kasta->listBarcodes();

$inBarcodes = $kasta->listBarcodes();

$items = $kasta->formatDataStock($products,$inBarcodes);

$updateStock = $kasta->updateStock( $items );

$items = $kasta->formatDataPrice($products,$inBarcodes );
print_r($items);
$kasta->updatePrice($items);

echo  $text;
