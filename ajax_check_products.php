<?php
require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/MySQLDB.php');

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 50;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

$customFilters = isset($_POST['custom_filters']) && is_array($_POST['custom_filters']) ? $_POST['custom_filters'] : ['all'];

// Columns definition mapping to DB fields
$columns = [
    0 => null, // toggle
    1 => 'image',
    2 => 'sku',
    3 => 'product_ref',
    4 => 'status', // special logic
    5 => 'name_1c', // or name_site or name_keycrm
    6 => 'category',
    7 => 'size',
    8 => 'color',
    9 => 'status', // active
    10 => 'qty_site',
    11 => 'qty_1c',
    12 => 'qty_keycrm',
    13 => 'diff', // special logic qty_site - qty_1c
    14 => 'diff_keycrm', // special logic qty_site - qty_keycrm
    15 => 'price_site',
    16 => 'price_1c'
];

$orderColIdx = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 13;
$orderDir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';

$orderBy = "qty_site - qty_1c"; // Default order by diff
if (isset($columns[$orderColIdx]) && $columns[$orderColIdx]) {
    if ($orderColIdx == 13) {
        $orderBy = "(qty_site - qty_1c)";
    } elseif ($orderColIdx == 14) {
        $orderBy = "(qty_site - qty_keycrm)";
    } elseif ($orderColIdx == 5) {
        $orderBy = "COALESCE(name_1c, name_site, name_keycrm)";
    } else {
        $orderBy = "`" . $columns[$orderColIdx] . "`";
    }
}

// Build WHERE clause
$where = [];
$params = [];

// Get the latest sync date
$maxDateQuery = $db->fetchOne("SELECT MAX(sync_date) as max_date FROM check_products_cache");
$maxDate = $maxDateQuery['max_date'] ?? date('Y-m-d');
$where[] = "sync_date = '$maxDate'";

