<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Intertop.php');
require_once ('class/Prestashop.php');

$prestahop = new Prestashop;
$intertop = new Intertop();

$keycrm = new KeyCrm();
dd($keycrm->product('[product_id]=1909'));
dd($keycrm-> listProductsCustomFields());

//$prestahop->products( ['filter[reference]' => '865_39']);
