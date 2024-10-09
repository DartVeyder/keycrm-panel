<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/KeyCrm.php');
require_once ('class/Intertop.php');


$intertop = new Intertop();
$keyCrm = new KeyCrm();
$intertop->auth();

$intertop->productsKeycrm = $intertop->getProductsKeycrm() ;
$offers = $intertop->getDataToUpdateQuantity() ;

$updateQuantity = $intertop->updateQuantity($offers ) ;
$text = date("Y-m-d H:i:s"). " ". json_encode($updateQuantity) ;
$intertop->saveLog($text , 'logs/update_stoct.txt');

foreach ($offers  as $offer){
    $txt =  $offer['barcode'] . " " .  $offer['article'] . " " .  $offer['quantity'] ;
    echo $txt . "</br>";
    $text = date("Y-m-d H:i:s"). " ". $txt ;
    $intertop->saveLog($text , 'logs/update_stock_products.txt');

}
