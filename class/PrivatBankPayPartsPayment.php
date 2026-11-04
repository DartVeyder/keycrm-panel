<?php

/**
 * Клас для роботи з сервісом "Оплата частинами" / "Миттєва розстрочка" ПриватБанку.
 * Офіційна документація: https://api.privatbank.ua/#p24/partPaymentApi
 * Базовий шлюз v2: https://payparts2.privatbank.ua/ipp/v2/payment/
 */
class PrivatBankPayPartsPayment
{
    private string $storeId;
    private string $password;
    private ?string $recipientId;

    // Базові URL та ендпоінти
    private const BASE_URL = 'https://payparts2.privatbank.ua/ipp/v2/payment';
    private const URL_DECLINE = '/decline';
    private const URL_STATE = '/state';
    private const URL_CANCEL = '/cancel';

    /**
     * Конструктор класу.
     *
     * @param string $storeId Ідентифікатор магазину (storeId)
     * @param string $password Пароль магазину з кабінету ПриватБанку
     * @param string|null $recipientId Ідентифікатор одержувача (необов'язковий)
     */
    public function __construct(string $storeId, string $password, ?string $recipientId = null)
    {
        $this->storeId = trim($storeId);
        $this->password = trim($password);
        $this->recipientId = $recipientId !== null ? trim($recipientId) : null;
    }

    /**
     * Повернення платежу / Скасування (Decline / Refund).
     * POST https://payparts2.privatbank.ua/ipp/v2/payment/decline
     *
     * Формула підпису (згідно з правилами без рухомої крапки / без копійок):
     * SIGNATURE = encodeBase64(SHA1(password + storeId + orderId + withoutFloatingPoint(amount) + password))
     * де withoutFloatingPoint(amount) — сума в копійках без коми (наприклад, 429100 для 4291.00)
     *
     * @param string $orderId Унікальний номер платежу / замовлення в системі (до 64 символів)
     * @param float|int|string $amount Остаточна сума покупки для повернення
     * @param string|null $recipientId Ідентифікатор одержувача (якщо відрізняється від дефолтного)
     * @return array Відповідь банку
     * @throws Exception
     */
    public function decline(string $orderId, $amount, ?string $recipientId = null): array
    {
        $orderId = trim($orderId);
        if (empty($orderId)) {
            throw new Exception("PayParts Error: orderId не може бути порожнім");
        }

        $numericAmount = (float)str_replace([' ', ','], ['', '.'], (string)$amount);
        if ($numericAmount <= 0) {
            throw new Exception("PayParts Error: сума повернення має бути більшою за 0");
        }

        // Сума в копійках без крапки для цифрового підпису: withoutFloatingPoint (наприклад, 429100 для 4291.00)
        $amountInCents = (string)(int)round($numericAmount * 100);

        // Розрахунок цифрового підпису: encodeBase64(SHA1(password + storeId + orderId + amountInCents + password))
        $stringToSign = $this->password . $this->storeId . $orderId . $amountInCents . $this->password;
        $signature = base64_encode(sha1($stringToSign, true));

        $payload = [
            'storeId'   => $this->storeId,
            'orderId'   => $orderId,
            'amount'    => (float)number_format($numericAmount, 2, '.', ''),
            'signature' => $signature,
        ];

        $targetRecipient = $recipientId ?? $this->recipientId;
        if (!empty($targetRecipient)) {
            $payload['recipientId'] = $targetRecipient;
        }

        $result = $this->sendPost(self::URL_DECLINE, $payload, $orderId);

        // Перевірка результату повернення
        $state = $result['state'] ?? null;
        if ($state === 'FAIL') {
            $errMsg = $result['message'] ?? 'Помилка повернення коштів в Оплата частинами';
            throw new Exception("PrivatBank PayParts Error: {$errMsg}");
        }

        if ($state !== 'SUCCESS') {
            $errMsg = $result['message'] ?? ($result['paymentState'] ?? 'Невідомий статус');
            // Якщо є повідомлення або помилка
            if (!empty($result['message']) && stripos($result['message'], 'успіш') === false) {
                throw new Exception("PrivatBank PayParts Error: {$errMsg}");
            }
        }

        return $result;
    }

    /**
     * Аліас для decline() з однаковим інтерфейсом refund($orderId, $amount).
     *
     * @param string $orderId
     * @param float|int|string $amount
     * @param string|null $recipientId
     * @return array
     * @throws Exception
     */
    public function refund(string $orderId, $amount, ?string $recipientId = null): array
    {
        return $this->decline($orderId, $amount, $recipientId);
    }

    /**
     * Отримання результату угоди / статусу платежу (State).
     * POST https://payparts2.privatbank.ua/ipp/v2/payment/state
     *
     * Формула підпису:
     * SIGNATURE = encodeBase64(SHA1(password + storeId + orderId + password))
     *
     * @param string $orderId
     * @param bool $showRefund Отримати деталі повернень (true / false)
     * @return array
     * @throws Exception
     */
    public function status(string $orderId, bool $showRefund = true): array
    {
        $orderId = trim($orderId);
        if (empty($orderId)) {
            throw new Exception("PayParts Error: orderId не може бути порожнім");
        }

        // Розрахунок цифрового підпису для запиту state
        $stringToSign = $this->password . $this->storeId . $orderId . $this->password;
        $signature = base64_encode(sha1($stringToSign, true));

        $payload = [
            'storeId'    => $this->storeId,
            'orderId'    => $orderId,
            'showRefund' => $showRefund ? 'true' : 'false',
            'signature'  => $signature,
        ];

        return $this->sendPost(self::URL_STATE, $payload, $orderId);
    }

    /**
     * Відправка POST-запиту до API ПриватБанку.
     *
     * @param string $endpoint
     * @param array $payload
     * @param string $orderId
     * @return array
     * @throws Exception
     */
    private function sendPost(string $endpoint, array $payload, string $orderId): array
    {
        $url = self::BASE_URL . $endpoint;
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Encoding: UTF-8',
            'Content-Type: application/json; charset=UTF-8',
            'Content-Length: ' . strlen($jsonPayload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logDebug("CURL Error for order {$orderId} to {$endpoint}: {$error}");
            throw new Exception("PayParts CURL Error: " . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (!$result || !is_array($result)) {
            $this->logDebug("Invalid JSON response for order {$orderId} (HTTP {$httpCode}): " . $response);
            throw new Exception("PayParts API Error (HTTP {$httpCode}): Порожня або некоректна відповідь від банку: " . substr($response, 0, 200));
        }

        $this->logDebug("Response for order {$orderId} from {$endpoint} (HTTP {$httpCode}): " . json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }

    /**
     * Допоміжний метод для логування відлагоджувальної інформації.
     *
     * @param string $message
     */
    private function logDebug(string $message): void
    {
        $logFile = dirname(__DIR__) . '/logs/privat_payparts_debug.log';
        $time = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$time}] {$message}\n", FILE_APPEND);
        @chmod($logFile, 0666);
    }
}

