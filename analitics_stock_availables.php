<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Prestashop.php');

$prestashop = new Prestashop();

$getStockAvailables = $prestashop->getStockAvailables();
$products = $prestashop->getProducts('[id,reference,quantity]');
$combinations = $prestashop->getCombinations('[id,reference,quantity]');
dd($combinations);
//dd($getStockAvailables['stock_availables']);
