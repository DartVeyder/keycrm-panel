<?php
set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ERROR);

require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');
require_once('class/KeyCrmV2.php');
require_once('class/MySQLDB.php');

use League\Csv\Reader;

echo "Starting sync...\n";

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

// 1. Fetch KeyCRM
echo "Fetching KeyCRM products via API...\n";
$keyCrm = new KeyCrmV2();
// We can use listProducts, but remember it fetches offers, products, and stocks.
$kcOffers = $keyCrm->listProducts(); 
$kcData = [];
if (is_array($kcOffers)) {
    foreach ($kcOffers as $offer) {
        $sku = trim($offer['sku']);
        if ($sku) {
            $kcData[$sku] = [
                'name' => $offer['product']['name'] ?? '',
                'quantity' => (int)($offer['stock'] ?? 0)
            ];
        }
    }
}
echo "KeyCRM fetched: " . count($kcData) . " items.\n";

// 2. Fetch PrestaShop
echo "Fetching PrestaShop API products...\n";
$prestashop = new Prestashop();
$apiProducts = $prestashop->getApiProducts();
$apiData = [];
if (is_array($apiProducts)) {
    foreach ($apiProducts as $p) {
        $ref = trim($p['combination_reference'] ?? ($p['product_reference'] ?? ''));
        if ($ref) {
            if (!isset($apiData[$ref])) {
                $apiData[$ref] = [];
            }
            $apiData[$ref][] = [
                'id' => $p['product_id'] ?? '',
                'product_reference' => $p['product_reference'] ?? '',
                'name' => $p['product_name'] ?? '',
                'color' => $p['color'] ?? '',
                'size' => $p['size'] ?? '',
                'quantity' => $p['quantity'] ?? 0,
                'price' => $p['price'] ?? 0,
                'status' => $p['product_status'] ?? 0,
                'category' => is_array($p['category_names'] ?? null) ? implode(', ', $p['category_names']) : ($p['category_names'] ?? ($p['main_category'] ?? '')),
                'image' => is_array($p['images'] ?? null) ? ($p['images'][0] ?? '') : ($p['images'] ?? ''),
            ];
        }
    }
}
echo "PrestaShop fetched: " . count($apiData) . " unique SKUs.\n";

// 3. Fetch 1C
echo "Fetching 1C products...\n";
$csvPath = 'uploads/products_1c.csv';
$data1C = [];
if (file_exists($csvPath)) {
    $csv = Reader::createFromPath($csvPath, 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);
    foreach ($csv->getRecords() as $record) {
        $sku = trim($record['SKU'] ?? '');
        if ($sku) {
            if (!isset($data1C[$sku])) {
                $data1C[$sku] = [];
            }
            $data1C[$sku][] = [
                'name' => $record['Name'] ?? ($record['Назва'] ?? ($record['title'] ?? '')),
                'quantity' => $record['Quantity'] ?? 0,
                'price' => $record['Price'] ?? ($record['price'] ?? 0)
            ];
        }
    }
}
echo "1C fetched: " . count($data1C) . " unique SKUs.\n";

// 4. Merge Data
echo "Merging and saving to database...\n";
$allSkus = array_unique(array_merge(array_keys($apiData), array_keys($data1C), array_keys($kcData)));

$dbData = [];
foreach ($allSkus as $sku) {
    $apiList = $apiData[$sku] ?? [];
    $c1List = $data1C[$sku] ?? [];
    $kcInfo = $kcData[$sku] ?? null;

    $apiQtyTotal = 0;
    foreach($apiList as $a) $apiQtyTotal += (int)($a['quantity'] ?? 0);
    
    $c1QtyTotal = 0;
    foreach($c1List as $c) $c1QtyTotal += (int)($c['quantity'] ?? 0);
    
    $kcQty = $kcInfo ? $kcInfo['quantity'] : 0;
    
    $hasDuplicates = count($apiList) > 1;
    $firstApi = $apiList[0] ?? [];
    $firstC1 = $c1List[0] ?? [];
    
    $dbData[] = [
        'sku' => $sku,
        'sync_date' => date('Y-m-d'),
        'product_ref' => $firstApi['product_reference'] ?? null,
        'name_1c' => $firstC1['name'] ?? null,
        'name_site' => $firstApi['name'] ?? null,
        'name_keycrm' => $kcInfo['name'] ?? null,
        'category' => $firstApi['category'] ?? null,
        'size' => $firstApi['size'] ?? null,
        'color' => $firstApi['color'] ?? null,
        'status' => $firstApi['status'] ?? 0,
        'image' => $firstApi['image'] ?? null,
        'qty_site' => $apiQtyTotal,
        'qty_1c' => $c1QtyTotal,
        'qty_keycrm' => $kcQty,
        'price_site' => $firstApi['price'] ?? 0.00,
        'price_1c' => $firstC1['price'] ?? 0.00,
        'has_duplicates' => $hasDuplicates ? 1 : 0,
        'api_details' => $hasDuplicates ? json_encode($apiList, JSON_UNESCAPED_UNICODE) : null
    ];
}

// Переконуємось, що таблиця існує
$db->query("CREATE TABLE IF NOT EXISTS check_products_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) NOT NULL,
    sync_date DATE NOT NULL,
    product_ref VARCHAR(100) NULL,
    name_site VARCHAR(255) NULL,
    name_1c VARCHAR(255) NULL,
    name_keycrm VARCHAR(255) NULL,
    category VARCHAR(255) NULL,
    size VARCHAR(50) NULL,
    color VARCHAR(50) NULL,
    status TINYINT(1) DEFAULT 1,
    qty_site INT DEFAULT 0,
    qty_1c INT DEFAULT 0,
    qty_keycrm INT DEFAULT 0,
    price_site DECIMAL(10,2) NULL,
    price_1c DECIMAL(10,2) NULL,
    image VARCHAR(255) NULL,
    has_duplicates TINYINT(1) DEFAULT 0,
    api_details TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY sku_date (sku, sync_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$syncDate = date('Y-m-d');

// Insert chunks
$chunks = array_chunk($dbData, 500);

foreach ($chunks as $chunk) {
    $db->insertOrUpdateMulti('check_products_cache', $chunk, 'sku');
}

// Автоматичне очищення історії (видаляємо знімки старіші за 30 днів)
$db->query("DELETE FROM check_products_cache WHERE sync_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

echo "Sync complete!\n";
