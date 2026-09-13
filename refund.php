<?php

require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/class/Base.php');
require_once(__DIR__ . '/class/PrivatBankPayment.php');
require_once(__DIR__ . '/class/LiqPayPayment.php');
require_once(__DIR__ . '/class/KeyCrmV2.php');

// 1. Налаштування (зчитані з конфігураційного файлу)
require_once(__DIR__ . '/refund_config.php');
 
$keyCrm = new KeyCrmV2();

// UUID полів
$statusField   = 'OR_1042'; // Повернення статус
$commentField1 = 'OR_1046'; // Повернення коментар (ФОП 1)
$commentField2 = 'OR_1080'; // ФОП 2 Повернення коментар

// Файл для логів
$logFile = __DIR__ . '/logs/refund.log';

if (!function_exists('logMessage')) {
    function logMessage($orderId, $message, $logFile) {
        $time = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$time] [Order #{$orderId}] $message\n", FILE_APPEND);
        @chmod($logFile, 0666);
    }
}

if (!function_exists('requireField')) {
    function requireField($array, $key, $fieldName) {
        if (!isset($array[$key]) || $array[$key] === '' || $array[$key] === null) {
            throw new Exception("Відсутнє або пусте поле: {$fieldName}");
        }
        return $array[$key];
    }
}

if (!function_exists('isFopRefundDone')) {
    function isFopRefundDone($comment) {
        if (empty($comment)) return false;
        return (
            strpos($comment, 'Платіж №AC') !== false ||
            strpos($comment, 'Повернення LiqPay') !== false ||
            strpos($comment, 'Запит відправлено LiqPay') !== false ||
            strpos($comment, 'Запит відправлено, але ref не отримано') !== false ||
            strpos($comment, 'Платіж уже успішно проведено') !== false
        );
    }
}

if (!function_exists('resolveFopConfig')) {
    function resolveFopConfig($fopName, $config) {
        if (empty($fopName)) return [null, null];
        $fopName = trim($fopName);

        // 1. Прямий збіг
        if (isset($config[$fopName])) {
            return [$fopName, $config[$fopName]];
        }

        // 2. Специфічні аліаси між KeyCRM та config
        $aliases = [
            'Приват ФОП Мамочка А.' => 'Приватбанк ФОП Мамочка А.А',
            'Приват ФОП Райша' => 'ФОП Райша Приват',
            'Приват ФОП Василишин М.В' => 'ФОП Василишин М, Приват',
            'Приватбанк ФОП Василишин М.В' => 'ФОП Василишин М, Приват',
            'Приватбанк ФОП Райша' => 'ФОП Райша Приват',
        ];
        if (isset($aliases[$fopName]) && isset($config[$aliases[$fopName]])) {
            return [$aliases[$fopName], $config[$aliases[$fopName]]];
        }

        // 3. Збіг після нормалізації
        $cleanName = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $fopName));
        foreach ($config as $key => $cfg) {
            $cleanKey = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $key));
            if ($cleanName === $cleanKey) {
                return [$key, $cfg];
            }
        }

        // 4. Пошук за прізвищем та банком
        if (preg_match('/(?:фоп\s+)?([\p{L}]+)/ui', $fopName, $m)) {
            $surname = mb_strtolower($m[1]);
            $isPrivat = (mb_stripos($fopName, 'приват') !== false);
            $isLiqpay = (mb_stripos($fopName, 'liqpay') !== false);

            foreach ($config as $key => $cfg) {
                if (mb_stripos(mb_strtolower($key), $surname) !== false) {
                    if ($isPrivat && ($cfg['type'] ?? '') === 'privatbank') {
                        return [$key, $cfg];
                    }
                    if ($isLiqpay && ($cfg['type'] ?? '') === 'liqpay') {
                        return [$key, $cfg];
                    }
                }
            }
        }

        return [null, null];
    }
}

$fpLock = null;
$lockFile = null;
$lockAcquired = false;
$currentFopIndex = 1;

