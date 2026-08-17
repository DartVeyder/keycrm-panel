<?php
require_once('vendor/autoload.php');
require_once('config.php');

require('refund_config.php');
$fopConfigs = $config ?? [];
$liqpayFops = [];
foreach ($fopConfigs as $key => $cfg) {
    if (isset($cfg['type']) && $cfg['type'] === 'liqpay' && !empty($cfg['public_key'])) {
        $liqpayFops[$key] = $cfg;
    }
}

// Defaults
$defaultDateTo = date('Y-m-d');
$defaultDateFrom = date('Y-m-d', strtotime('-3 days'));

$selectedFop = $_GET['fop'] ?? array_key_first($liqpayFops);
$dateFrom = $_GET['date_from'] ?? $defaultDateFrom;
$dateTo = $_GET['date_to'] ?? $defaultDateTo;

$transactions = [];
$errorMsg = null;
$isLoading = isset($_GET['fop']);

if ($isLoading && isset($liqpayFops[$selectedFop])) {
    $cfg = $liqpayFops[$selectedFop];
    
    // API request to LiqPay
    $params = [
        "action"     => "reports",
        "version"    => "3",
        "public_key" => $cfg["public_key"],
        "date_from"  => strtotime($dateFrom . ' 00:00:00') * 1000,
        "date_to"    => strtotime($dateTo . ' 23:59:59') * 1000
    ];

    $data = base64_encode(json_encode($params));
    $signature = base64_encode(sha1($cfg["private_key"] . $data . $cfg["private_key"], 1));

    $post = [
        "data"      => $data,
        "signature" => $signature
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.liqpay.ua/api/request");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        $errorMsg = "CURL помилка: " . $curlErr;
    } else {
        $result = json_decode($response, true);
        if (isset($result['result']) && $result['result'] === 'error') {
            $errorMsg = "Помилка API LiqPay: " . ($result['err_description'] ?? 'Невідома помилка');
        } elseif (isset($result['data']) && is_array($result['data'])) {
            $transactions = $result['data'];
            // Сортуємо новіші зверху
            usort($transactions, function($a, $b) {
                return $b['create_date'] <=> $a['create_date'];
            });
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Історія LiqPay - KeyCRM Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .table-custom th { background-color: #f1f3f5; font-weight: 600; }
        .action-pay { color: #20c997; }
        .action-refund { color: #fd7e14; }
        .status-success { color: #198754; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-wait { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left me-2"></i>На головну
            </a>
            <span class="navbar-text text-white fw-bold">
                <i class="fas fa-list-alt me-2"></i>Кабінет LiqPay
            </span>
        </div>
    </nav>

    <div class="container-fluid px-4 mb-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="liqpay_logs.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-muted small mb-1">Виберіть ФОП</label>
                        <select name="fop" class="form-select">
                            <?php foreach ($liqpayFops as $key => $cfg): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $key === $selectedFop ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($key) ?> (<?= htmlspecialchars($cfg['public_key']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1">Дата з</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1">Дата по</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Завантажити
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <?php if ($isLoading && !$errorMsg): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Транзакції (<?= count($transactions) ?> шт.)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Дата / Час</th>
                                <th>Дія / Статус</th>
                                <th>Сума</th>
                                <th>Комісія</th>
                                <th>Order ID / SOID</th>
                                <th>Payment ID (LiqPay)</th>
                                <th>Деталі / Картка</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        За вибраний період транзакцій не знайдено
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $index => $tr): 
                                    $date = date('d.m.Y H:i:s', $tr['create_date'] / 1000);
                                    
                                    $actionIcon = '';
                                    if ($tr['action'] === 'pay' || $tr['action'] === 'hold') $actionIcon = '<i class="fas fa-arrow-down action-pay" title="Оплата"></i> Оплата';
                                    elseif ($tr['action'] === 'refund') $actionIcon = '<i class="fas fa-undo action-refund" title="Повернення"></i> Повернення';
                                    else $actionIcon = htmlspecialchars($tr['action']);

                                    $statusClass = 'text-muted';
                                    if ($tr['status'] === 'success') $statusClass = 'status-success';
                                    elseif ($tr['status'] === 'error' || $tr['status'] === 'failure' || $tr['status'] === 'reversed') $statusClass = 'status-error';
                                    elseif ($tr['status'] === 'wait_accept' || $tr['status'] === 'processing') $statusClass = 'status-wait';
                                ?>
                                    <tr>
                                        <td class="text-nowrap"><small><?= $date ?></small></td>
                                        <td>
                                            <div><?= $actionIcon ?></div>
                                            <small class="<?= $statusClass ?>"><?= htmlspecialchars($tr['status']) ?></small>
                                        </td>
                                        <td>
                                            <strong class="<?= $tr['action'] === 'refund' ? 'text-danger' : 'text-success' ?>">
                                                <?= $tr['action'] === 'refund' ? '-' : '+' ?><?= number_format($tr['amount'], 2, '.', ' ') ?> <?= htmlspecialchars($tr['currency']) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= isset($tr['receiver_commission']) ? number_format($tr['receiver_commission'], 2) : '0.00' ?> <?= htmlspecialchars($tr['currency']) ?>
                                            </small>
                                        </td>
                                        <td style="word-break: break-all; max-width: 200px;">
                                            <code><?= htmlspecialchars($tr['order_id'] ?? '-') ?></code>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($tr['payment_id'] ?? '-') ?></code>
                                        </td>
                                        <td>
                                            <small class="d-block text-truncate text-muted mb-1" style="max-width: 250px;" title="<?= htmlspecialchars($tr['description'] ?? '') ?>">
                                                <?= htmlspecialchars($tr['description'] ?? '-') ?>
                                            </small>
                                            <div class="d-flex flex-wrap gap-1 mb-1 align-items-center">
                                                <?php if (!empty($tr['paytype'])): ?>
                                                    <span class="badge bg-secondary"><i class="fas fa-wallet"></i> <?= htmlspecialchars($tr['paytype']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($tr['sender_card_mask2'])): ?>
                                                    <small class="text-muted"><i class="far fa-credit-card mx-1"></i><?= htmlspecialchars($tr['sender_card_mask2']) ?> (<?= htmlspecialchars($tr['sender_card_bank'] ?? 'Unknown') ?>)</small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($tr['err_description'])): ?>
                                                <small class="text-danger d-block mb-1"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($tr['err_description']) ?></small>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#tr-<?= $index ?>" aria-expanded="false" title="Більше інформації">
                                                <i class="fas fa-code"></i> Усі дані
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="collapse bg-light" id="tr-<?= $index ?>">
                                        <td colspan="7">
                                            <div class="p-2">
                                                <strong>Повний JSON відповіді від банку:</strong>
                                                <pre class="mb-0 mt-2 p-3 bg-dark text-light rounded" style="font-size: 0.85rem; white-space: pre-wrap; word-break: break-all;"><?= htmlspecialchars(json_encode($tr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
