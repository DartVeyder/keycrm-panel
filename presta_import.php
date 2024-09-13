<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/PrestaImport.php');

$prestaImport = new PrestaImport();


$prestaImport->generateXLS($prestaImport->generateData());
