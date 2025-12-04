<?php

require_once('vendor/autoload.php');

require_once('config.php');
require_once('class/Base.php');
require_once('class/PrivatBankPayment.php');
require_once('class/KeyCrmV2.php');

// --- ПРИКЛАД ВИКОРИСТАННЯ ---


// 1. Налаштування (наприклад, зчитані з конфігураційного файлу)
$config = [
    "ФОП Райша Приват" => [
        'token' => '58d21a4f-01cf-414b-89a4-04cb656756bfQDssXEBHAW7j6Zas5tFeTZUq+xVHu86Auu5yH5mDZ/49MH8hnaELflMmhXUmKB/APLotDv6I7TDntOb8GIrxsVnmKAWo+3tNtUNWFdk7VuXu4jb1xzch77UCYA/wyUy0El6mYHmltNAlORXi8B9dO7BSz1xkoq3mBNcusqRLUE7a2KqlyaYy863DtJROaPQErgpqYgeV/+wjLoU+oeu8rQJO+5cI4g9lZDs9Sm6kdb4yeB8K2JYvdSe19CpXL4g=',
        'my_iban' => 'UA623052990000026008031050068',
        'my_name' => 'ФОП Райша Приват',
        'type' =>  'privatbank'
    ]
];
 
$keyCrm = new KeyCrmV2();

// UUID полів
$statusField  = 'OR_1042'; // Повернення статус
$commentField = 'OR_1046'; // Повернення коментар

// Файл для логів
$logFile = __DIR__ . '/logs/refund.log';

// Функція логування
function logMessage($orderId, $message, $logFile) {
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] [Order #{$orderId}] $message\n", FILE_APPEND);
}

try {
    // ------------------------------------------------------------
    // 1. Отримання замовлення та кастомних полів
    // ------------------------------------------------------------
   //$order = $keyCrm->order(211443);
    $orderId = $order['id'] ?? 'UNKNOWN';
 
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
        exit;
    }

    // ------------------------------------------------------------
    // 3. Валідація вхідних полів
    // ------------------------------------------------------------
    function requireField($array, $key, $fieldName) {
        if (!isset($array[$key]) || $array[$key] === '' || $array[$key] === null) {
            throw new Exception("Відсутнє або пусте поле: {$fieldName}");
        }
        return $array[$key];
    }

    $ibanKey  = requireField($order_custom_fields, 'OR_1041', 'IBAN ключ (OR_1041)');
    $iban     = requireField($order_custom_fields, 'OR_1039', 'Рахунок отримувача (OR_1039)');
    $edrpou   = requireField($order_custom_fields, 'OR_1043', 'ЄДРПОУ отримувача (OR_1043)');
    $amount   = requireField($order_custom_fields, 'OR_1038', 'Сума платежу (OR_1038)');
    $buyer    = requireField($order['buyer'], 'full_name', 'ПІБ покупця');

    logMessage($orderId, "INFO: Вхідні дані успішно перевірені", $logFile);

    // ------------------------------------------------------------
    // 4. Конфігурація
    // ------------------------------------------------------------
    $cfg = $config[$ibanKey] ?? null;
    if (!$cfg) throw new Exception("Конфіг не знайдено для ключа: {$ibanKey}");
    if (empty($cfg['token']))   throw new Exception("Порожній токен API у конфігу");
    if (empty($cfg['my_iban'])) throw new Exception("Порожній мій IBAN у конфігу");

    logMessage($orderId, "INFO: Конфіг для {$ibanKey} успішно знайдено", $logFile);

    // ------------------------------------------------------------
    // 5. Створення платежу
    // ------------------------------------------------------------
    switch ($cfg['type']) {
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
                "payment_destination" => "Поверненя коштів за повернений товар",
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

        default:
            throw new Exception("Непідтримуваний тип конфігу: {$cfg['type']}");
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
