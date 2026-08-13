<?php

require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/class/Base.php');
require_once(__DIR__ . '/class/PrivatBankPayment.php');
require_once(__DIR__ . '/class/LiqPayPayment.php');
require_once(__DIR__ . '/class/KeyCrmV2.php');

// --- ПРИКЛАД ВИКОРИСТАННЯ ---


// 1. Налаштування (наприклад, зчитані з конфігураційного файлу)
require_once(__DIR__ . '/refund_config.php');
 
$keyCrm = new KeyCrmV2();

// UUID полів
$statusField  = 'OR_1042'; // Повернення статус
$commentField = 'OR_1046'; // Повернення коментар

// Файл для логів
$logFile = __DIR__ . '/logs/refund.log';

if (!function_exists('logMessage')) {
    function logMessage($orderId, $message, $logFile) {
        $time = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$time] [Order #{$orderId}] $message\n", FILE_APPEND);
    }
}

try {
    // ------------------------------------------------------------
    // 1. Отримання замовлення та кастомних полів
    // ------------------------------------------------------------
   //$order = $keyCrm->order(211443);
    $orderId = $order['id'] ?? 'UNKNOWN';

    // Захист від паралельного (одночасного) виконання повернення для одного замовлення
    if ($orderId !== 'UNKNOWN') {
        $lockFile = sys_get_temp_dir() . '/keycrm_refund_' . $orderId . '.lock';
        $fpLock = fopen($lockFile, "c+");
        if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
            $msg = "WARNING: Процес повернення вже виконується іншим запитом (race condition)";
            logMessage($orderId, $msg, $logFile);
            echo $msg;
            return;
        }
    }
 
    $order_custom_fields = array_map(
        fn($v) => is_array($v) ? reset($v) : $v,
        array_column($order['custom_fields'], 'value', 'uuid')
    );

    // ------------------------------------------------------------
    // 2. Перевірка: якщо вже SUCCESS, платіж не створювати
    // ------------------------------------------------------------
    if (isset($order_custom_fields[$statusField]) && $order_custom_fields[$statusField] === 'SUCCESS') {
        $statusText  = "SUCCESS";
        $commentText = "Платіж уже успішно проведено. Новий платіж не створюється.";
 
        logMessage($orderId, "INFO: {$commentText}", $logFile);
        echo $statusText . " | " . $commentText;
        return;
    }

    if (isset($order_custom_fields[$commentField])) {
        $comment = $order_custom_fields[$commentField];
        if (strpos($comment, 'Платіж №AC') !== false || strpos($comment, 'Повернення LiqPay') !== false || strpos($comment, 'Запит відправлено LiqPay') !== false) {
            $statusText  = "SUCCESS";
            $commentText = "Платіж вже було ініційовано раніше (знайдено в коментарі). Новий платіж не створюється.";
            logMessage($orderId, "INFO: {$commentText}", $logFile);
            echo $statusText . " | " . $commentText;
            return;
        }
    }

    // ------------------------------------------------------------
    // 3. Валідація вхідних полів
    // ------------------------------------------------------------
    if (!function_exists('requireField')) {
        function requireField($array, $key, $fieldName) {
            if (!isset($array[$key]) || $array[$key] === '' || $array[$key] === null) {
                throw new Exception("Відсутнє або пусте поле: {$fieldName}");
            }
            return $array[$key];
        }
    }

    $ibanKey  = requireField($order_custom_fields, 'OR_1047', 'Ключ ФОП (OR_1047)');
    $amount   = requireField($order_custom_fields, 'OR_1038', 'Сума платежу (OR_1038)');
    
    // ------------------------------------------------------------
    // 4. Конфігурація
    // ------------------------------------------------------------
    $cfg = $config[$ibanKey] ?? null;
    if (!$cfg) throw new Exception("Конфіг не знайдено для ключа: {$ibanKey}");
    
    $type = $cfg['type'] ?? 'privatbank';
    
    if ($type === 'privatbank') {
        $iban     = requireField($order_custom_fields, 'OR_1039', 'Рахунок отримувача (OR_1039)');
        $edrpou   = requireField($order_custom_fields, 'OR_1043', 'ЄДРПОУ отримувача (OR_1043)');
        $buyer    = requireField($order['buyer'], 'full_name', 'ПІБ покупця');
        
        // Очищення даних від зайвих пробілів
        $iban = preg_replace('/\s+/', '', $iban);
        $edrpou = preg_replace('/\s+/', '', $edrpou);
        
        if (empty($cfg['token']))   throw new Exception("Порожній токен API у конфігу");
        if (empty($cfg['my_iban'])) throw new Exception("Порожній мій IBAN у конфігу");
    } elseif ($type === 'liqpay') {
        if (empty($cfg['public_key'])) throw new Exception("Порожній public_key у конфігу");
        if (empty($cfg['private_key'])) throw new Exception("Порожній private_key у конфігу");
    }

    logMessage($orderId, "INFO: Вхідні дані перевірені. ФОП: {$ibanKey}", $logFile);

    // ------------------------------------------------------------
    // 5. Створення платежу
    // ------------------------------------------------------------
    switch ($type) {
        case 'privatbank':
            $api = new PrivatBankPayment($cfg['token']);
            $today = date('d.m.Y');
            $document_number = "AC{$orderId}";
            $paymentData = [
                "document_number" => $document_number,
                "payer_account"       => $cfg['my_iban'],
                "recipient_account"   => $iban,
                "recipient_nceo"      => $edrpou,
                "payment_naming"      => $buyer,
                "payment_amount"      => $amount,
                "payment_destination" => "Повернення коштів за повернений товар замовлення {$orderId}",
                "payment_ccy"         => "UAH",
                "document_type"       => "cr",
                "payment_date"        => $today,
                "payment_accept_date" => $today,
            ];

            $result = $api->createWithForecast($paymentData);

            if (!empty($result['payment_ref'])) {
                $statusText  = "SUCCESS";
                $commentText = "Платіж №" . $document_number;
                logMessage($orderId, "SUCCESS: Платіж успішно створено. Ref: " . $result['payment_ref'], $logFile);
            } else {
                $statusText  = "SUCCESS";
                $commentText = "Запит відправлено, але ref не отримано.";
                logMessage($orderId, "WARNING: Ref не отримано", $logFile);
            }
            break;

        case 'liqpay':
            $api = new LiqPayPayment($cfg['public_key'], $cfg['private_key']);
            
            $liqpayOrderId = $orderId;
            
            if (!empty($order_custom_fields['OR_1034'])) {
                $liqpayOrderId = trim($order_custom_fields['OR_1034']);
                logMessage($orderId, "INFO: Знайдено ID LiqPay у полі OR_1034: {$liqpayOrderId}", $logFile);
            } else {
                // Спробуємо знайти SOID у коментарях до оплат (щоб повернути саме ту транзакцію)
                if (!empty($order['payments']) && is_array($order['payments'])) {
                    foreach ($order['payments'] as $payment) {
                        $comment = $payment['description'] ?? $payment['comment'] ?? '';
                        if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $comment, $matches)) {
                            $liqpayOrderId = $matches[1];
                            logMessage($orderId, "INFO: Знайдено SOID для LiqPay: {$liqpayOrderId}", $logFile);
                            break;
                        }
                    }
                }
            }

            $result = $api->refund($liqpayOrderId, $amount);

            if (isset($result['status']) && in_array($result['status'], ['reversed', 'success', 'wait_accept'])) {
                $statusText  = "SUCCESS";
                $paymentId = $result['payment_id'] ?? $result['order_id'] ?? 'N/A';
                $commentText = "Повернення LiqPay успішне (ID: {$paymentId})";
                logMessage($orderId, "SUCCESS: Повернення LiqPay успішне. Ref: {$paymentId}", $logFile);
            } else {
                $statusText  = "SUCCESS";
                $commentText = "Запит відправлено LiqPay, статус: " . ($result['status'] ?? 'unknown');
                logMessage($orderId, "WARNING: LiqPay повернув статус " . ($result['status'] ?? 'unknown'), $logFile);
            }
            break;

        default:
            throw new Exception("Непідтримуваний тип конфігу: {$type}");
    }

    // ------------------------------------------------------------
    // 6. Запис успіху у KeyCRM
    // ------------------------------------------------------------
    $keyCrm->updateOrder($orderId, [
        'custom_fields' => [
            ["uuid" => $statusField, "value" => $statusText],
            ["uuid" => $commentField, "value" => $commentText]
        ]
    ]);

    echo $statusText . " | " . $commentText;

} catch (Exception $e) {

    // ------------------------------------------------------------
    // 7. Помилка — запис у KeyCRM та лог
    // ------------------------------------------------------------
    $statusText  = "ERROR";
    $commentText = $e->getMessage();

    $orderIdForError = $order['id'] ?? 'UNKNOWN';
    $keyCrm->updateOrder($orderIdForError, [
        'custom_fields' => [
            ["uuid" => $statusField, "value" => $statusText],
            ["uuid" => $commentField, "value" => $commentText]
        ]
    ]);

    logMessage($orderIdForError, "ERROR: {$commentText}", $logFile);
    echo $statusText . " | " . $commentText;
}
