<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrmV2.php');
require_once ('class/LookSize.php');

$looksize = new LookSize();
$keyCrm = new KeyCrmV2();
$orderKC_id =  183841;
$orderKS = $keyCrm->order(183841);

$custom_fields  = array_column($orderKS['custom_fields'], 'value', 'id');
 
$orderKS_reference = $custom_fields[36];


$orderLS = $looksize->getOrder($orderKS_reference);
dd($orderLS);
$action_key = end($orderLS['list'])['action_key'] ;

$looksize->getDataByKey($action_key);

//$keyCrmData = $looksize->getSizesClient();
$keyCrmData = $looksize->getSizesClientByOrder();

//$response = $keyCrm->updateClient(109712,$keyCrmData);
 $response = $keyCrm->updateOrder($orderKC_id,$keyCrmData);


dd($keyCrmData,$response );

//
//if($orderLS['list']){
//    $keyCrm->addTagOrder($orderKC_id,265);
//}
