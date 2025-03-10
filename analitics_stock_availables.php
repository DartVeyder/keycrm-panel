<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once ('class/Base.php');
require_once ('class/MySQLDB.php');
require_once ('class/KeyCrm.php');
require_once ('class/Prestashop.php');
require_once ('class/Intertop.php');
require_once ('class/Kasta.php');
require_once ('class/Rozetka.php');
require_once ('class/Prom.php');


$prestashop = new Prestashop();
$kasta = new Kasta();
$intertop = new Intertop();
$rozetka = new Rozetka();
$prom = new Prom();
$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

$promProducts = $prom->products();
$promProducts = array_column($promProducts, 'quantity_in_stock','sku');
$rozetkaProducts = $rozetka->products();
$rozetkaProducts = array_column($rozetkaProducts, 'stock_quantity','article');

$intertopProducts = $intertop->readProductsFromJson('uploads/products.json');
$intertopProducts  = array_column($intertopProducts['data'], 'quantity','barcode');

$kasta_products = $kasta->productsStock();


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

// Отримуємо значення фільтрів з GET-запиту
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$nameFilter = isset($_GET['name']) ? trim($_GET['name']) : '';
$stockFilter = isset($_GET['keycrm_stock']) ? (int)$_GET['keycrm_stock'] : 0;
$skuFilter = isset($_GET['sku']) ? trim($_GET['sku']) : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name_asc'; // Сортування за замовчуванням

// Формуємо SQL-запит із фільтрами
$sql = 'SELECT * FROM products WHERE 1=1';
$params = [];

if (!empty($categoryFilter)) {
    $sql .= ' AND category = ?';
    $params[] = $categoryFilter;
}

if (!empty($nameFilter)) {
    $sql .= ' AND name LIKE ?';
    $params[] = "%$nameFilter%";
}

if ($stockFilter > 0) {
    $sql .= ' AND keycrm_stock >= ?';
    $params[] = $stockFilter;
}

if (!empty($skuFilter)) {
    $sql .= ' AND sku LIKE ?';
    $params[] = "%$skuFilter%";
}

// Додаємо сортування
$sortOptions = [
    'name_asc' => 'name ASC',
    'name_desc' => 'name DESC',
    'sku_asc' => 'sku ASC',
    'sku_desc' => 'sku DESC',
    'stock_asc' => 'keycrm_stock ASC',
    'stock_desc' => 'keycrm_stock DESC',
];

$sql .= ' ORDER BY ' . ($sortOptions[$sortBy] ?? 'name ASC');

// Виконуємо запит
$products = $db->fetchAll($sql, $params);

// Отримуємо унікальні категорії та сортуємо їх за алфавітом
$categories = $db->fetchAll('SELECT DISTINCT category FROM products ORDER BY category ASC');


// Форма фільтрів + сортування
echo '<form method="GET" action="">
    <div class="form-group">
        <label for="categoryFilter">Фільтр за категорією:</label>
        <select name="category" id="categoryFilter" class="form-control">
            <option value="">Всі категорії</option>';

foreach ($categories as $cat) {
    $selected = ($cat['category'] == $categoryFilter) ? 'selected' : '';
    echo '<option value="'.$cat['category'].'" '.$selected.'>'.$cat['category'].'</option>';
}

echo '</select>
    </div>';

echo '</select>
    </div>
    
    <div class="form-group">
        <label for="nameFilter">Фільтр за назвою:</label>
        <input type="text" name="name" id="nameFilter" class="form-control" value="'.$nameFilter.'">
    </div>

    <div class="form-group">
        <label for="skuFilter">Фільтр за артикулом (SKU):</label>
        <input type="text" name="sku" id="skuFilter" class="form-control" value="'.$skuFilter.'">
    </div>

    <div class="form-group">
        <label for="stockFilter">Мінімальний KeyCRM stock:</label>
        <input type="number" name="keycrm_stock" id="stockFilter" class="form-control" value="'.$stockFilter.'" min="0">
    </div>

    <div class="form-group">
        <label for="sortBy">Сортувати за:</label>
        <select name="sort_by" id="sortBy" class="form-control">
            <option value="name_asc" '.($sortBy == 'name_asc' ? 'selected' : '').'>Назва (А → Я)</option>
            <option value="name_desc" '.($sortBy == 'name_desc' ? 'selected' : '').'>Назва (Я → А)</option>
            <option value="sku_asc" '.($sortBy == 'sku_asc' ? 'selected' : '').'>Артикул (↑)</option>
            <option value="sku_desc" '.($sortBy == 'sku_desc' ? 'selected' : '').'>Артикул (↓)</option>
            <option value="stock_asc" '.($sortBy == 'stock_asc' ? 'selected' : '').'>KeyCRM Stock (↑)</option>
            <option value="stock_desc" '.($sortBy == 'stock_desc' ? 'selected' : '').'>KeyCRM Stock (↓)</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Фільтрувати</button>
    <a href="?" class="btn btn-secondary">Скинути</a>
</form>';

    $i = 1;
        foreach ($products as $kc_product){
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

            echo '  <tr>
      <th scope="row">'.$i++.'</th>
      <th scope="row">'.$kc_product['sku'].'</th>
        <td>'.$kc_product['name'].'</td>
        <td>'. $kc_product['category'].'</td>
      <td>'. $kc_product['keycrm_stock'].'</td>
      <td>'.$ps_combinations[$kc_product['sku'] ].'</td> 
      <td>'.$kasta_products[$kc_product['sku'] ].'</td> 
      <td>'.$intertopProducts[$kc_product['sku'] ].'</td> 
      <td>'.$rozetkaProducts[$kc_product['sku'] ].'</td> 
      <td>'.$promProducts[$kc_product['sku'] ].'</td> 
    </tr> ';

        }
echo '  </tbody>
</table>';
