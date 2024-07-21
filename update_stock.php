<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/KeyCrm.php');
require_once ('class/Kasta.php');

$keyCrm = new KeyCrm();
$kasta = new Kasta();

$products = $keyCrm->products();

$kasta->listBarcodes();

$inBarcodes = $kasta->listBarcodes();
$items = $kasta->formatDataStock($products,$inBarcodes);

$kasta->updateStock( $items );

$items = $kasta->formatDataPrice($products,$inBarcodes);
$kasta->updatePrice($items);
dd($items);
