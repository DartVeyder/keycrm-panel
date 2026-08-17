<?php
require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/KeyCrmV2.php');

$orderId = $_GET['order_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ручне повернення коштів</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .result-box {
            background: #212529;
            color: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 1.05rem;
            white-space: pre-wrap;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.5);
        }
        .text-success-custom { color: #20c997 !important; font-weight: bold; }
        .text-danger-custom { color: #ff6b6b !important; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container py-5" style="max-width: 700px;">
        <div class="card shadow border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                <h4 class="mb-0 text-primary">Обробка повернення</h4>
                <span class="badge bg-secondary">KeyCRM</span>
            </div>
            <div class="card-body p-4 pt-0">
                <?php if (!$orderId): ?>
                    <div class="text-center py-3">
                        <p class="text-muted">Введіть ID замовлення з KeyCRM для ручного запуску процесу повернення.</p>
                        <form method="GET" action="manual_refund.php" class="d-flex justify-content-center gap-2 mt-3 mb-4">
                            <input type="number" name="order_id" class="form-control w-50" placeholder="Наприклад: 12345" required>
                            <button type="submit" class="btn btn-primary">Запустити</button>
                        </form>
                    </div>

                    <hr>
                    <h5 class="mb-3 text-secondary">Останні замовлення на повернення</h5>
                    <?php
                        $statusIds = [31, 33, 34, 39, 79, 80, 115, 11, 38, 40, 117, 116];
                        $filter = "filter[status_id]=" . implode(',', $statusIds) . "&sort=-updated_at";
                        $keyCrm = new KeyCrmV2();
                        $recentOrders = $keyCrm->orders($filter, 1); // Завантажуємо тільки першу сторінку (до 50 замовлень)
                        
                        $statusField = 'OR_1042'; 
                        $fopField = 'OR_1047';
                        $amountField = 'OR_1038';
                    ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>ID</th>
                                    <th>Сума</th>
                                    <th>ФОП</th>
                                    <th>Статус</th>
                                    <th>Оновлено</th>
                                    <th class="text-end">Дія</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentOrders as $ro): 
                                    $cFields = array_map(fn($v) => is_array($v) ? reset($v) : $v, array_column($ro['custom_fields'] ?? [], 'value', 'uuid'));
                                    $isSuccess = ($cFields[$statusField] ?? '') === 'SUCCESS';
                                    $amount = $cFields[$amountField] ?? '-';
                                    $fop = $cFields[$fopField] ?? '-';
                                    $updated = date('d.m H:i', strtotime($ro['updated_at']));
                                ?>
                                <tr>
                                    <td><strong><a href="https://twice1.keycrm.app/app/orders/view/<?= $ro['id'] ?>" target="_blank" class="text-decoration-none">#<?= $ro['id'] ?></a></strong></td>
                                    <td><?= htmlspecialchars($amount) ?></td>
                                    <td>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 150px;" title="<?= htmlspecialchars($fop) ?>">
                                            <?= htmlspecialchars($fop) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($isSuccess): ?>
                                            <span class="badge bg-success">Повернуто</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Очікує</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= $updated ?></small></td>
                                    <td class="text-end">
                                        <?php if (!$isSuccess && $amount !== '-'): ?>
                                            <a href="manual_refund.php?order_id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">Запустити</a>
                                        <?php elseif (!$isSuccess): ?>
                                            <span class="text-muted small" title="Не вказана сума">Немає суми</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Замовлень не знайдено</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-2">Замовлення ID: <strong><?= htmlspecialchars($orderId) ?></strong></p>
                    <?php
                    $keyCrm = new KeyCrmV2();
                    $order = $keyCrm->order($orderId);
                    
                    if (!$order || !isset($order['id'])) {
                        echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Помилка: Замовлення не знайдено в KeyCRM. Перевірте ID.</div>";
                    } else {
                        ob_start();
                        try {
                            require_once('refund.php');
                        } catch (Exception $e) {
                            echo "КРИТИЧНА ПОМИЛКА: " . $e->getMessage();
                        }
                        
                        $output = trim(ob_get_clean());
                        
                        $isError = (strpos($output, 'ERROR') !== false || strpos($output, 'Відсутнє або пусте') !== false || strpos($output, 'Помилка') !== false || strpos($output, 'КРИТИЧНА ПОМИЛКА') !== false);
                        $isSuccess = (strpos($output, 'SUCCESS') !== false);
                        
                        if ($isSuccess) {
                            echo '<div class="alert alert-success fs-5 shadow-sm" style="border-left: 5px solid #198754;"><i class="fas fa-check-circle me-2"></i><strong>Успішно!</strong> Повернення пройшло вдало.</div>';
                        } elseif ($isError) {
                            echo '<div class="alert alert-danger fs-5 shadow-sm" style="border-left: 5px solid #dc3545;"><i class="fas fa-times-circle me-2"></i><strong>Помилка!</strong> Не вдалося виконати повернення.</div>';
                        }
                        ?>
                        <div class="result-box mb-4">
Замовлення знайдено.
Ініціюємо процес транзакції...

----------------------------------------
<?= htmlspecialchars($output) ?>

----------------------------------------
Процес завершено.
                        </div>
                    <?php } ?>
                    <div class="text-center mb-3">
                        <a href="manual_refund.php" class="btn btn-outline-primary">Повернутися до списку</a>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="refund_settings.php" class="btn btn-outline-secondary btn-sm" target="_blank">⚙️ Налаштування ФОП</a>
                    <button onclick="window.close();" class="btn btn-primary">Закрити вікно</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
