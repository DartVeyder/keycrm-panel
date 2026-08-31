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

    // Визначаємо, який ФОП використовується
    $ibanKey1 = $order_custom_fields['OR_1047'] ?? null;
    $amount1  = $order_custom_fields['OR_1038'] ?? null;
    $ibanKey2 = $order_custom_fields['OR_1060'] ?? null;
    $amount2  = $order_custom_fields['OR_1059'] ?? null;

    $ibanKey = null;
    $amount = null;
    $usedFopIndex = 1;

    if (!empty($amount1)) {
        $ibanKey = $ibanKey1;
        $amount = $amount1;
        $usedFopIndex = 1;
    } elseif (!empty($amount2)) {
        $ibanKey = $ibanKey2;
        $amount = $amount2;
        $usedFopIndex = 2;
    } else {
        $ibanKey = $ibanKey1;
    }
    
    // Встановлюємо правильне поле для коментаря
    $commentField = $usedFopIndex === 2 ? 'OR_1080' : 'OR_1046';

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


    $liqpayOrderIdFromComment = null;
    $liqpayPaymentIdFromComment = null;
    $buyerComment = $order['buyer_comment'] ?? '';

    // Парсинг buyer_comment (може бути "LiqPayID,SOID" або детальний "LIQPAY ID ... SOID ... PBK ...")
    if (!empty($buyerComment)) {
        $trimmedComment = trim($buyerComment);
        if (preg_match('/^(\d+),([A-Za-z0-9\-]+)$/', $trimmedComment, $m)) {
            $liqpayPaymentIdFromComment = $m[1];
            $liqpayOrderIdFromComment = $m[2];
        } else {
            if (preg_match('/LIQPAY\s*ID\s+(\d+)/i', $trimmedComment, $m)) {
                $liqpayPaymentIdFromComment = $m[1];
            }
            if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $trimmedComment, $m)) {
                $liqpayOrderIdFromComment = $m[1];
            }
            if (preg_match('/PBK\s+([a-zA-Z0-9]+)/i', $trimmedComment, $m)) {
                $pbk = $m[1];
                foreach ($config as $fopName => $cfg) {
                    if (($cfg['type'] ?? '') === 'liqpay' && ($cfg['public_key'] ?? '') === $pbk) {
                        if (strpos($fopName, 'Передоплата') === false) {
                            $ibanKey = $fopName;
                            break;
                        } else {
                            $ibanKey = $fopName; // Fallback
                        }
                    }
                }
            }
        }
    }

    if (empty($ibanKey) && !empty($order['payments']) && is_array($order['payments'])) {
        // Спроба автоматично визначити ФОП
        foreach ($order['payments'] as $payment) {
            $desc = $payment['description'] ?? $payment['comment'] ?? '';
            $paymentMethodId = $payment['payment_method_id'] ?? null;

            // 1. Пошук по PBK для LiqPay (PBK i31151286400) (тільки для LiqPay)
            if (($paymentMethodId == 61 || strpos($desc, 'PBK') !== false) && preg_match('/PBK\s+([a-zA-Z0-9]+)/i', $desc, $pbkMatches)) {
                $pbk = $pbkMatches[1];
                foreach ($config as $fopName => $cfg) {
                    if (($cfg['type'] ?? '') === 'liqpay' && ($cfg['public_key'] ?? '') === $pbk) {
                        if (strpos($fopName, 'Передоплата') === false) {
                            $ibanKey = $fopName;
                            break 2;
                        } else {
                            $ibanKey = $fopName; // Fallback
                        }
                    }
                }
            }
        }
    }

    if (empty($ibanKey)) {
        $fieldName = $usedFopIndex === 2 ? 'Ключ ФОП 2 (OR_1060)' : 'Ключ ФОП (OR_1047)';
        throw new Exception("Відсутнє або пусте поле: {$fieldName} і не вдалося визначити автоматично з оплат");
    }
    
    if (empty($amount)) {
        throw new Exception("Відсутнє або пусте поле: Сума платежу ФОП 1 (OR_1038) або ФОП 2 (OR_1059)");
    }
    
    // ------------------------------------------------------------
    // 3.5. Захист від повторного повернення однакової суми
    // ------------------------------------------------------------
    $amountLockFile = __DIR__ . '/logs/refund_amounts_history.json';
    $amountsHistory = file_exists($amountLockFile) ? json_decode(file_get_contents($amountLockFile), true) : [];
    if (isset($amountsHistory[$orderId]) && in_array((string)$amount, $amountsHistory[$orderId])) {
        $msg = "Сума {$amount} вже була успішно повернена для замовлення {$orderId} раніше. Повторне повернення тієї ж самої суми заблоковано системою.";
        logMessage($orderId, "ERROR: {$msg}", $logFile);
        throw new Exception($msg);
    }

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
            $liqpayFallbackId = null;
            $usePaymentId = false;
            
            if (!empty($liqpayOrderIdFromComment) && !empty($liqpayPaymentIdFromComment)) {
                $liqpayOrderId = $liqpayOrderIdFromComment;
                $liqpayFallbackId = $liqpayPaymentIdFromComment;
                logMessage($orderId, "INFO: Знайдено SOID ({$liqpayOrderId}) та LiqPayID ({$liqpayFallbackId}) у buyer_comment", $logFile);
            } elseif (!empty($order_custom_fields['OR_1034'])) {
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

            try {
                $result = $api->refund($liqpayOrderId, $amount);
            } catch (Exception $e) {
                // Якщо платіж не знайдено по order_id, спробуємо по payment_id (перша частина)
                if ($liqpayFallbackId && strpos($e->getMessage(), 'Платіж не знайдено') !== false) {
                    logMessage($orderId, "WARNING: SOID не знайдено, пробуємо використати payment_id: {$liqpayFallbackId}", $logFile);
                    $result = $api->refund($liqpayFallbackId, $amount);
                } else {
                    throw $e;
                }
            }

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
    // 6. Запис успіху у KeyCRM та збереження суми
    // ------------------------------------------------------------
    if ($statusText === 'SUCCESS') {
        $amountsHistory[$orderId][] = (string)$amount;
        file_put_contents($amountLockFile, json_encode($amountsHistory));
    }
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

    $logText = $commentText;
    if (!empty($ibanKey)) {
        $logText .= " | ФОП: {$ibanKey}";
    }
    
    logMessage($orderIdForError, "ERROR: {$logText}", $logFile);
    echo $statusText . " | " . $commentText;
}
