<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class/LiqPayPayment.php';
require_once __DIR__ . '/class/KeyCrmV2.php';
require_once __DIR__ . '/refund_config.php';

$keyCrm = new KeyCrmV2();

$orderId = trim($_REQUEST['order_id'] ?? '');
$paymentId = trim($_REQUEST['payment_id'] ?? '');
$fopName = trim($_REQUEST['fop'] ?? '');

$liqpayFops = [];
foreach ($config as $name => $cfg) {
    if (($cfg['type'] ?? '') === 'liqpay' && !empty($cfg['public_key']) && !empty($cfg['private_key'])) {
        $liqpayFops[$name] = $cfg;
    }
}

$selectedCfg = null;
$activeFopName = null;

if ($fopName && isset($liqpayFops[$fopName])) {
    $selectedCfg = $liqpayFops[$fopName];
    $activeFopName = $fopName;
}

$crmOrder = null;
$liqpayOrderId = $orderId;
$buyerEmail = '';
$buyerName = '';
$buyerPhone = '';

// Якщо передано ID замовлення KeyCRM (або воно виглядає як число)
if ($orderId && is_numeric($orderId)) {
    try {
        $crmOrder = $keyCrm->order($orderId);
        if ($crmOrder) {
            $buyerEmail = trim($crmOrder['buyer']['email'] ?? '');
            $buyerName = trim($crmOrder['buyer']['full_name'] ?? '');
            $buyerPhone = trim($crmOrder['buyer']['phone'] ?? '');

            if (empty($buyerEmail) && !empty($crmOrder['buyer_comment'])) {
                if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $crmOrder['buyer_comment'], $mEmail)) {
                    $buyerEmail = $mEmail[0];
                }
            }

            // Шукаємо SOID у кастомних полях або коментарі
            $cFields = array_map(fn($v) => is_array($v) ? reset($v) : $v, array_column($crmOrder['custom_fields'] ?? [], 'value', 'uuid'));
            if (!empty($cFields['OR_1034'])) {
                $liqpayOrderId = trim($cFields['OR_1034']);
            } elseif (!empty($crmOrder['buyer_comment'])) {
                if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $crmOrder['buyer_comment'], $m)) {
                    $liqpayOrderId = $m[1];
                } elseif (preg_match('/^\d+,([A-Za-z0-9\-]+)$/', trim($crmOrder['buyer_comment']), $m)) {
                    $liqpayOrderId = $m[2];
                }
            }

            // Спроба взяти SOID з оплат KeyCRM
            if (($liqpayOrderId === $orderId || empty($liqpayOrderId)) && !empty($crmOrder['payments'])) {
                foreach ($crmOrder['payments'] as $p) {
                    $pDesc = $p['description'] ?? $p['comment'] ?? '';
                    if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $pDesc, $m)) {
                        $liqpayOrderId = $m[1];
                        break;
                    }
                }
            }

            // Якщо ФОП не вибрано, дивимось на вибір у замовленні
            if (!$selectedCfg) {
                $targetFop = $cFields['OR_1047'] ?? $cFields['OR_1060'] ?? '';
                if ($targetFop && isset($liqpayFops[$targetFop])) {
                    $selectedCfg = $liqpayFops[$targetFop];
                    $activeFopName = $targetFop;
                }
            }
        }
    } catch (Exception $e) {
        // ігноруємо
    }
}

$transaction = null;
$errorMsg = null;
$successMsg = null;

// Якщо ФОП не вибрано, перебираємо всі LiqPay ФОПи
if (!$selectedCfg && $liqpayOrderId) {
    foreach ($liqpayFops as $name => $cfg) {
        $api = new LiqPayPayment($cfg['public_key'], $cfg['private_key']);
        try {
            $res = $api->status($liqpayOrderId);
            if (isset($res['status']) && $res['status'] !== 'error') {
                $transaction = $res;
                $selectedCfg = $cfg;
                $activeFopName = $name;
                break;
            }
        } catch (Exception $e) {}
    }
} elseif ($selectedCfg && $liqpayOrderId) {
    $api = new LiqPayPayment($selectedCfg['public_key'], $selectedCfg['private_key']);
    try {
        $transaction = $api->status($liqpayOrderId);
    } catch (Exception $e) {
        $errorMsg = "Помилка отримання даних LiqPay: " . $e->getMessage();
    }
}

