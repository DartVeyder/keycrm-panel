<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/LookSize.php');

$looksize = new LookSize();
$keyCrm = new KeyCrm();

$orderKS_reference = 'FKQALEIQT';
$orderKC_id =  122614;

$orderLS = $looksize->getOrder($orderKS_reference);

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
