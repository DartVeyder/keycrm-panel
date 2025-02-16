<?php
set_time_limit(0);
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Intertop.php');
require_once ('class/Prestashop.php');

$intertop = new Intertop();
$keyCrm = new KeyCrm();
$intertop->auth();

//dd($intertop->updateQuantity([
//[
//    "barcode" => "2501220074205",
//    "quantity"=> 2,
//    "warehouse_external_id"=>"default",
//    'base_price'=>
//        [
//            "amount"=>"1396",
//            "currency"=> "UAH"
//        ],
//        'discount_price'=>
//            [
//                "amount"=>"1391",
//                "currency"=> "UAH"
//            ]
//]
//]));
// $intertopProducts = $intertop->getProducts() ;

$intertop->productsKeycrm = $intertop->getProductsKeycrm() ;

$offers = $intertop->getDataToUpdateQuantity() ;

$groups = array_chunk($offers, 1000);
$date  = date("Y-m-d H:i:s");
foreach ($groups  as $group){
    $updateQuantity = $intertop->updateQuantity($group ) ;
    $updatePrice = $intertop->updatePrice($group ) ;
    $response['Quantity'] =  $updateQuantity;
    $response['Price'] =  $updatePrice;
}
$log[$date] = $response;
$text =  json_encode($log ,JSON_UNESCAPED_UNICODE) ;
$intertop->saveLog($text , 'logs/update_product_stock_intertop.txt');

foreach ($offers  as $offer){
    $txt =  $offer['barcode'] . " " .  $offer['article'] . " " .  $offer['quantity'] ;
    $text = date("Y-m-d H:i:s"). " ". $txt ;
    $intertop->saveLog($text , 'logs/update_stock_products_intertop.txt');
}
