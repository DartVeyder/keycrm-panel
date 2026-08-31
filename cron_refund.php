<?php
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/class/KeyCrmV2.php');

$keyCrm = new KeyCrmV2();

// Статуси, що означають "Повернення" (згідно webhook_change_order_status.php)
$statusIds = [31, 33, 34, 39, 79, 80, 115, 11, 38, 40, 117, 116];
// Додаємо сортування за часом оновлення, щоб найсвіжіші були першими
$filter = "filter[status_id]=" . implode(',', $statusIds) . "&sort=-updated_at";

echo "Запуск CRON перевірки повернень: " . date('Y-m-d H:i:s') . "\n";
echo "Шукаємо замовлення зі статусами: " . implode(', ', $statusIds) . "\n";

// Беремо 4 сторінки (до 200 останніх оновлених замовлень)
$ordersList = $keyCrm->orders($filter, 4);

if (empty($ordersList)) {
    echo "Немає замовлень у статусі повернення.\n";
    exit;
}

$todayDate = date('Y-m-d');
$yesterdayDate = date('Y-m-d', strtotime('-1 day'));
$recentOrders = [];

foreach ($ordersList as $order) {
    $orderDate = substr($order['updated_at'], 0, 10);
    if ($orderDate === $todayDate || $orderDate === $yesterdayDate) {
        $recentOrders[] = $order;
    }
}

$ordersList = $recentOrders;

if (empty($ordersList)) {
    echo "За сьогодні ({$todayDate}) та вчора ({$yesterdayDate}) немає оновлених замовлень у статусі повернення.\n";
    exit;
}

$foundIds = array_column($ordersList, 'id');
echo "Всього знайдено замовлень за СЬОГОДНІ та ВЧОРА: " . count($ordersList) . " (ID: " . implode(', ', $foundIds) . ")\n\n";
$processedCount = 0;
$statusField = 'OR_1042'; // Поле "Повернення статус"
$fopField = 'OR_1047'; // Тип оплати ФОП 1
$amountField = 'OR_1038'; // Повернення сума ФОП 1
$fopField2 = 'OR_1060'; // Тип оплати ФОП 2
$amountField2 = 'OR_1059'; // Повернення сума ФОП 2
$commentField = 'OR_1046'; // Поле "Повернення коментар"

$processedLogFile = __DIR__ . '/logs/cron_processed_orders.txt';
$processedIds = file_exists($processedLogFile) ? file($processedLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

foreach ($ordersList as $orderData) {
    if (in_array((string)$orderData['id'], $processedIds)) {
        continue; // Вже пробували обробити через крон
    }

    // Збираємо кастомні поля ПРЯМО зі списку, щоб не робити 50 зайвих запитів
    $order_custom_fields = array_map(
        fn($v) => is_array($v) ? reset($v) : $v,
        array_column($orderData['custom_fields'] ?? [], 'value', 'uuid')
    );

    // 0. Головне правило: має бути вказана сума повернення!
    if (empty($order_custom_fields[$amountField]) && empty($order_custom_fields[$amountField2])) {
        continue;
    }

    // 1. Перевіряємо, чи вказано ФОП (поле OR_1047 або OR_1060 не пусте)
    $hasFop = !empty($order_custom_fields[$fopField]) || !empty($order_custom_fields[$fopField2]);
    if (!$hasFop) {
        $hasAutoFop = false;
        if (!empty($orderData['payments'])) {
            foreach ($orderData['payments'] as $payment) {
                // Визначаємо тільки для LiqPay (id 61) або по наявності PBK
                if (($payment['payment_method_id'] ?? 0) == 61 || strpos($payment['description'] ?? '', 'PBK') !== false) {
                    $hasAutoFop = true;
                    break;
                }
            }
        }
        if (!$hasAutoFop && !empty($orderData['buyer_comment']) && preg_match('/^\d+,([A-Za-z0-9\-]+)$/', trim($orderData['buyer_comment']))) {
            $hasAutoFop = true;
        }

        if (!$hasAutoFop) {
            // Пропускаємо старі замовлення мовчки, щоб не засмічувати консоль
            continue;
        }
    }

    // 2. Перевіряємо, чи вже був успішний платіж (через статус)
    if (isset($order_custom_fields[$statusField]) && $order_custom_fields[$statusField] === 'SUCCESS') {
        continue;
    }

    // 3. Додаткова перевірка: якщо статус не зберігся, але є коментар про успішний платіж
    $currentCommentField = !empty($order_custom_fields[$amountField]) ? $commentField : (!empty($order_custom_fields[$amountField2]) ? 'OR_1080' : $commentField);
    
    if (isset($order_custom_fields[$currentCommentField])) {
        $comment = $order_custom_fields[$currentCommentField];
        if (strpos($comment, 'Платіж №AC') !== false || strpos($comment, 'Повернення LiqPay') !== false || strpos($comment, 'Запит відправлено LiqPay') !== false) {
            continue;
        }
    }

    // Якщо дійшли сюди - замовлення підходить! Отримуємо повні дані (покупець і тд)
    $orderId = $orderData['id'];
    $order = $keyCrm->order($orderId);
    if (!$order) {
        continue;
    }

    echo "Знайдено нове замовлення #{$orderId} на повернення. Запускаємо обробку...\n";

    // Перехоплюємо вивід скрипта, щоб він не зупинив цикл
    ob_start();
    try {
        require(__DIR__ . '/refund.php');
    } catch (Exception $e) {
        echo "Помилка при обробці #{$orderId}: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();

    echo "Результат: " . trim($output) . "\n\n";
    $processedCount++;

    // Зберігаємо ID замовлення, щоб крон не намагався обробити його повторно
    file_put_contents(__DIR__ . '/logs/cron_processed_orders.txt', $orderId . "\n", FILE_APPEND);

    // Невелика затримка, щоб не спамити API ПриватБанку та KeyCRM
    sleep(1);
}

echo "CRON завершено. Оброблено нових замовлень: {$processedCount}\n";
