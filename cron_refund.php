<?php
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/class/KeyCrmV2.php');

$keyCrm = new KeyCrmV2();

// Статуси, що означають "Повернення" (згідно webhook_change_order_status.php)
$statusIds = [34, 79, 80];
// Додаємо сортування за часом оновлення, щоб найсвіжіші були першими
$filter    = "filter[status_id]=" . implode(',', $statusIds) . "&sort=-updated_at";

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
$statusField    = 'OR_1042'; // Поле "Повернення статус"
$fopField       = 'OR_1047'; // Тип оплати ФОП 1
$commentField   = 'OR_1046'; // Поле "Повернення коментар"

foreach ($ordersList as $orderData) {
    // Збираємо кастомні поля ПРЯМО зі списку, щоб не робити 50 зайвих запитів
    $order_custom_fields = array_map(
        fn($v) => is_array($v) ? reset($v) : $v,
        array_column($orderData['custom_fields'] ?? [], 'value', 'uuid')
    );

    // 1. Перевіряємо, чи вказано ФОП (поле OR_1047 не пусте)
    if (empty($order_custom_fields[$fopField])) {
        // Пропускаємо старі замовлення мовчки, щоб не засмічувати консоль
        continue;
    }

    // 2. Перевіряємо, чи вже був успішний платіж (через статус)
    if (isset($order_custom_fields[$statusField]) && $order_custom_fields[$statusField] === 'SUCCESS') {
        continue;
    }

    // 3. Додаткова перевірка: якщо статус не зберігся, але є коментар "Платіж №AC..."
    if (isset($order_custom_fields[$commentField]) && strpos($order_custom_fields[$commentField], 'Платіж №AC') !== false) {
        continue;
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

    // Невелика затримка, щоб не спамити API ПриватБанку та KeyCRM
    sleep(1);
}

echo "CRON завершено. Оброблено нових замовлень: {$processedCount}\n";
