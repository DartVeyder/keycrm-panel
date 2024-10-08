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
 dd($intertop->updateQuantity($offers ));
