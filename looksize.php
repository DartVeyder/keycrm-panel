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

if($orderLS['list']){
    $keyCrm->addTagOrder($orderKC_id,265);
}
