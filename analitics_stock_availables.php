<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Prestashop.php');
require_once ('class/Intertop.php');
require_once ('class/Kasta.php');
require_once ('class/Rozetka.php');
require_once ('class/Prom.php');


$prestashop = new Prestashop();
$keyCrm = new KeyCrm();
$kasta = new Kasta();
$intertop = new Intertop();
$rozetka = new Rozetka();
$prom = new Prom();

$promProducts = $prom->products();
$promProducts = array_column($promProducts, 'quantity_in_stock','sku');
$rozetkaProducts = $rozetka->products();
$rozetkaProducts = array_column($rozetkaProducts, 'stock_quantity','article');

$categories = $keyCrm->categories();
$intertopProducts = $intertop->readProductsFromJson('uploads/products.json');
$intertopProducts  = array_column($intertopProducts['data'], 'quantity','barcode');

$kasta_products = $kasta->productsStock();

$kc_products = $keyCrm->products();

$ps_combinations = $prestashop->getCombinations('[id,reference,quantity]');
$ps_combinations = array_column($ps_combinations, 'quantity','reference');
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
echo '<table class="table  table-bordered ">
  <thead>
    <tr>
      <th scope="col">№</th>
      <th scope="col">Артикл</th>
      <th scope="col">Назва</th>
       <th scope="col">Категорія</th>
      <th scope="col">KEYCRM</th>
      <th scope="col">PRESTAHOP</th> 
      <th scope="col">KASTA</th> 
      <th scope="col">INTERTOP</th> 
      <th scope="col">ROZETKA</th> 
      <th scope="col">PROM</th> 
    </tr>
  </thead>
  <tbody>';
    $i = 1;
        foreach ($kc_products as $kc_product){
            if(!$kc_product['sku']){
                continue;
            }

            if ( strpos($kc_product['sku'], '_') !== false) {
                continue;
            }

            if ( strpos($kc_product['sku'], '5555') !== false) {
                continue;
            }

            if ( strpos($kc_product['sku'], 'B24') !== false) {
                continue;
            }

            $stock = (int)$kc_product['quantity'] - (int)$kc_product['in_reserve'];
            echo '  <tr>
      <th scope="row">'.$i++.'</th>
      <th scope="row">'.$kc_product['sku'].'</th>
        <td>'.$kc_product['product']['name'].'</td>
        <td>'. $categories[$kc_product['product']['category_id']].'</td>
      <td>'.  $stock.'</td>
      <td>'.$ps_combinations[$kc_product['sku'] ].'</td> 
      <td>'.$kasta_products[$kc_product['sku'] ].'</td> 
      <td>'.$intertopProducts[$kc_product['sku'] ].'</td> 
      <td>'.$rozetkaProducts[$kc_product['sku'] ].'</td> 
      <td>'.$promProducts[$kc_product['sku'] ].'</td> 
    </tr> ';

        }
echo '  </tbody>
</table>';
