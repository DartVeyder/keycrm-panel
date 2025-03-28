<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Prestashop.php');
require_once ('class/LookSize.php');

$keyCrm = new KeyCrm();
$prestashop = new Prestashop();

$statusPS = [
    2 => 3, //Обробляється
    4 => 4, //Відправлено
    5 => 5, //Виконано
    6 => 6  //Скасовано
];
$orderKC = $keyCrm->webhookOrder();

if($orderKC){

$global_source_uuid = explode('-', $orderKC['global_source_uuid']);

$orderKC_id =  $orderKC['id'];
$orderKC_source_id = $orderKC['source_id'] ;
$idOrder = $global_source_uuid[1];
$orderKS_reference = $global_source_uuid[2];
$groupStatusId = $orderKC['status_group_id'];
$kcClientId =  $orderKC['client_id'];

//$orderKC_id =  100000;
//$orderKC_source_id = 18 ;
//$idOrder = 8002;
//$groupStatusId = 2;
//$orderKS_reference = 'FKQALEIQT';

$idOrderState = $statusPS[$groupStatusId];

if( $orderKC_source_id == 18) {
    $looksize = new LookSize();
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
    $text = date("Y-m-d H:i:s") . " orderKC_id: " . $orderKC_id . " orderPS_id: " . $idOrder . " status_group_id: " . $groupStatusId . " source_id: " . $orderKC_source_id . ' current_state_PS: ' . $orderPS['current_state'];
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
