<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Kasta.php');
require_once ('class/Rozetka.php');

$rozetka = new Rozetka();
$kasta = new Kasta();
$kastaProducts  = $kasta->products();
//dd($kastaProducts[67] ,$kastaProducts[68]);
//dd($rozetka->getProducts());
