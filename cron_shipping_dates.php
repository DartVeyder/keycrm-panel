<?php
if (php_sapi_name() === 'cli') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_USER_AGENT'] = 'CLI';
}
require_once __DIR__ . '/../../config/config.inc.php';
require_once __DIR__ . '/../../init.php';
require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/Base.php');
require_once('class/KeyCrmV2.php');
require_once('class/Prestashop.php');

// ================= НАЛАШТУВАННЯ =================
$customFieldIdForShippingDate = 'OR_1037';
// =================================================

$keyCrm = new KeyCrmV2();
$prestashop = new Prestashop();

echo "Starting cron for shipping dates...\n";

// Беремо останні оновлені замовлення. 
// Сортування по даті оновлення (-updated_at), 2 сторінки (до 100 замовлень)
$recentOrders = $keyCrm->orders('sort=-updated_at', 2);

echo "Fetched " . count($recentOrders) . " recently updated orders.\n";

foreach ($recentOrders as $order) {
    // Витягуємо всі додаткові поля замовлення у вигляді [uuid => value]
    $customFields = array_column($order['custom_fields'] ?? [], 'value', 'uuid');
    
    // Перевіряємо чи є наше поле і чи воно не порожнє
    if (isset($customFields[$customFieldIdForShippingDate])) {
        $val = $customFields[$customFieldIdForShippingDate];
        if (is_array($val)) {
            $val = $val[0] ?? '';
        }
        $newDate = trim((string)$val);
        
        if (!empty($newDate)) {
        
        // З KeyCRM витягуємо ID замовлення в PrestaShop
        // global_source_uuid зазвичай має формат "prestashop-1234-REFERENCE"
        $global_source_uuid = explode('-', $order['global_source_uuid'] ?? '');
        $idOrderPS = (int)($global_source_uuid[1] ?? 0);
        
        // Джерело 18 - це PrestaShop (бачили в webhook_change_order_status)
        // Можна додати перевірку на $order['source_id'] == 18, але наявність $idOrderPS достатня
        if ($idOrderPS > 0 && ($order['source_id'] == 18)) {
            
            $messageText = "Доброго дня!\n\nПовідомляємо, що на виробництві виникла затримка, через що дата відвантаження вашого замовлення змістилася.\n\nНова орієнтовна дата відвантаження: " . $newDate . ".\nЦю інформацію вже оновлено у вашому особистому кабінеті.\n\nПерепрошуємо за тимчасові незручності та дякуємо за ваше розуміння.";
            
            // Шукаємо в повідомленнях рядок, щоб зрозуміти, чи відправляли ми вже лист про цю конкретну дату
            $searchString = "Нова орієнтовна дата відвантаження: " . $newDate;
            
            if (!$prestashop->hasOrderMessage($idOrderPS, $searchString)) {
                echo "Sending notification for PS Order ID $idOrderPS (New Date: $newDate)\n";
                $success = $prestashop->addOrderMessage($idOrderPS, $messageText);
                if ($success) {
                    echo " - Notification sent successfully.\n";
                } else {
                    echo " - Failed to send notification.\n";
                }
            } else {
                // Повідомлення для цієї дати вже було відправлено
                // echo "Skipping PS Order ID $idOrderPS - already notified about $newDate\n";
            }
        }
        }
    }
}

echo "Done.\n";
