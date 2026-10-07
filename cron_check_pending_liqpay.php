<?php
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/class/KeyCrmV2.php');
require_once(__DIR__ . '/class/LiqPayPayment.php');
require_once(__DIR__ . '/refund_config.php');
require_once(__DIR__ . '/class/SyncLogger.php');

$logger = new SyncLogger(__DIR__ . '/logs', true, true, 'liqpay_check_');
$keyCrm = new KeyCrmV2();

// Статуси, що означають "Повернення" (згідно webhook_change_order_status.php)
$statusIds = [31, 33, 34, 39, 79, 80, 115, 11, 38, 40, 117, 116];
// Додаємо сортування за часом оновлення, щоб найсвіжіші були першими
$filter = "filter[status_id]=" . implode(',', $statusIds) . "&sort=-updated_at";

$logger->info("Запуск CRON перевірки ЗАВИСЛИХ платежів LiqPay");

// Беремо 10 сторінок (до 500 останніх оновлених замовлень)
$ordersList = $keyCrm->orders($filter, 10);

if (empty($ordersList)) {
    $logger->info("Немає замовлень у статусах повернення.");
    exit;
}

$processedLogFile = __DIR__ . '/logs/cron_processed_orders.txt';
$failedLogFile = __DIR__ . '/logs/cron_failed_orders.json';

// Функція для пошуку конфігу ФОП (скопійована з refund.php)
function resolveFopConfigLocal($fopName, $config) {
    if (empty($fopName)) return [null, null];
    $fopName = trim($fopName);
    if (isset($config[$fopName])) return [$fopName, $config[$fopName]];
    
    $aliases = [
        'Приват ФОП Мамочка А.' => 'Приватбанк ФОП Мамочка А.А',
        'Приват ФОП Райша' => 'ФОП Райша Приват',
        'Приват ФОП Василишин М.В' => 'ФОП Василишин М, Приват',
        'Приватбанк ФОП Василишин М.В' => 'ФОП Василишин М, Приват',
        'Приватбанк ФОП Райша' => 'ФОП Райша Приват',
    ];
    if (isset($aliases[$fopName]) && isset($config[$aliases[$fopName]])) return [$aliases[$fopName], $config[$aliases[$fopName]]];
    
    $cleanName = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $fopName));
    foreach ($config as $key => $cfg) {
        $cleanKey = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $key));
        if ($cleanName === $cleanKey) return [$key, $cfg];
    }
    
    if (preg_match('/(?:фоп\s+)?([\p{L}]+)/ui', $fopName, $m)) {
        $surname = mb_strtolower($m[1]);
        $isPrivat = (mb_stripos($fopName, 'приват') !== false);
        $isLiqpay = (mb_stripos($fopName, 'liqpay') !== false);
        
        foreach ($config as $key => $cfg) {
            if (mb_stripos(mb_strtolower($key), $surname) !== false) {
                if ($isPrivat && ($cfg['type'] ?? '') === 'privatbank') return [$key, $cfg];
                if ($isLiqpay && ($cfg['type'] ?? '') === 'liqpay') return [$key, $cfg];
            }
        }
    }
    return [null, null];
}

$failedOrders = file_exists($failedLogFile) ? json_decode(file_get_contents($failedLogFile), true) : [];
if (!is_array($failedOrders)) $failedOrders = [];
$failedChanged = false;

