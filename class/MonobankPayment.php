<?php

/**
 * Клас для роботи з інтернет-еквайрингом Monobank (Скасування оплати / Повернення коштів).
 * Документація: https://monobank.ua/api-docs/acquiring/methods/ia/post--api--merchant--invoice--cancel
 */
class MonobankPayment
{
    private string $token;
    private const BASE_URL = 'https://api.monobank.ua';

    /**
     * @param string $token X-Token мерчанта з кабінету еквайрингу Monobank
     */
    public function __construct(string $token)
    {
        $this->token = trim($token);
    }

    /**
     * Скасування оплати рахунку (повне або часткове повернення коштів).
     * POST /api/merchant/invoice/cancel
     *
     * @param string $invoiceId Ідентифікатор рахунку (інвойсу)
     * @param float|int|null $amount Сума для часткового повернення в грн (переводиться в копійки). Якщо null/0 — повне скасування.
     * @param string|null $extRef Референс скасування на боці мерчанта
     * @param array|null $items Список товарів для фіскалізації повернення (якщо увімкнено ПРРО)
     * @return array Відповідь API Monobank
     * @throws Exception
     */
    public function cancel(string $invoiceId, $amount = null, ?string $extRef = null, ?array $items = null): array
    {
        $invoiceId = trim($invoiceId);
        if (empty($invoiceId)) {
            throw new Exception("Monobank Error: invoiceId не може бути порожнім");
        }

        $payload = [
            'invoiceId' => $invoiceId,
        ];

        if ($extRef !== null && $extRef !== '') {
            $payload['extRef'] = (string)$extRef;
        }

        if ($amount !== null && (float)$amount > 0) {
            // Сума в копійках (ціле число)
            $payload['amount'] = (int)round((float)$amount * 100);
        }

        if (!empty($items)) {
            $payload['items'] = $items;
        }

        return $this->request('POST', '/api/merchant/invoice/cancel', $payload);
    }

    /**
     * Аліас для cancel() для однакового інтерфейсу з LiqPayPayment.
     *
     * @param string $invoiceId
     * @param float|int|null $amount
     * @param string|null $extRef
     * @param array|null $items
     * @return array
     * @throws Exception
     */
    public function refund(string $invoiceId, $amount = null, ?string $extRef = null, ?array $items = null): array
    {
        return $this->cancel($invoiceId, $amount, $extRef, $items);
    }

    /**
     * Перевірка статусу рахунку (інвойсу).
     * GET /api/merchant/invoice/status
     *
     * @param string $invoiceId
     * @return array
     * @throws Exception
     */
    public function status(string $invoiceId): array
    {
        $invoiceId = trim($invoiceId);
        if (empty($invoiceId)) {
            throw new Exception("Monobank Error: invoiceId не може бути порожнім");
        }

        return $this->request('GET', '/api/merchant/invoice/status?invoiceId=' . urlencode($invoiceId));
    }

    /**
     * Виконання HTTP запиту до API Monobank.
     *
     * @param string $method
     * @param string $endpoint
     * @param array|null $payload
     * @return array
     * @throws Exception
     */
    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        if (empty($this->token)) {
            throw new Exception("Monobank API Error: відсутній або порожній токен (X-Token) мерчанта");
        }

        $url = self::BASE_URL . $endpoint;
        $ch = curl_init();

        $headers = [
            'X-Token: ' . $this->token,
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $jsonData = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Monobank CURL Error: " . $curlError);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && !empty($response)) {
            throw new Exception("Monobank JSON Parse Error: " . substr($response, 0, 200));
        }

        $logsDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0777, true);
        }
        $debugLogFile = $logsDir . '/monobank_debug.log';

        // Перевірка на помилки (HTTP >= 400 або наявність errCode)
        if ($httpCode >= 400 || (is_array($decoded) && isset($decoded['errCode']))) {
            $errCode = $decoded['errCode'] ?? "HTTP_{$httpCode}";
            $errText = $decoded['errText'] ?? ($decoded['message'] ?? $response);

            $logMsg = date('[Y-m-d H:i:s]') . " Monobank Error ({$httpCode}) [{$errCode}]: {$errText} | Endpoint: {$endpoint} | Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
            @file_put_contents($debugLogFile, $logMsg, FILE_APPEND);
            @chmod($debugLogFile, 0666);

            throw new Exception("Monobank Error: {$errText} ({$errCode})");
        }

        // Логування успішної операції
        $logMsg = date('[Y-m-d H:i:s]') . " Monobank Success ({$httpCode}): " . json_encode($decoded, JSON_UNESCAPED_UNICODE) . " | Endpoint: {$endpoint}\n";
        @file_put_contents($debugLogFile, $logMsg, FILE_APPEND);
        @chmod($debugLogFile, 0666);

        return is_array($decoded) ? $decoded : [];
    }
}
