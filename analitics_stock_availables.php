<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/KeyCrm.php');
require_once ('class/Prestashop.php');
require_once ('class/Kasta.php');
$prestashop = new Prestashop();
$keyCrm = new KeyCrm();
$kasta = new Kasta();


$kasta_products = $kasta->productsStock();

$kc_products = $keyCrm->products();

$ps_combinations = $prestashop->getCombinations('[id,reference,quantity]');
$ps_combinations = array_column($ps_combinations, 'quantity','reference');
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">';
echo '<table class="table">
  <thead>
    <tr>
      <th scope="col">Артикл</th>
      <th scope="col">Назва</th>
      <th scope="col">KEYCRM</th>
      <th scope="col">PRESTAHOP</th> 
      <th scope="col">KASTA</th> 
    </tr>
  </thead>
  <tbody>';
        foreach ($kc_products as $kc_product){
            if(!$kc_product['sku']){
                continue;
            }

            $stock = (int)$kc_product['quantity'] - (int)$kc_product['in_reserve'];
            echo '  <tr>
      <th scope="row">'.$kc_product['sku'].'</th>
        <td>'.$kc_product['product']['name'].'</td>
      <td>'.  $stock.'</td>
      <td>'.$ps_combinations[$kc_product['sku'] ].'</td> 
      <td>'.$kasta_products[$kc_product['sku'] ].'</td> 
    </tr> ';

        }
echo '  </tbody>
</table>';
