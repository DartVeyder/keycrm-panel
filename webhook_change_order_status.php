<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/KeyCrm.php');

$keyCrm = new KeyCrm();

$keyCrm->webhookOrder();