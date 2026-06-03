<?php
require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');

use League\Csv\Reader;

$prestashop = new Prestashop();
$apiProducts = $prestashop->getApiProducts();

// key by reference
$apiData = [];
$apiNames = [];
if (is_array($apiProducts)) {
    foreach ($apiProducts as $p) {
        $pName = trim($p['product_name'] ?? '');
        if ($pName) {
            $apiNames[mb_strtolower($pName)] = true;
        }
        $ref = $p['combination_reference'] ?? ($p['product_reference'] ?? '');
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

// Read 1c
$csvPath = 'uploads/products_1c.csv';
$data1C = [];
if (file_exists($csvPath)) {
    $csv = Reader::createFromPath($csvPath, 'r');
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);
    foreach ($csv->getRecords() as $record) {
        $sku = $record['SKU'] ?? '';
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

$allSkus = array_unique(array_merge(array_keys($apiData), array_keys($data1C)));

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Перевірка товарів</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .table-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .mismatch { background-color: #fff3cd !important; }
        .mismatch-danger { background-color: #f8d7da !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="table-container">
            <h2 class="mb-4">Перевірка товарів (Сайт vs 1C)</h2>
            <div class="mb-3">
                <button class="btn btn-outline-primary filter-btn active" data-filter="all">Всі товари</button>
                <button class="btn btn-outline-danger filter-btn" data-filter="missing-api">Немає на сайті (Є в 1С)</button>
                <button class="btn btn-outline-danger filter-btn" data-filter="missing-1c">Немає в 1С (Є на сайті)</button>
                <button class="btn btn-outline-warning filter-btn" data-filter="diff">Різниця залишків</button>
                <button class="btn btn-outline-info filter-btn" data-filter="dups">Є дублікати</button>
            </div>
            <?php if (!file_exists($csvPath)): ?>
                <div class="alert alert-warning">Файл 1С не знайдено (<?= htmlspecialchars($csvPath) ?>). Будь ласка, завантажте його.</div>
            <?php endif; ?>
            <table id="productsTable" class="table table-striped table-bordered" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 30px; text-align: center;"><i class="bi bi-list"></i></th>
                        <th style="width: 50px;">Фото</th>
                        <th>SKU</th>
                        <th>Product Ref</th>
                        <th>Статус</th>
                        <th>Назва (1C)</th>
                        <th>Категорія</th>
                        <th>Активний</th>
                        <th>Залишок Сайт</th>
                        <th>Залишок 1C</th>
                        <th>Різниця залишків</th>
                        <th>Ціна Сайт</th>
                        <th>Ціна 1C</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSkus as $sku): 
                        $apiList = $apiData[$sku] ?? [];
                        $c1List = $data1C[$sku] ?? [];
                        
                        $apiQtyTotal = 0;
                        foreach($apiList as $a) $apiQtyTotal += (int)($a['quantity'] ?? 0);
                        
                        $c1QtyTotal = 0;
                        foreach($c1List as $c) $c1QtyTotal += (int)($c['quantity'] ?? 0);
                        
                        $diff = $apiQtyTotal - $c1QtyTotal;
                        
                        $rowClass = '';
                        if (empty($apiList) || empty($c1List)) {
                            $rowClass = 'mismatch-danger';
                        } elseif ($diff != 0) {
                            $rowClass = 'mismatch';
                        }
                        
                        $hasDuplicates = count($apiList) > 1;
                        $firstApi = $apiList[0] ?? [];
                        $firstC1 = $c1List[0] ?? [];
                        
                        $btn = '';
                        $dataAttr = '';
                        if ($hasDuplicates) {
                            $btn = '<button class="btn btn-sm btn-outline-secondary toggle-details px-2 py-0 fw-bold">+</button>';
                            $dataAttr = "data-details='" . htmlspecialchars(json_encode($apiList), ENT_QUOTES, 'UTF-8') . "'";
                        }
                    ?>
                    <tr class="<?= $rowClass ?>" <?= $dataAttr ?>
                        data-missing-api="<?= empty($apiList) ? '1' : '0' ?>"
                        data-missing-1c="<?= empty($c1List) ? '1' : '0' ?>"
                        data-diff="<?= $diff != 0 ? '1' : '0' ?>"
                        data-dups="<?= $hasDuplicates ? '1' : '0' ?>"
                    >
                        <td class="details-control text-center align-middle"><?= $btn ?></td>
                        <td class="text-center align-middle">
                            <?php if (!empty($firstApi['image'])): ?>
                                <img src="<?= htmlspecialchars($firstApi['image']) ?>" alt="Фото" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; background: #eee; border-radius: 4px; display: inline-block;"></div>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <?= htmlspecialchars($sku) ?>
                            <?php if ($hasDuplicates): ?>
                                <span class="badge bg-danger ms-1">Дублів Сайт: <?= count($apiList) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle"><?= htmlspecialchars($firstApi['product_reference'] ?? '-') ?></td>
                        <td class="align-middle text-center">
                            <?php
                            if (empty($apiList)) {
                                $c1Name = mb_strtolower(trim($firstC1['name'] ?? ''));
                                if ($c1Name && isset($apiNames[$c1Name])) {
                                    echo '<span class="badge bg-danger">Тільки 1С</span> <br><span class="badge bg-secondary mt-1">Товар є, комбінації нема</span>';
                                } else {
                                    echo '<span class="badge bg-danger">Тільки 1С</span> <br><span class="badge bg-dark mt-1">Товару взагалі нема</span>';
                                }
                            } elseif (empty($c1List)) {
                                echo '<span class="badge bg-warning text-dark">Тільки Сайт</span>';
                            } else {
                                echo '<span class="badge bg-success">Є в обох</span>';
                            }
                            ?>
                        </td>
                        <td class="align-middle"><?= htmlspecialchars(!empty($firstC1['name']) ? $firstC1['name'] : ($firstApi['name'] ?? 'Не знайдено на сайті')) ?></td>
                        <td class="align-middle"><?= htmlspecialchars(!empty($firstApi['category']) ? $firstApi['category'] : '-') ?></td>
                        <td class="align-middle text-center">
                            <?php
                            if (!empty($firstApi)) {
                                if (($firstApi['status'] ?? 0) == 1) {
                                    echo '<span class="badge bg-success">Так</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">Ні</span>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="align-middle fw-bold"><?= htmlspecialchars($apiQtyTotal) ?></td>
                        <td class="align-middle fw-bold"><?= htmlspecialchars($c1QtyTotal) ?></td>
                        <td class="align-middle fw-bold <?= $diff != 0 ? 'text-danger' : 'text-success' ?>"><?= htmlspecialchars($diff) ?></td>
                        <td class="align-middle"><?= htmlspecialchars($firstApi['price'] ?? '-') ?></td>
                        <td class="align-middle"><?= htmlspecialchars($firstC1['price'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready( function () {
            // Custom filtering function
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData, counter) {
                var filter = $('.filter-btn.active').data('filter');
                if (filter === 'all') return true;
                
                var tr = $(settings.aoData[dataIndex].nTr);
                if (filter === 'missing-api') return tr.data('missing-api') == 1;
                if (filter === 'missing-1c') return tr.data('missing-1c') == 1;
                if (filter === 'diff') return tr.data('diff') == 1;
                if (filter === 'dups') return tr.data('dups') == 1;
                
                return true;
            });

            // Clone the thead tr for column filters
            $('#productsTable thead tr').clone(true).addClass('filters').appendTo('#productsTable thead');

            var table = $('#productsTable').DataTable({
                "pageLength": 50,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/uk.json"
                },
                "order": [[ 10, "desc" ]], // Sort by difference (index 10)
                "orderCellsTop": true,
                "fixedHeader": true,
                initComplete: function () {
                    var api = this.api();

                    // For each column
                    api.columns().eq(0).each(function (colIdx) {
                        // Set the header cell to contain the input element
                        var cell = $('.filters th').eq($(api.column(colIdx).header()).index());
                        var title = $.trim($(cell).text());
                        
                        // Don't add filter for the details control column (index 0) or photo (index 1)
                        if (colIdx === 0 || colIdx === 1) {
                            $(cell).html('');
                            return;
                        }

                        // Add a dropdown list for Status column (index 4)
                        if (colIdx === 4) {
                            $(cell).html('<select class="form-select form-select-sm" style="min-width: 105px; font-weight: normal; cursor: pointer;"><option value="">Всі</option><option value="Є в обох">Є в обох</option><option value="Тільки 1С">Тільки 1С</option><option value="Тільки Сайт">Тільки Сайт</option></select>');
                            
                            $('select', $('.filters th').eq($(api.column(colIdx).header()).index()))
                                .off('change')
                                .on('change', function () {
                                    api.column(colIdx).search(
                                        this.value != '' ? this.value : '',
                                        false,
                                        false // exact match basically
                                    ).draw();
                                });
                            return;
                        }

                        // Add a dropdown list for Active column (index 7)
                        if (colIdx === 7) {
                            $(cell).html('<select class="form-select form-select-sm" style="min-width: 70px; font-weight: normal; cursor: pointer;"><option value="">Всі</option><option value="Так">Так</option><option value="Ні">Ні</option></select>');
                            
                            $('select', $('.filters th').eq($(api.column(colIdx).header()).index()))
                                .off('change')
                                .on('change', function () {
                                    api.column(colIdx).search(
                                        this.value != '' ? this.value : '',
                                        false,
                                        false
                                    ).draw();
                                });
                            return;
                        }
                        
                        $(cell).html('<input type="text" class="form-control form-control-sm" placeholder="' + title + '" style="min-width: 70px; font-weight: normal;" />');

                        $('input', $('.filters th').eq($(api.column(colIdx).header()).index()))
                            .off('keyup change')
                            .on('keyup change', function (e) {
                                e.stopPropagation();
                                
                                $(this).attr('title', $(this).val());
                                var cursorPosition = this.selectionStart;
                                
                                api.column(colIdx).search(
                                    this.value != '' ? this.value : '',
                                    false,
                                    true // smart search
                                ).draw();
                                
                                // Maintain focus
                                $(this).focus()[0].setSelectionRange(cursorPosition, cursorPosition);
                            });
                    });
                }
            });

            // Filter button click event
            $('.filter-btn').on('click', function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                table.draw();
            });

            // Add event listener for opening and closing details
            $('#productsTable tbody').on('click', 'td.details-control button', function () {
                var tr = $(this).closest('tr');
                var row = table.row( tr );

                if ( row.child.isShown() ) {
                    // This row is already open - close it
                    row.child.hide();
                    tr.removeClass('shown');
                    $(this).text('+');
                }
                else {
                    // Open this row
                    var detailsStr = tr.attr('data-details');
                    if (detailsStr) {
                        var details = JSON.parse(detailsStr);
                        var html = '<table class="table table-sm table-bordered mt-2 mb-2 bg-light shadow-sm">';
                        html += '<thead class="table-secondary"><tr><th>ID</th><th>Колір</th><th>Розмір</th><th>Назва</th><th>Залишок</th><th>Ціна</th></tr></thead><tbody>';
                        details.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>' + (item.id || '-') + '</td>';
                            html += '<td>' + (item.color || '-') + '</td>';
                            html += '<td>' + (item.size || '-') + '</td>';
                            html += '<td>' + (item.name || '-') + '</td>';
                            html += '<td class="fw-bold">' + item.quantity + '</td>';
                            html += '<td>' + item.price + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                        
                        row.child( html ).show();
                        tr.addClass('shown');
                        $(this).text('-');
                    }
                }
            } );
        } );
    </script>
</body>
</html>
