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
                    <div class="alert alert-danger">
                        <strong>Помилка:</strong> Не передано ID замовлення (відсутній параметр order_id у посиланні).
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-2">Замовлення ID: <strong><?= htmlspecialchars($orderId) ?></strong></p>
                    
                    <div class="result-box mb-4">
<?php
// Отримуємо замовлення
$keyCrm = new KeyCrmV2();
$order = $keyCrm->order($orderId);

if (!$order || !isset($order['id'])) {
    echo "<span class='text-danger-custom'>Помилка: Замовлення не знайдено в KeyCRM. Перевірте ID.</span>";
} else {
    echo "Замовлення знайдено.\nІніціюємо процес транзакції...\n\n";
    echo "----------------------------------------\n";
    
    // Запускаємо логіку повернення
    ob_start();
    try {
        require_once('refund.php');
    } catch (Exception $e) {
        echo "<span class='text-danger-custom'>КРИТИЧНА ПОМИЛКА: " . $e->getMessage() . "</span>";
    }
    
    $output = ob_get_clean();
    $output = trim($output);
    
    // Робимо вивід красивішим
    if (strpos($output, 'ERROR') !== false || strpos($output, 'Відсутнє або пусте') !== false || strpos($output, 'Помилка') !== false) {
        echo "<span class='text-danger-custom'>" . htmlspecialchars($output) . "</span>";
    } else if (strpos($output, 'SUCCESS') !== false) {
        echo "<span class='text-success-custom'>" . htmlspecialchars($output) . "</span>";
    } else {
        echo htmlspecialchars($output);
    }
    
    echo "\n----------------------------------------\n";
    echo "Процес завершено.";
}
?>
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