// Якщо передано payment_id, але транзакцію за order_id не знайдено
if (!$transaction && $paymentId) {
    foreach ($liqpayFops as $name => $cfg) {
        $api = new LiqPayPayment($cfg['public_key'], $cfg['private_key']);
        try {
            $res = $api->status($paymentId);
            if (isset($res['status']) && $res['status'] !== 'error') {
                $transaction = $res;
                $selectedCfg = $cfg;
                $activeFopName = $name;
                $liqpayOrderId = $res['order_id'] ?? $liqpayOrderId;
                break;
            }
        } catch (Exception $e) {}
    }
}

// -------------------------------------------------------------
// ОБРОБКА РУЧНОЇ ВІДПРАВКИ КВИТАНЦІЇ
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_ticket') {
    $targetEmail = trim($_POST['email'] ?? '');
    
    if (empty($targetEmail) || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Будь ласка, вкажіть коректний Email клієнта.";
    } elseif (!$selectedCfg) {
        $errorMsg = "Не вдалося визначити рахунок ФОП для цієї транзакції. Оберіть ФОП зі списку вручну.";
    } else {
        $api = new LiqPayPayment($selectedCfg['public_key'], $selectedCfg['private_key']);
        try {
            $effectivePaymentId = $transaction['payment_id'] ?? $paymentId ?? null;
            $tRes = $api->sendTicket($liqpayOrderId, $targetEmail, $effectivePaymentId);

            if (isset($tRes['result']) && $tRes['result'] === 'ok') {
                $successMsg = "Квитанцію LiqPay успішно надіслано на email: <strong>" . htmlspecialchars($targetEmail) . "</strong>";

                // Оновлюємо помітку в KeyCRM, якщо замовлення знайдено
                if ($crmOrder && $orderId && is_numeric($orderId)) {
                    try {
                        $cFields = array_map(fn($v) => is_array($v) ? reset($v) : $v, array_column($crmOrder['custom_fields'] ?? [], 'value', 'uuid'));
                        $commentField = (!empty($cFields['OR_1060']) && $cFields['OR_1060'] === $activeFopName) ? 'OR_1080' : 'OR_1046';
                        $existingComment = $cFields[$commentField] ?? '';

                        $manualNote = "[Квитанцію надіслано вручну на email: {$targetEmail} (" . date('d.m.Y H:i') . ")]";

                        if (preg_match('/\[Квитанція[^\]]+\]/u', $existingComment)) {
                            $updatedComment = preg_replace('/\[Квитанція[^\]]+\]/u', $manualNote, $existingComment);
                        } else {
                            $updatedComment = trim($existingComment . " " . $manualNote);
                        }

                        $keyCrm->updateOrder($orderId, [
                            'custom_fields' => [
                                ['uuid' => $commentField, 'value' => $updatedComment]
                            ]
                        ]);
                        $successMsg .= " <span class='badge bg-success ms-2'>Помітку в KeyCRM оновлено</span>";
                    } catch (Exception $eKey) {
                        // ігноруємо помилку оновлення CRM
                    }
                }
            } else {
                $errorMsg = "LiqPay повернув помилку: " . ($tRes['err_description'] ?? 'Невідома помилка');
            }
        } catch (Exception $e) {
            $errorMsg = "Помилка при відправці квитанції: " . $e->getMessage();
        }
    }
}

