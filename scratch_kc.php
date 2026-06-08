<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'class/KeyCrmV2.php';

$k = new KeyCrmV2();
$offers = $k->listProducts(null, 1);
print_r($offers[0]);
