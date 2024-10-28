<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/Prestashop.php');

$prestahop = new Prestashop;

$prestahop->products( ['filter[reference]' => '865_39']);