$amount = $transaction['refund_amount'] ?? $transaction['amount'] ?? 0;
$currency = $transaction['currency'] ?? 'UAH';
$status = $transaction['status'] ?? 'unknown';
$date = !empty($transaction['end_date']) ? date('d.m.Y H:i:s', $transaction['end_date'] / 1000) : (!empty($transaction['create_date']) ? date('d.m.Y H:i:s', $transaction['create_date'] / 1000) : '-');
$cardMask = $transaction['sender_card_mask2'] ?? '-';
$cardBank = $transaction['sender_card_bank'] ?? '';
$paymentIdVal = $transaction['payment_id'] ?? $paymentId ?? '-';
$rrn = $transaction['rrn_debit'] ?? '-';
$authCode = $transaction['authcode_debit'] ?? '-';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ручна відправка квитанції LiqPay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .main-container { max-width: 680px; margin: 30px auto; }
        .search-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: none;
            margin-bottom: 25px;
        }
        .receipt-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            border-top: 6px solid #20c997;
            border-left: none;
            border-right: none;
            border-bottom: none;
        }
        .receipt-header {
            padding: 24px 30px;
            background: #fafbfc;
            border-bottom: 1px dashed #dee2e6;
            text-align: center;
        }
        .receipt-body { padding: 30px; }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f3f5;
            font-size: 0.95rem;
        }
        .receipt-row:last-child { border-bottom: none; }
        .receipt-label { color: #6c757d; }
        .receipt-value { font-weight: 600; color: #212529; text-align: right; }
        .amount-highlight {
            font-size: 2rem;
            font-weight: 700;
            color: #dc3545;
            text-align: center;
            margin: 15px 0 5px 0;
        }
        .badge-refund {
            background-color: #e6fcf5;
            color: #0ca678;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @media print {
            body { background: #fff; }
            .receipt-card { box-shadow: none; margin: 0; width: 100%; max-width: 100%; border-top: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="container main-container py-2">
        <!-- Верхня панель пошуку замовлення -->
        <div class="search-card p-4 no-print">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 text-primary">
                    <i class="fas fa-paper-plane me-2"></i>Ручна відправка квитанції LiqPay
                </h5>
                <a href="manual_refund.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Повернення
                </a>
            </div>
            <form method="GET" action="liqpay_receipt.php" class="row g-2">
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">ID замовлення KeyCRM або SOID</label>
                    <input type="text" name="order_id" class="form-control" placeholder="Наприклад: 211443" value="<?= htmlspecialchars($orderId) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">ФОП (опціонально)</label>
                    <select name="fop" class="form-select">
                        <option value="">Автовизначення</option>
                        <?php foreach ($liqpayFops as $fName => $cfg): ?>
                            <option value="<?= htmlspecialchars($fName) ?>" <?= $fName === $activeFopName ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Знайти
                    </button>
                </div>
            </form>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success shadow-sm d-flex align-items-center mb-4 no-print">
                <i class="fas fa-check-circle fs-3 me-3 text-success"></i>
                <div><?= $successMsg ?></div>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4 no-print">
                <i class="fas fa-exclamation-triangle fs-3 me-3 text-danger"></i>
                <div><?= htmlspecialchars($errorMsg) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($transaction): ?>
            <!-- Картка квитанції -->
            <div class="receipt-card">
                <div class="receipt-header">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-success" style="font-size: 1.25rem;">
                            <i class="fas fa-shield-alt me-1"></i> LiqPay
                        </span>
                        <span class="badge-refund">
                            <i class="fas fa-undo-alt me-1"></i> Повернення коштів
                        </span>
                    </div>
                    <div class="amount-highlight">
                        -<?= number_format((float)$amount, 2, '.', ' ') ?> <?= htmlspecialchars($currency) ?>
                    </div>
                    <div class="text-muted small">
                        <?= $status === 'reversed' ? 'Кошти успішно повернено на картку платника' : 'Статус: ' . htmlspecialchars($status) ?>
                    </div>
                </div>

                <div class="receipt-body">
                    <div class="receipt-row">
                        <span class="receipt-label">Рахунок мерчанта (ФОП):</span>
                        <span class="receipt-value"><?= htmlspecialchars($activeFopName ?: 'LiqPay Merchant') ?></span>
                    </div>
                    <?php if ($orderId): ?>
                        <div class="receipt-row">
                            <span class="receipt-label">Замовлення в KeyCRM:</span>
                            <span class="receipt-value">
                                <a href="https://twice1.keycrm.app/app/orders/view/<?= htmlspecialchars($orderId) ?>" target="_blank" class="text-decoration-none">
                                    #<?= htmlspecialchars($orderId) ?> <i class="fas fa-external-link-alt small ms-1"></i>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($buyerName): ?>
                        <div class="receipt-row">
                            <span class="receipt-label">Покупець:</span>
                            <span class="receipt-value"><?= htmlspecialchars($buyerName) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Payment ID LiqPay:</span>
                        <span class="receipt-value"><code><?= htmlspecialchars($paymentIdVal) ?></code></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Order ID (SOID):</span>
                        <span class="receipt-value text-break"><code><?= htmlspecialchars($liqpayOrderId ?: '-') ?></code></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Дата та час операції:</span>
                        <span class="receipt-value"><?= htmlspecialchars($date) ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Картка платника:</span>
                        <span class="receipt-value">
                            <i class="far fa-credit-card me-1 text-muted"></i>
                            <?= htmlspecialchars($cardMask) ?> <?= $cardBank ? '(' . htmlspecialchars($cardBank) . ')' : '' ?>
                        </span>
                    </div>
                    <?php if ($rrn !== '-'): ?>
                        <div class="receipt-row">
                            <span class="receipt-label">Код RRN:</span>
                            <span class="receipt-value"><code><?= htmlspecialchars($rrn) ?></code></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($authCode !== '-'): ?>
                        <div class="receipt-row">
                            <span class="receipt-label">Код авторизації:</span>
                            <span class="receipt-value"><code><?= htmlspecialchars($authCode) ?></code></span>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top text-center text-muted small">
                        Офіційний електронний документ платіжної системи.<br>
                        Перевірка достовірності квитанції: 
                        <a href="https://pb.ua/check" target="_blank" class="text-decoration-none fw-bold">pb.ua/check</a>
                    </div>

                    <!-- Форма ручної відправки на будь-який Email -->
                    <div class="no-print mt-4 pt-4 border-top">
                        <div class="card bg-light border-0 p-3">
                            <h6 class="fw-bold mb-2 text-dark">
                                <i class="fas fa-envelope-open-text me-2 text-primary"></i>Надіслати квитанцію клієнту на Email
                            </h6>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="send_ticket">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
                                <input type="hidden" name="fop" value="<?= htmlspecialchars($activeFopName) ?>">
                                <div class="mb-2">
                                    <div class="input-group">
                                        <input type="email" name="email" class="form-control" placeholder="Введіть email клієнта" value="<?= htmlspecialchars($buyerEmail) ?>" required>
                                        <button class="btn btn-success px-4" type="submit">
                                            <i class="fas fa-paper-plane me-1"></i> Надіслати
                                        </button>
                                    </div>
                                </div>
                                <div class="form-text small text-muted">
                                    LiqPay згенерує банківську квитанцію та надішле її на цю адресу. Статус відправки автоматично зафіксується в коментарі замовлення в KeyCRM.
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Кнопки дій -->
                    <div class="no-print d-flex justify-content-between align-items-center mt-4">
                        <a href="javascript:window.close();" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i> Закрити
                        </a>
                        <div>
                            <a href="https://pb.ua/check" target="_blank" class="btn btn-outline-info btn-sm me-2">
                                <i class="fas fa-external-link-alt me-1"></i> pb.ua/check
                            </a>
                            <button onclick="window.print();" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-print me-1"></i> Роздрукувати
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($orderId): ?>
            <div class="card shadow-sm border-0 p-4 text-center">
                <div class="text-warning mb-3">
                    <i class="fas fa-search-minus fa-3x"></i>
                </div>
                <h5 class="text-secondary">Транзакцію LiqPay не знайдено</h5>
                <p class="text-muted">
                    Для замовлення <strong>#<?= htmlspecialchars($orderId) ?></strong> не вдалося автоматично знайти завершене повернення в підключених LiqPay акаунтах.
                </p>
                <div class="text-start bg-light p-3 rounded small mb-3">
                    <strong>Рекомендації:</strong>
                    <ul class="mb-0 mt-1">
                        <li>Перевірте, чи замовлення дійсно оплачувалося через LiqPay.</li>
                        <li>Переконайтеся, що в полі <code>OR_1034</code> або в коментарі покупця присутній коректний <code>SOID</code> або <code>LiqPay ID</code>.</li>
                        <li>Якщо повернення здійснювалося з конкретного ФОП, оберіть його в полі вибору вище.</li>
                    </ul>
                </div>
                <a href="liqpay_logs.php" target="_blank" class="btn btn-outline-primary btn-sm align-self-center">
                    <i class="fas fa-list-alt me-1"></i> Переглянути журнал платежів у кабінеті LiqPay
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