if ($search !== '') {
    $where[] = "(sku LIKE ? OR product_ref LIKE ? OR name_1c LIKE ? OR name_site LIKE ? OR name_keycrm LIKE ? OR category LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}

// Column specific searches
if (isset($_POST['columns']) && is_array($_POST['columns'])) {
    foreach ($_POST['columns'] as $i => $col) {
        if (isset($col['search']['value']) && $col['search']['value'] !== '') {
            $val = $col['search']['value'];
            
            if ($i == 2) { $where[] = "sku LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 3) { $where[] = "product_ref LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 4) { // Status (Всі, Є в обох, Тільки 1С, Тільки Сайт)
                if ($val == 'Є в обох') {
                    $where[] = "name_site IS NOT NULL AND name_1c IS NOT NULL";
                } elseif ($val == 'Тільки 1С') {
                    $where[] = "name_site IS NULL AND name_1c IS NOT NULL";
                } elseif ($val == 'Тільки Сайт') {
                    $where[] = "name_site IS NOT NULL AND name_1c IS NULL";
                }
            }
            elseif ($i == 5) { $where[] = "(name_1c LIKE ? OR name_site LIKE ? OR name_keycrm LIKE ?)"; $params = array_merge($params, ["%$val%", "%$val%", "%$val%"]); }
            elseif ($i == 6) { $where[] = "category LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 7) { $where[] = "size LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 8) { $where[] = "color LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 9) { // Active (Так, Ні)
                if ($val == 'Так') { $where[] = "status = 1"; }
                elseif ($val == 'Ні') { $where[] = "status = 0"; }
            }
            elseif ($i == 10) { $where[] = "qty_site LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 11) { $where[] = "qty_1c LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 12) { $where[] = "qty_keycrm LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 13) { $where[] = "(qty_site - qty_1c) LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 14) { $where[] = "(qty_site - qty_keycrm) LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 15) { $where[] = "price_site LIKE ?"; $params[] = "%$val%"; }
            elseif ($i == 16) { $where[] = "price_1c LIKE ?"; $params[] = "%$val%"; }
        }
    }
}

// Custom filters
if (!in_array('all', $customFilters)) {
    foreach ($customFilters as $filter) {
        if ($filter === 'missing-api') {
            $where[] = "name_site IS NULL";
        } elseif ($filter === 'missing-1c') {
            $where[] = "name_1c IS NULL";
        } elseif ($filter === 'missing-keycrm') {
            $where[] = "name_keycrm IS NULL";
        } elseif ($filter === 'diff') {
            $where[] = "(qty_site - qty_1c) != 0";
        } elseif ($filter === 'diff-keycrm') {
            $where[] = "(qty_site - qty_keycrm) != 0";
        } elseif ($filter === 'dups') {
            $where[] = "has_duplicates = 1";
        } elseif ($filter === 'samples') {
            $where[] = "product_type = 'sample'";
        } elseif ($filter === 'no-samples') {
            $where[] = "product_type != 'sample'";
        } elseif ($filter === 'defects') {
            $where[] = "product_type = 'defect'";
        } elseif ($filter === 'no-defects') {
            $where[] = "product_type != 'defect'";
        }
    }
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

// Get total records without filter
$totalQuery = $db->fetchOne("SELECT COUNT(*) as cnt FROM check_products_cache");
$recordsTotal = (int)($totalQuery['cnt'] ?? 0);

// Get total records with filter
if (!empty($whereSql)) {
    $totalFilteredQuery = $db->fetchOne("SELECT COUNT(*) as cnt FROM check_products_cache $whereSql", $params);
    $recordsFiltered = $totalFilteredQuery['cnt'];
} else {
    $recordsFiltered = $recordsTotal;
}

// Fetch paginated data
$sql = "SELECT * FROM check_products_cache $whereSql ORDER BY $orderBy $orderDir LIMIT $start, $length";
$data = $db->fetchAll($sql, $params);

// Format data for DataTables
$response = [];
foreach ($data as $row) {
    $sku = $row['sku'];
    $apiQtyTotal = (int)$row['qty_site'];
    $c1QtyTotal = (int)$row['qty_1c'];
    $kcQty = (int)$row['qty_keycrm'];
    $diff = $apiQtyTotal - $c1QtyTotal;
    $diffKc = $apiQtyTotal - $kcQty;
    
    $isMissingApi = $row['name_site'] === null;
    $isMissing1c = $row['name_1c'] === null;
    $isMissingKc = $row['name_keycrm'] === null;
    
    $rowClass = '';
    if ($isMissingApi || $isMissing1c || $isMissingKc) {
        $rowClass = 'mismatch-danger';
    } elseif ($diff != 0 || $diffKc != 0) {
        $rowClass = 'mismatch';
    }
    
    $hasDuplicates = $row['has_duplicates'] == 1;
    $apiList = $row['api_details'] ? json_decode($row['api_details'], true) : [];
    
    $btn = '';
    $dataAttrDetails = '';
    if ($hasDuplicates && !empty($apiList)) {
        $btn = '<button class="btn btn-sm btn-outline-secondary toggle-details px-2 py-0 fw-bold">+</button>';
        $dataAttrDetails = json_encode($apiList, JSON_UNESCAPED_UNICODE);
    }
    
    $imageHtml = '';
    if (!empty($row['image'])) {
        $imageHtml = '<img src="' . htmlspecialchars($row['image']) . '" alt="Фото" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">';
    } else {
        $imageHtml = '<div style="width: 40px; height: 40px; background: #eee; border-radius: 4px; display: inline-block;"></div>';
    }

    $statusHtml = '';
    if ($isMissingApi && $isMissing1c) {
        $statusHtml = '<span class="badge bg-info text-dark">Тільки KeyCRM</span>';
    } elseif ($isMissingApi) {
        $statusHtml = '<span class="badge bg-danger">Тільки 1С</span> <br><span class="badge bg-dark mt-1">Товару нема на сайті</span>';
    } elseif ($isMissing1c) {
        $statusHtml = '<span class="badge bg-warning text-dark">Тільки Сайт</span>';
    } else {
        $statusHtml = '<span class="badge bg-success">Є в обох</span>';
    }
    
    $activeHtml = '';
    if (!$isMissingApi) {
        if ($row['status'] == 1) {
            $activeHtml = '<span class="badge bg-success">Так</span>';
        } else {
            $activeHtml = '<span class="badge bg-secondary">Ні</span>';
        }
    } else {
        $activeHtml = '-';
    }
    
    $prodType = $row['product_type'] ?? 'regular';
    $typeBadge = '';
    if ($prodType === 'defect') {
        $typeBadge = '<span class="badge bg-dark ms-1" style="font-size:0.7rem;" title="Дефект">88</span>';
    } elseif ($prodType === 'sample') {
        $typeBadge = '<span class="badge bg-secondary ms-1" style="font-size:0.7rem;" title="Взірець">ВЗ</span>';
    } else {
        $typeBadge = '<span class="badge bg-light text-secondary border ms-1" style="font-size:0.7rem;" title="Звичайний товар">ЗВ</span>';
    }

    $skuHtml = '<strong>' . htmlspecialchars($sku) . '</strong> ' . $typeBadge;
    if ($hasDuplicates) {
        $skuHtml .= '<br><span class="badge bg-danger mt-1">Дублів Сайт: ' . count($apiList) . '</span>';
    }
    
    $name = !empty($row['name_1c']) ? $row['name_1c'] : (!empty($row['name_site']) ? $row['name_site'] : ($row['name_keycrm'] ?? 'Не знайдено'));
    
    $diffClass = $diff != 0 ? 'text-danger' : 'text-success';
    $diffKcClass = $diffKc != 0 ? 'text-danger' : 'text-success';
    
    $historyBtn = '<button class="btn btn-sm btn-outline-primary load-history px-2 py-0" title="Історія залишків"><i class="bi bi-clock-history"></i></button>';

    $rowData = [
        "DT_RowClass" => $rowClass,
        "DT_RowAttr" => [
            "data-sku" => $sku,
            "data-missing-api" => $isMissingApi ? '1' : '0',
            "data-missing-1c" => $isMissing1c ? '1' : '0',
            "data-missing-keycrm" => $isMissingKc ? '1' : '0',
            "data-diff" => $diff != 0 ? '1' : '0',
            "data-diff-keycrm" => $diffKc != 0 ? '1' : '0',
            "data-dups" => $hasDuplicates ? '1' : '0',
            "data-details" => $dataAttrDetails
        ],
        0 => '<div class="text-center align-middle d-flex justify-content-center gap-1">' . $btn . $historyBtn . '</div>',
        1 => '<div class="text-center align-middle">' . $imageHtml . '</div>',
        2 => '<div class="align-middle">' . $skuHtml . '</div>',
        3 => '<div class="align-middle">' . htmlspecialchars($row['product_ref'] ?? '-') . '</div>',
        4 => '<div class="align-middle text-center">' . $statusHtml . '</div>',
        5 => '<div class="align-middle">' . htmlspecialchars($name) . '</div>',
        6 => '<div class="align-middle">' . htmlspecialchars($row['category'] ?? '-') . '</div>',
        7 => '<div class="align-middle">' . htmlspecialchars($row['size'] ?? '-') . '</div>',
        8 => '<div class="align-middle">' . htmlspecialchars($row['color'] ?? '-') . '</div>',
        9 => '<div class="align-middle text-center">' . $activeHtml . '</div>',
        10 => '<div class="align-middle fw-bold">' . htmlspecialchars($apiQtyTotal) . '</div>',
        11 => '<div class="align-middle fw-bold">' . htmlspecialchars($c1QtyTotal) . '</div>',
        12 => '<div class="align-middle fw-bold text-primary">' . htmlspecialchars($kcQty) . '</div>',
        13 => '<div class="align-middle fw-bold ' . $diffClass . '">' . htmlspecialchars($diff) . '</div>',
        14 => '<div class="align-middle fw-bold ' . $diffKcClass . '">' . htmlspecialchars($diffKc) . '</div>',
        15 => '<div class="align-middle">' . htmlspecialchars($row['price_site'] ?? '-') . '</div>',
        16 => '<div class="align-middle">' . htmlspecialchars($row['price_1c'] ?? '-') . '</div>'
    ];
    $response[] = $rowData;
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $response
], JSON_UNESCAPED_UNICODE);
