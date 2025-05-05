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

$generateDataCreateProducts = $kasta->generateDataCreateProducts($grouped);

//$categories = $kasta->categories();
//$categories = array_column($categories['items'], 'kinds','name');
//$categoriesKinds = array_column($categories['Жінкам'], 'kind_id','name');
////dd( $categories['Жінкам'] );
//foreach($categories['Жінкам'] as $category){
//    $kasta = $db->fetchOne("SELECT * FROM kasta WHERE kind  = ?", [$category['name']] );
//
//    if(!$kasta){
//        continue;
//    }
//
//    $analitic_products_stock = $db->fetchOne("SELECT * FROM analitic_products_stock WHERE keycrm_offer_id  = ?", [$kasta['keycrm_offer_id']] );
//
//    $data =  [
//        'affiliation_name' => 'Жінкам',
//        'kind_name' => $category['name'],
//        'affiliation_id' => $category['affiliation_id'],
//        'kind_id' => $category['kind_id'],
//        'keycrm_category_name' => $analitic_products_stock ['category'] ?? null,
//    ] ;
//
//    $db->insert("kasta_kind_keycrm_category", $data );
//}
//
//foreach ($kastaProducts as $barcode => $kastaProduct){
//
//
//      $sku = $kastaProduct['barcode'][0] ??  $kastaProduct['unique_sku_id'];
//      $exists = $db->exists("analitic_products_stock", "sku = ?", [$sku]);
//
//      if($exists){
//          $analitic_products_stock = $db->fetchOne("SELECT * FROM analitic_products_stock WHERE sku  = ?", [$sku] );
//
//          $data = [
//              'keycrm_offer_id' => $analitic_products_stock['keycrm_offer_id'] ?? null,
//              'sku' => $sku,
//              'barcode' =>   $kastaProduct['barcode'][0],
//              'code' =>  $kastaProduct['code'],
//              'name_uk' =>  $kastaProduct['name_uk'],
//              'affiliation' =>  $kastaProduct['affiliation'],
//              'kind' =>  $kastaProduct['kind'],
//              'status' =>  $kastaProduct['status'],
//              'kasta_size_id' =>  $kastaProduct['kasta_size_id'],
//              'hub_sku' =>  $kastaProduct['hub_sku'],
//              'size' =>  $kastaProduct['size'],
//              'color' =>  $kastaProduct['color'],
//              'total_stock' =>  $kastaProduct['total_stock'],
//              'unique_sku_id ' => $kastaProduct['unique_sku_id'] ?? null,
//          ];
//          $db->insert("kasta", $data  );
//      }
//      }
