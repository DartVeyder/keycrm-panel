<?php
require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/Base.php');
require_once('class/Prestashop.php');
require_once('class/MySQLDB.php');

use League\Csv\Reader;

$db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

$lastUpdateQuery = $db->fetchOne("SELECT MAX(updated_at) as last_update FROM check_products_cache");
$lastUpdate = $lastUpdateQuery['last_update'] ?? 'Ніколи';
if ($lastUpdate !== 'Ніколи') {
    $lastUpdate = date('d.m.Y H:i', strtotime($lastUpdate));
}

$csvPath = 'uploads/products_1c.csv';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Перевірка товарів</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Перевірка товарів (Сайт vs 1C vs KeyCRM)</h2>
                <div class="text-end">
                    <span class="text-muted small me-3"><i class="bi bi-clock-history"></i> Оновлено: <strong><?= $lastUpdate ?></strong></span>
                    <button class="btn btn-success" id="btnUpdateData">
                        <i class="bi bi-arrow-clockwise"></i> Синхронізувати
                    </button>
                </div>
            </div>

            <div class="card mb-4 shadow-sm border-0 bg-light">
                <div class="card-body pb-2 pt-3">
                    <div class="btn-toolbar mb-0" role="toolbar">
                        <div class="btn-group btn-group-sm me-3 mb-2" role="group">
                            <button class="btn btn-outline-primary filter-btn active fw-bold" data-filter="all">Всі товари</button>
                        </div>
                        
                        <div class="btn-group btn-group-sm me-3 mb-2" role="group">
                            <button class="btn btn-outline-danger filter-btn" data-filter="missing-api">Немає на сайті</button>
                            <button class="btn btn-outline-danger filter-btn" data-filter="missing-1c">Немає в 1С</button>
                            <button class="btn btn-outline-danger filter-btn" data-filter="missing-keycrm">Немає в KeyCRM</button>
                        </div>

                        <div class="btn-group btn-group-sm me-3 mb-2" role="group">
                            <button class="btn btn-outline-warning text-dark filter-btn" data-filter="diff">Різн. Сайт-1С</button>
                            <button class="btn btn-outline-warning text-dark filter-btn" data-filter="diff-keycrm">Різн. Сайт-KeyCRM</button>
                        </div>

                        <div class="btn-group btn-group-sm me-3 mb-2" role="group">
                            <button class="btn btn-outline-info text-dark filter-btn" data-filter="dups">Є дублікати</button>
                        </div>

                        <div class="btn-group btn-group-sm me-3 mb-2" role="group">
                            <button class="btn btn-outline-secondary filter-btn" data-filter="samples">Тільки Взірці</button>
                            <button class="btn btn-outline-secondary filter-btn" data-filter="no-samples">Без взірців</button>
                        </div>

                        <div class="btn-group btn-group-sm mb-2" role="group">
                            <button class="btn btn-outline-dark filter-btn" data-filter="defects">Тільки Дефекти</button>
                            <button class="btn btn-outline-dark filter-btn" data-filter="no-defects">Без дефектів</button>
                        </div>
                    </div>
                </div>
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
                        <th>Розмір</th>
                        <th>Колір</th>
                        <th>Активний</th>
                        <th>Залишок Сайт</th>
                        <th>Залишок 1C</th>
                        <th>Залишок KeyCRM</th>
                        <th>Різн. Сайт-1С</th>
                        <th>Різн. Сайт-KeyCRM</th>
                        <th>Ціна Сайт</th>
                        <th>Ціна 1C</th>
                    </tr>
                </thead>
                <tbody>
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
            // Clone the thead tr for column filters
            $('#productsTable thead tr').clone(true).addClass('filters').appendTo('#productsTable thead');

            var table = $('#productsTable').DataTable({
                "serverSide": true,
                "processing": true,
                "ajax": {
                    "url": "ajax_check_products.php",
                    "type": "POST",
                    "data": function ( d ) {
                        var activeFilters = [];
                        $('.filter-btn.active').each(function() {
                            activeFilters.push($(this).data('filter'));
                        });
                        d.custom_filters = activeFilters;
                    }
                },
                "pageLength": 50,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/uk.json"
                },
                "order": [[ 13, "desc" ]], // Sort by difference (index 13)
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

                        // Add a dropdown list for Active column (index 9)
                        if (colIdx === 9) {
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
                var filter = $(this).data('filter');
                if (filter === 'all') {
                    $('.filter-btn').removeClass('active');
                    $(this).addClass('active');
                } else {
                    $('.filter-btn[data-filter="all"]').removeClass('active');
                    
                    // Mutually exclusive pairs
                    if (filter === 'samples') $('.filter-btn[data-filter="no-samples"]').removeClass('active');
                    if (filter === 'no-samples') $('.filter-btn[data-filter="samples"]').removeClass('active');
                    if (filter === 'defects') $('.filter-btn[data-filter="no-defects"]').removeClass('active');
                    if (filter === 'no-defects') $('.filter-btn[data-filter="defects"]').removeClass('active');

                    $(this).toggleClass('active');
                    
                    if ($('.filter-btn.active').length === 0) {
                        $('.filter-btn[data-filter="all"]').addClass('active');
                    }
                }
                table.draw();
            });

            $('#btnUpdateData').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Оновлюємо (може зайняти кілька хвилин)...');
                
                $.ajax({
                    url: 'update_check_products.php',
                    method: 'GET',
                    success: function(response) {
                        alert('Дані успішно оновлено!');
                        location.reload();
                    },
                    error: function() {
                        alert('Сталася помилка під час оновлення.');
                        btn.prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Оновити дані');
                    }
                });
            });

            // Add event listener for opening and closing details (Duplicates)
            $('#productsTable tbody').on('click', '.toggle-details', function () {
                var tr = $(this).closest('tr');
                var row = table.row( tr );

                // If history is shown, hide it first
                if (tr.hasClass('history-shown')) {
                    row.child.hide();
                    tr.removeClass('history-shown shown');
                    tr.find('.load-history').html('<i class="bi bi-clock-history"></i>');
                }

                if ( row.child.isShown() && tr.hasClass('shown') ) {
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
                        var html = '<div class="p-3 bg-light border border-top-0"><h6 class="mb-2 text-secondary">Дублікати на Сайті</h6><table class="table table-sm table-bordered mt-2 mb-2 bg-white shadow-sm">';
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
                        html += '</tbody></table></div>';
                        
                        row.child( html ).show();
                        tr.addClass('shown');
                        $(this).text('-');
                    }
                }
            } );

            // Add event listener for History button
            $('#productsTable tbody').on('click', '.load-history', function () {
                var btn = $(this);
                var tr = btn.closest('tr');
                var row = table.row( tr );
                var sku = tr.attr('data-sku');

                // If duplicates are shown, hide them first
                if (tr.hasClass('shown') && !tr.hasClass('history-shown')) {
                    row.child.hide();
                    tr.removeClass('shown');
                    tr.find('.toggle-details').text('+');
                }

                if ( row.child.isShown() && tr.hasClass('history-shown') ) {
                    row.child.hide();
                    tr.removeClass('history-shown shown');
                    btn.html('<i class="bi bi-clock-history"></i>');
                } else {
                    btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
                    
                    $.ajax({
                        url: 'ajax_get_history.php',
                        method: 'POST',
                        data: { sku: sku },
                        dataType: 'json',
                        success: function(response) {
                            if (response.error) {
                                alert(response.error);
                                btn.html('<i class="bi bi-clock-history"></i>');
                                return;
                            }
                            
                            var html = '<div class="p-3 bg-light border border-top-0"><h6 class="mb-2 text-primary"><i class="bi bi-clock-history"></i> Історія змін для SKU: ' + sku + '</h6>' + response.html + '</div>';
                            row.child( html ).show();
                            tr.addClass('history-shown shown');
                            btn.html('<i class="bi bi-chevron-up"></i>');
                        },
                        error: function() {
                            alert('Помилка завантаження історії');
                            btn.html('<i class="bi bi-clock-history"></i>');
                        }
                    });
                }
            });
        } );
    </script>
</body>
</html>
