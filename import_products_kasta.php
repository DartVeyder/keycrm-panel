<?php
set_time_limit(0); // Знімає обмеження часу виконання
ini_set('display_errors', 1);  // Включаємо відображення помилок
error_reporting(E_ERROR);      // Виводимо тільки фатальні помилки
require_once('vendor/autoload.php');

require_once('config.php');

require_once ('class/Base.php');
require_once('class/Prestashop.php');
require_once ('class/KeyCrmV2.php');
require_once ('class/KastaV2.php');
require_once ('class/Rozetka.php');
require_once ('class/MySQLDB.php');

$prestashop = new Prestashop();

$kasta = new KastaV2();

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

$keyCrm = new KeyCrmV2();
$keycrmListProducts = $keyCrm->listProducts(null,5);

$grouped =$kasta->grouped($keycrmListProducts ) ;
$kasta->generateDataCreateProducts($grouped);
