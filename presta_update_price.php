<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/PrestaImport.php');

$prestaImport = new PrestaImport();

$text = date("Y-m-d H:i:s"). " start cron "  ;


$prestaImport->generateXLS($prestaImport->generateData(),'uploads/output_presta_price.xlsx', 'price');
if(PRESTASHOP_UPDATE_PRICE){
    $startImport = $prestaImport->startUpdatePrice();
    $prestaImport->saveLog($text . " " . $startImport , 'logs/cron-prestashop-update-price.txt');
}