try {
    // ------------------------------------------------------------
    // 1. Отримання замовлення та кастомних полів
    // ------------------------------------------------------------
    $orderId = $order['id'] ?? 'UNKNOWN';

    // Захист від паралельного (одночасного) виконання повернення для одного замовлення
    if ($orderId !== 'UNKNOWN') {
        $locksDir = __DIR__ . '/logs/locks';
        if (!is_dir($locksDir)) {
            @mkdir($locksDir, 0777, true);
            @chmod($locksDir, 0777);
        }
        $lockFile = $locksDir . '/refund_' . $orderId . '.lock';
        $fpLock = @fopen($lockFile, "c+");
        if ($fpLock) {
            @chmod($lockFile, 0666);
            if (!flock($fpLock, LOCK_EX | LOCK_NB)) {
                @fclose($fpLock);
                $fpLock = null;
                $msg = "WARNING: Процес повернення вже виконується іншим запитом (race condition)";
                logMessage($orderId, $msg, $logFile);
                echo $msg;
                return;
            }
            $lockAcquired = true;
        }

        // Прибираємо старий застарілий lock-файл із системного /tmp, якщо він залишився
        $oldTmpLock = sys_get_temp_dir() . '/keycrm_refund_' . $orderId . '.lock';
        if (file_exists($oldTmpLock)) {
            @unlink($oldTmpLock);
        }
    }
 
    $order_custom_fields = array_map(
        fn($v) => is_array($v) ? reset($v) : $v,
        array_column($order['custom_fields'] ?? [], 'value', 'uuid')
    );

    // Зчитуємо дані по ФОП 1 та ФОП 2
    $rawAmount1 = $order_custom_fields['OR_1038'] ?? null;
    $ibanKey1   = $order_custom_fields['OR_1047'] ?? null;
    $comment1   = $order_custom_fields[$commentField1] ?? '';

    $rawAmount2 = $order_custom_fields['OR_1059'] ?? null;
    $ibanKey2   = $order_custom_fields['OR_1060'] ?? null;
    $comment2   = $order_custom_fields[$commentField2] ?? '';

    $cleanAmount1 = !empty($rawAmount1) ? (float)str_replace([' ', ','], ['', '.'], $rawAmount1) : 0;
    $cleanAmount2 = !empty($rawAmount2) ? (float)str_replace([' ', ','], ['', '.'], $rawAmount2) : 0;

    if ($cleanAmount1 <= 0 && $cleanAmount2 <= 0) {
        throw new Exception("Відсутнє або пусте поле: Сума платежу ФОП 1 (OR_1038) або ФОП 2 (OR_1059)");
    }

    $fop1Done = ($cleanAmount1 <= 0) || isFopRefundDone($comment1);
    $fop2Done = ($cleanAmount2 <= 0) || isFopRefundDone($comment2);

    // Якщо обидва ФОП уже мають успішні повернення — нічого не робимо
    if ($fop1Done && $fop2Done) {
        $statusText  = "SUCCESS";
        $commentText = "Платіж(і) вже було ініційовано раніше (знайдено в коментарях). Новий платіж не створюється.";
        logMessage($orderId, "INFO: {$commentText}", $logFile);
        echo $statusText . " | " . $commentText;
        return;
    }

    // Визначаємо, які саме ФОП потребують обробки
    $fopsToProcess = [];
    $targetFop = isset($_GET['fop']) ? (int)$_GET['fop'] : null;

    if ($targetFop === 1) {
        if ($cleanAmount1 > 0 && !$fop1Done) $fopsToProcess[] = 1;
    } elseif ($targetFop === 2) {
        if ($cleanAmount2 > 0 && !$fop2Done) $fopsToProcess[] = 2;
    } else {
        if ($cleanAmount1 > 0 && !$fop1Done) $fopsToProcess[] = 1;
        if ($cleanAmount2 > 0 && !$fop2Done) $fopsToProcess[] = 2;
    }

    if (empty($fopsToProcess)) {
        $statusText  = "SUCCESS";
        $commentText = "Всі зазначені суми для повернення вже успішно проведено.";
        logMessage($orderId, "INFO: {$commentText}", $logFile);
        echo $statusText . " | " . $commentText;
        return;
    }

    // Історія виплат для блокування дублікатів
    $amountLockFile = __DIR__ . '/logs/refund_amounts_history.json';
    $amountsHistory = file_exists($amountLockFile) ? json_decode(file_get_contents($amountLockFile), true) : [];
    if (!is_array($amountsHistory)) $amountsHistory = [];

    $results = [];

    foreach ($fopsToProcess as $usedFopIndex) {
        $currentFopIndex = $usedFopIndex;
        $amount = ($usedFopIndex === 1) ? $cleanAmount1 : $cleanAmount2;
        $ibanKey = ($usedFopIndex === 1) ? $ibanKey1 : $ibanKey2;
        $commentField = ($usedFopIndex === 1) ? $commentField1 : $commentField2;
        $fopLabel = "ФОП {$usedFopIndex}";

        // Спроба автоматично визначити ФОП для LiqPay (якщо не вибрано вручну)
        $liqpayOrderIdFromComment = null;
        $liqpayPaymentIdFromComment = null;
        $buyerComment = $order['buyer_comment'] ?? '';

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
                                $ibanKey = $fopName;
                            }
                        }
                    }
                }
            }
        }

        if (empty($ibanKey) && !empty($order['payments']) && is_array($order['payments'])) {
            foreach ($order['payments'] as $payment) {
                $desc = $payment['description'] ?? $payment['comment'] ?? '';
                $paymentMethodId = $payment['payment_method_id'] ?? null;

                if (($paymentMethodId == 61 || strpos($desc, 'PBK') !== false) && preg_match('/PBK\s+([a-zA-Z0-9]+)/i', $desc, $pbkMatches)) {
                    $pbk = $pbkMatches[1];
                    foreach ($config as $fopName => $cfg) {
                        if (($cfg['type'] ?? '') === 'liqpay' && ($cfg['public_key'] ?? '') === $pbk) {
                            if (strpos($fopName, 'Передоплата') === false) {
                                $ibanKey = $fopName;
                                break 2;
                            } else {
                                $ibanKey = $fopName;
                            }
                        }
                    }
                }
            }
        }

        if (empty($ibanKey)) {
            $fieldName = ($usedFopIndex === 2) ? 'Ключ ФОП 2 (OR_1060)' : 'Ключ ФОП 1 (OR_1047)';
            throw new Exception("Відсутнє або пусте поле: {$fieldName} і не вдалося визначити автоматично з оплат");
        }

        // Захист від повторного повернення однакової суми
        $amountLockKey = "fop{$usedFopIndex}_{$amount}";
        if (isset($amountsHistory[$orderId]) && in_array($amountLockKey, $amountsHistory[$orderId])) {
            $msg = "Сума {$amount} для {$fopLabel} вже була успішно повернена для замовлення {$orderId} раніше. Повторне повернення тієї ж самої суми заблоковано системою.";
            logMessage($orderId, "ERROR: {$msg}", $logFile);
            throw new Exception($msg);
        }

        // Пошук та валідація конфігурації ФОП
        list($matchedKey, $cfg) = resolveFopConfig($ibanKey, $config);
        if (!$cfg) {
            throw new Exception("Конфіг не знайдено для ключа: '{$ibanKey}' ({$fopLabel})");
        }
        $ibanKey = $matchedKey;
        $type = $cfg['type'] ?? 'privatbank';

        if ($type === 'privatbank') {
            $iban     = requireField($order_custom_fields, 'OR_1039', 'Рахунок отримувача (OR_1039)');
            $edrpou   = requireField($order_custom_fields, 'OR_1043', 'ЄДРПОУ отримувача (OR_1043)');
            $buyer    = requireField($order['buyer'] ?? [], 'full_name', 'ПІБ покупця');

            $iban = preg_replace('/\s+/', '', $iban);
            $edrpou = preg_replace('/\s+/', '', $edrpou);

            if (empty($cfg['token']))   throw new Exception("Порожній токен API у конфігу ({$ibanKey})");
            if (empty($cfg['my_iban'])) throw new Exception("Порожній мій IBAN у конфігу ({$ibanKey})");
        } elseif ($type === 'liqpay') {
            if (empty($cfg['public_key'])) throw new Exception("Порожній public_key у конфігу ({$ibanKey})");
            if (empty($cfg['private_key'])) throw new Exception("Порожній private_key у конфігу ({$ibanKey})");
        }

        logMessage($orderId, "INFO: [{$fopLabel}] Вхідні дані перевірені. ФОП: {$ibanKey}, сума: {$amount}", $logFile);

        // Створення платежу
        switch ($type) {
            case 'privatbank':
                $api = new PrivatBankPayment($cfg['token']);
                $today = date('d.m.Y');
                $docSuffix = ($usedFopIndex == 2) ? "2" : "";
                $document_number = "AC{$orderId}{$docSuffix}";
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
                    logMessage($orderId, "SUCCESS [{$fopLabel}]: Платіж успішно створено. Ref: " . $result['payment_ref'], $logFile);
                } else {
                    $statusText  = "SUCCESS";
                    $commentText = "Запит відправлено, але ref не отримано.";
                    logMessage($orderId, "WARNING [{$fopLabel}]: Ref не отримано", $logFile);
                }
                break;

            case 'liqpay':
                $api = new LiqPayPayment($cfg['public_key'], $cfg['private_key']);
                
                $liqpayOrderId = $orderId;
                $liqpayFallbackId = null;
                
                if (!empty($liqpayOrderIdFromComment) && !empty($liqpayPaymentIdFromComment)) {
                    $liqpayOrderId = $liqpayOrderIdFromComment;
                    $liqpayFallbackId = $liqpayPaymentIdFromComment;
                    logMessage($orderId, "INFO: [{$fopLabel}] Знайдено SOID ({$liqpayOrderId}) та LiqPayID ({$liqpayFallbackId}) у buyer_comment", $logFile);
                } elseif (!empty($order_custom_fields['OR_1034'])) {
                    $liqpayOrderId = trim($order_custom_fields['OR_1034']);
                    logMessage($orderId, "INFO: [{$fopLabel}] Знайдено ID LiqPay у полі OR_1034: {$liqpayOrderId}", $logFile);
                } else {
                    if (!empty($order['payments']) && is_array($order['payments'])) {
                        foreach ($order['payments'] as $payment) {
                            $pComment = $payment['description'] ?? $payment['comment'] ?? '';
                            if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $pComment, $matches)) {
                                $liqpayOrderId = $matches[1];
                                logMessage($orderId, "INFO: [{$fopLabel}] Знайдено SOID для LiqPay: {$liqpayOrderId}", $logFile);
                                break;
                            }
                        }
                    }
                }

                try {
                    $result = $api->refund($liqpayOrderId, $amount);
                } catch (Exception $e) {
                    if ($liqpayFallbackId && strpos($e->getMessage(), 'Платіж не знайдено') !== false) {
                        logMessage($orderId, "WARNING: [{$fopLabel}] SOID не знайдено, пробуємо використати payment_id: {$liqpayFallbackId}", $logFile);
                        $result = $api->refund($liqpayFallbackId, $amount);
                    } else {
                        throw $e;
                    }
                }

                if (isset($result['status']) && in_array($result['status'], ['reversed', 'success', 'wait_accept'])) {
                    $statusText  = "SUCCESS";
                    $paymentId = $result['payment_id'] ?? $result['order_id'] ?? 'N/A';
                    $commentText = "Повернення LiqPay успішне (ID: {$paymentId})";
                    logMessage($orderId, "SUCCESS [{$fopLabel}]: Повернення LiqPay успішне. Ref: {$paymentId}", $logFile);
                } else {
                    $statusText  = "SUCCESS";
                    $commentText = "Запит відправлено LiqPay, статус: " . ($result['status'] ?? 'unknown');
                    logMessage($orderId, "WARNING [{$fopLabel}]: LiqPay повернув статус " . ($result['status'] ?? 'unknown'), $logFile);
                }
                break;

            default:
                throw new Exception("Непідтримуваний тип конфігу: {$type}");
        }

        // Запис успішного повернення
        if ($statusText === 'SUCCESS') {
            $amountsHistory[$orderId][] = $amountLockKey;
            @file_put_contents($amountLockFile, json_encode($amountsHistory, JSON_PRETTY_PRINT));
            @chmod($amountLockFile, 0666);
        }

        // Оновлюємо коментар у KeyCRM для конкретного ФОП
        $keyCrm->updateOrder($orderId, [
            'custom_fields' => [
                ["uuid" => $statusField, "value" => $statusText],
                ["uuid" => $commentField, "value" => $commentText]
            ]
        ]);

        $results[] = "[{$fopLabel}] {$statusText} | {$commentText}";
    }

    echo implode("\n", $results);

} catch (Exception $e) {
    $statusText  = "ERROR";
    $commentText = $e->getMessage();

    $orderIdForError = $order['id'] ?? 'UNKNOWN';
    $errCommentField = ($currentFopIndex === 2) ? $commentField2 : $commentField1;

    $keyCrm->updateOrder($orderIdForError, [
        'custom_fields' => [
            ["uuid" => $statusField, "value" => $statusText],
            ["uuid" => $errCommentField, "value" => $commentText]
        ]
    ]);

    $logText = $commentText;
    if (!empty($ibanKey)) {
        $logText .= " | ФОП: {$ibanKey}";
    }
    
    logMessage($orderIdForError, "ERROR: {$logText}", $logFile);
    echo $statusText . " | " . $commentText;

} finally {
    if (is_resource($fpLock)) {
        if ($lockAcquired) {
            @flock($fpLock, LOCK_UN);
        }
        @fclose($fpLock);
        $fpLock = null;
    }
    if ($lockAcquired && $lockFile && file_exists($lockFile)) {
        @unlink($lockFile);
    }
}
