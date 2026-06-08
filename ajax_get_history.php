<?php
require_once('config.php');
require_once('class/MySQLDB.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['sku']) || empty($_POST['sku'])) {
    echo json_encode(['error' => 'SKU is required']);
    exit;
}

$sku = $_POST['sku'];

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

$history = $db->fetchAll(
    "SELECT sync_date, qty_site, qty_1c, qty_keycrm, price_site, price_1c, name_site, name_1c, name_keycrm 
     FROM check_products_cache 
     WHERE sku = ? 
     ORDER BY sync_date DESC", 
    [$sku]
);

if (empty($history)) {
    echo json_encode(['error' => 'No history found']);
    exit;
}

// Generate HTML for the modal/sub-row
$html = '<table class="table table-sm table-bordered mt-2 mb-2 bg-white shadow-sm">';
$html .= '<thead class="table-light"><tr>
            <th>Дата перевірки</th>
            <th>Назва (Сайт / 1С / KeyCRM)</th>
            <th>Сайт</th>
            <th>1С</th>
            <th>KeyCRM</th>
          </tr></thead><tbody>';

foreach ($history as $row) {
    $dateFormatted = date('d.m.Y', strtotime($row['sync_date']));
    $qtySite = (int)$row['qty_site'];
    $qty1c = (int)$row['qty_1c'];
    $qtyKc = (int)$row['qty_keycrm'];
    
    $diff = $qtySite - $qty1c;
    $diffKc = $qtySite - $qtyKc;
    
    $siteClass = ($diff != 0 || $diffKc != 0) ? 'fw-bold' : '';
    $c1Class = ($diff != 0) ? 'fw-bold text-danger' : '';
    $kcClass = ($diffKc != 0) ? 'fw-bold text-danger' : '';
    
    $nameSite = $row['name_site'] ?: '-';
    $name1c = $row['name_1c'] ?: '-';
    $nameKc = $row['name_keycrm'] ?: '-';
    
    $nameHtml = "<small class='text-muted'>Сайт:</small> " . htmlspecialchars($nameSite) . "<br>";
    $nameHtml .= "<small class='text-muted'>1С:</small> " . htmlspecialchars($name1c) . "<br>";
    $nameHtml .= "<small class='text-muted'>CRM:</small> " . htmlspecialchars($nameKc);

    $html .= '<tr>';
    $html .= '<td class="align-middle fw-bold">' . $dateFormatted . '</td>';
    $html .= '<td class="align-middle">' . $nameHtml . '</td>';
    $html .= '<td class="align-middle ' . $siteClass . '">' . $qtySite . '</td>';
    $html .= '<td class="align-middle ' . $c1Class . '">' . $qty1c . '</td>';
    $html .= '<td class="align-middle ' . $kcClass . '">' . $qtyKc . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

echo json_encode(['html' => $html]);
