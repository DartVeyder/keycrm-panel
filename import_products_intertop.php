<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrmV2.php');
require_once ('class/IntertopV2.php');
require_once ('class/Prestashop.php');

$prestahop = new Prestashop;
$keyCrm = new KeyCrmV2();
$listProducts = $keyCrm->listProducts();

$intertop = new IntertopV2();

$intertop->create($listProducts);
