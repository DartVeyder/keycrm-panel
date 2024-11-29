<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Intertop.php');
require_once ('class/Prestashop.php');

$intertop = new Intertop();
$keyCrm = new KeyCrm();
$intertop->auth();

$intertop->productsKeycrm = $intertop->getProductsKeycrm() ;
$offers = $intertop->getDataToUpdateQuantity() ;
$groups = array_chunk($offers, 1000);
$date  = date("Y-m-d H:i:s");
foreach ($groups  as $group){
    $updateQuantity = $intertop->updateQuantity($group ) ;
    $response[] =  $updateQuantity;
}
$log[$date] = $response;
$text =  json_encode($log ,JSON_UNESCAPED_UNICODE) ;
$intertop->saveLog($text , 'logs/update_product_stock_intertop.txt');

foreach ($offers  as $offer){
    $txt =  $offer['barcode'] . " " .  $offer['article'] . " " .  $offer['quantity'] ;
    $text = date("Y-m-d H:i:s"). " ". $txt ;
    $intertop->saveLog($text , 'logs/update_stock_products_intertop.txt');

}
