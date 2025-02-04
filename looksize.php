<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/LookSize.php');

$looksize = new LookSize();
$keyCrm = new KeyCrm();

$keyCrm->addTagOrder(122614,265);

dd($looksize->getOrder('FKQALEIQT'));
