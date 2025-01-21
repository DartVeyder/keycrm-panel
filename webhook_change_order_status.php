<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Prestashop.php');
$keyCrm = new KeyCrm();
$prestashop = new Prestashop();

$statusPS = [
    2 => 3, //Обробляється
    4 => 4, //Відправлено
    5 => 5, //Виконано
    6 => 6  //Скасовано
];
$orderKC = $keyCrm->webhookOrder();



$global_source_uuid = explode('-', $orderKC['global_source_uuid']);

//$orderKC_id =  $orderKC['id'];
//$orderKC_source_id = $orderKC['source_id'] ;
//$idOrder = $global_source_uuid[1];
//$groupStatusId = $orderKC['status_group_id'];

$orderKC_id =  100000;
$orderKC_source_id = 18 ;
$idOrder = 8392;
$groupStatusId = 2;

$idOrderState = $statusPS[$groupStatusId];



if( $orderKC_source_id == 18) {
    $orderPS = $prestashop->getOrder($idOrder);
    $text = date("Y-m-d H:i:s") . " orderKC_id: " . $orderKC_id . " orderPS_id: " . $idOrder . " status_group_id: " . $groupStatusId . " source_id: " . $orderKC_source_id . ' current_state_PS: ' . $orderPS['current_state'];
    echo $text;
    $file = fopen('logs/changeOrderStatus.txt', 'a+');
    fwrite($file, $text . "\n");
    fclose($file);

    if ($orderPS['current_state'] != $idOrderState) {
        $prestashop->changeOrderStatus($idOrder, $idOrderState);
    }
}