foreach ($ordersList as $order) {
    $orderId = $order['id'];

    $customFields = array_map(
        fn($v) => is_array($v) ? reset($v) : $v,
        array_column($order['custom_fields'] ?? [], 'value', 'uuid')
    );

    $comment1 = $customFields['OR_1046'] ?? '';
    $comment2 = $customFields['OR_1080'] ?? '';

    $isPending1 = strpos($comment1, 'Запит відправлено LiqPay, статус:') !== false && strpos($comment1, 'success') === false && strpos($comment1, 'reversed') === false && strpos($comment1, 'ПОМИЛКА') === false;
    $isPending2 = strpos($comment2, 'Запит відправлено LiqPay, статус:') !== false && strpos($comment2, 'success') === false && strpos($comment2, 'reversed') === false && strpos($comment2, 'ПОМИЛКА') === false;

    if (!$isPending1 && !$isPending2) {
        continue;
    }

    $logger->info("Знайдено зависле замовлення #{$orderId}");
    
    // Отримуємо повне замовлення для доступу до payments та buyer_comment
    $fullOrder = $keyCrm->order($orderId);
    if (!$fullOrder) continue;
    
    $fopsToCheck = [];
    if ($isPending1) $fopsToCheck[1] = ['ibanField' => 'OR_1047', 'commentField' => 'OR_1046'];
    if ($isPending2) $fopsToCheck[2] = ['ibanField' => 'OR_1060', 'commentField' => 'OR_1080'];

    foreach ($fopsToCheck as $fopIndex => $fields) {
        $logger->info("Перевіряємо ФОП {$fopIndex}...");
        $ibanKey = $customFields[$fields['ibanField']] ?? null;
        
        $liqpayOrderId = $orderId;
        $liqpayFallbackId = null;
        
        $buyerComment = $fullOrder['buyer_comment'] ?? '';
        if (preg_match('/^(\d+),([A-Za-z0-9\-]+)$/', trim($buyerComment), $m)) {
            $liqpayFallbackId = $m[1];
            $liqpayOrderId = $m[2];
        } else {
            if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $buyerComment, $m)) $liqpayOrderId = $m[1];
            if (preg_match('/LIQPAY\s*ID\s+(\d+)/i', $buyerComment, $m)) $liqpayFallbackId = $m[1];
        }

        if (!empty($customFields['OR_1034'])) {
            $liqpayOrderId = trim($customFields['OR_1034']);
        } else {
            if (!empty($fullOrder['payments']) && is_array($fullOrder['payments'])) {
                foreach ($fullOrder['payments'] as $payment) {
                    $pComment = $payment['description'] ?? $payment['comment'] ?? '';
                    if (preg_match('/SOID\s+([A-Za-z0-9\-]+)/i', $pComment, $matches)) {
                        $liqpayOrderId = $matches[1];
                        break;
                    }
                }
            }
        }
        
        if (empty($ibanKey) && !empty($fullOrder['payments'])) {
            foreach ($fullOrder['payments'] as $payment) {
                $desc = $payment['description'] ?? $payment['comment'] ?? '';
                $paymentMethodId = $payment['payment_method_id'] ?? null;
                if (($paymentMethodId == 61 || strpos($desc, 'PBK') !== false) && preg_match('/PBK\s+([a-zA-Z0-9]+)/i', $desc, $pbkMatches)) {
                    $pbk = $pbkMatches[1];
                    foreach ($config as $fopName => $cfg) {
                        if (($cfg['type'] ?? '') === 'liqpay' && ($cfg['public_key'] ?? '') === $pbk) {
                            $ibanKey = $fopName;
                            break 2;
                        }
                    }
                }
            }
        }

        if (empty($ibanKey)) {
            $logger->error("Не знайдено ключ ФОП для замовлення {$orderId}");
            continue;
        }

        list($matchedKey, $cfg) = resolveFopConfigLocal($ibanKey, $config);
        if (!$cfg || ($cfg['type'] ?? '') !== 'liqpay') {
            $logger->error("Конфіг не знайдено або це не LiqPay ({$ibanKey})");
            continue;
        }

        $api = new LiqPayPayment($cfg['public_key'], $cfg['private_key']);
        
        try {
            $result = $api->status($liqpayOrderId);
            $liqStatus = $result['status'] ?? 'unknown';

            if ($liqStatus === 'error' && $liqpayFallbackId) {
                $res2 = $api->status($liqpayFallbackId);
                if (isset($res2['status']) && $res2['status'] !== 'error') {
                    $result = $res2;
                    $liqStatus = $result['status'];
                }
            }

            $logger->info("Статус LiqPay: {$liqStatus}");

            if (in_array($liqStatus, ['reversed', 'success'])) {
                $paymentId = $result['payment_id'] ?? $result['order_id'] ?? 'N/A';
                $commentText = "Платіж уже успішно проведено (перевірено cron_check). Ref: {$paymentId}";
                $keyCrm->updateOrder($orderId, [
                    'custom_fields' => [
                        ["uuid" => $fields['commentField'], "value" => $commentText]
                    ]
                ]);
                $logger->success("Оновлено коментар на SUCCESS");
                
                // Додаємо в список успішних
                @file_put_contents($processedLogFile, $orderId . "\n", FILE_APPEND);
                @chmod($processedLogFile, 0666);
                
                if (isset($failedOrders[(string)$orderId])) {
                    unset($failedOrders[(string)$orderId]);
                    $failedChanged = true;
                }
                
            } elseif (in_array($liqStatus, ['error', 'failure'])) {
                $errDesc = $result['err_description'] ?? 'Unknown error';
                $commentText = "ПОМИЛКА: LiqPay повернув статус {$liqStatus} - {$errDesc}";
                $keyCrm->updateOrder($orderId, [
                    'custom_fields' => [
                        ["uuid" => $fields['commentField'], "value" => $commentText]
                    ]
                ]);
                
                if (isset($failedOrders[(string)$orderId])) {
                    unset($failedOrders[(string)$orderId]);
                    $failedChanged = true;
                }
                
                $logger->error("Оновлено коментар на ПОМИЛКА");
            } else {
                $logger->warning("Статус досі {$liqStatus}, нічого не змінюємо.");
            }

        } catch (Exception $e) {
            $logger->error("Помилка API LiqPay для {$orderId}: " . $e->getMessage());
        }
    }
    
    // Невелика затримка, щоб не спамити API
    sleep(1);
}

if ($failedChanged) {
    @file_put_contents($failedLogFile, json_encode($failedOrders, JSON_PRETTY_PRINT));
    @chmod($failedLogFile, 0666);
}

$logger->info("CRON перевірки ЗАВИСЛИХ платежів завершено.");
