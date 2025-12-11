<?php

require_once('vendor/autoload.php');

require_once('config.php');
include('../../config/config.inc.php');
include('../../init.php');
include('../../config/functions.php');
include('../../config/header.inc.php');
require_once ('class/Base.php');
require_once ('class/KeyCrmV2.php');
require_once ('class/Prestashop.php');
require_once ('class/LookSize.php');

$keyCrm = new KeyCrmV2();
$prestashop = new Prestashop();

$statusPS = [
    2 => 3, //Обробляється 
    5 => 28, //Виконано
    6 => 6  //Скасовано
];



$orderKC = $keyCrm->webhookOrder();

//$order = $keyCrm->order(211443);



if($orderKC){

$global_source_uuid = explode('-', $orderKC['global_source_uuid']);

$orderKC_id =  $orderKC['id'];
$orderKC_source_id = $orderKC['source_id'] ;
$idOrder = $global_source_uuid[1];
$orderKS_reference = $global_source_uuid[2];
$groupStatusId = $orderKC['status_group_id'];
$orderStatusId = $orderKC['status_id'];
$kcClientId =  $orderKC['client_id'];

//$orderKC_id =  100000;
//$orderKC_source_id = 18 ;
//$idOrder = 8002;
//$groupStatusId = 2;
//$orderKS_reference = 'FKQALEIQT';

if($groupStatusId == 4 && $orderStatusId == 10 ){
    $statusPS[4] = 4;  //в дорозі
}else if($groupStatusId == 4 && $orderStatusId == 20 ){
    $statusPS[4] = 5;
}


$idOrderState = $statusPS[$groupStatusId];

if(UPDATE_STOCK_PRICE_CHANGE_STATUS){
    if($orderStatusId == 4 ){
        $order = $keyCrm->order($orderKC_id);
        $product_ids = '';
        if($order){
            $product_ids = implode(',',array_column(array_column($order['products'], 'offer'), 'product_id') ) ;
            include ('update_products_price_stock.php');
        }
    } 
}
 

switch ( $orderStatusId ) {
case 34:  
    $order = $keyCrm->order($orderKC_id);
    include ('refund.php');
    break;
}

if( $orderKC_source_id == 18) {
    $looksize = new LookSize();
    $orderKS = $keyCrm->order($orderKC_id);
    if($orderStatusId == 10 ){
        $prestashop->addTrackingNumber((int)$idOrder, $orderKS['shipping']['tracking_code']);
    }

    $custom_fields  = array_column($orderKS['custom_fields'], 'value', 'id');
    
    $orderKS_reference = $custom_fields[36];
    $orderLS = $looksize->getOrder($orderKS_reference);
    if($orderLS['list']){
        $keyCrm->addTagOrder($orderKC_id,265);
        $action_key = end($orderLS['list'])['action_key'] ;
        $looksize->getDataByKey($action_key);
        $getSizesClient = $looksize->getSizesClient();
        if( $getSizesClient ){
            $response = $keyCrm->updateClient($kcClientId,$getSizesClient);
        }

        $getSizesClientByOrder = $looksize->getSizesClientByOrder();
        if( $getSizesClientByOrder ) {
            $response = $keyCrm->updateOrder($orderKC_id, $getSizesClientByOrder);
        }

    }

    $orderPS = $prestashop->getOrder((int)$idOrder);
    $text = date("Y-m-d H:i:s") . " orderKC_id: " . $orderKC_id . " orderPS_id: " . $idOrder . " status_group_id: " . $groupStatusId . " source_id: " . $orderKC_source_id . ' current_state_PS: ' . $orderPS['current_state'] . 'order_status_id: '.$orderStatusId.  ' productIDS: '.  $product_ids ;
    echo $text;
    $file = fopen('logs/changeOrderStatus.txt', 'a+');
    fwrite($file, $text . "\n");
    fclose($file);
    if( $orderPS){
        if ($orderPS['current_state'] != $idOrderState) {
            $prestashop->changeOrderStatus($idOrder, $idOrderState);
        }
    }

}

}
