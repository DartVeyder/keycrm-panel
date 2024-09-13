<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/PrestaImport.php');

$prestaImport = new PrestaImport();

$text = date("Y-m-d H:i:s"). " start cron "  ;
$prestaImport->saveLog($text , 'logs/cron-import.txt');

$prestaImport->generateXLS($prestaImport->generateData());
