<?php

require 'vendor/autoload.php'; // Переконайтеся, що підключено автозавантажувач Composer

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Клас для створення платежів через API "Автоклієнт" ПриватБанку.
 * Реалізовано згідно з документацією: створення платежу та створення з прогнозом.
 */
class PrivatBankPayment
{
    private string $token;
    private Client $client;
    
    // Базовий URL API
    private const BASE_URL = 'https://acp.privatbank.ua/api/proxy/payment';
    
    // Ендпоінти
    private const URL_CREATE = '/create';
    private const URL_CREATE_PRED = '/create_pred';

    /**
     * Конструктор класу.
     * @param string $token Токен доступу (з налаштувань Автоклієнта)
     */
    public function __construct(string $token)
    {
        $this->token = $token;
        // Ініціалізація клієнта Guzzle
        $this->client = new Client([
            'timeout'  => 30.0,
            'http_errors' => false, // Не викидати виключення на 4xx/5xx кодах, обробляємо вручну
            'verify' => true, // Перевірка SSL сертифікатів
        ]);
    }

    /**
     * Створення платежу (без прогнозу).
     * URL: https://acp.privatbank.ua/api/proxy/payment/create
     * * @param array $paymentData Масив даних платежу (document_number, recipient_account тощо)
     * @return array Відповідь API (JSON)
     * @throws Exception
     */
    public function create(array $paymentData): array
    {
        return $this->sendPostRequest(self::URL_CREATE, $paymentData);
    }

    /**
     * Створення платежу з прогнозом.
     * URL: https://acp.privatbank.ua/api/proxy/payment/create_pred
     * * @param array $paymentData Масив даних платежу
     * @return array Відповідь API (JSON)
     * @throws Exception
     */
    public function createWithForecast(array $paymentData): array
    {
        return $this->sendPostRequest(self::URL_CREATE_PRED, $paymentData);
    }

    /**
     * Валідація та підготовка даних перед відправкою.
     * Згідно документації: "Усі реквізити типу string".
     * * @param array $data
     * @return array
     */
    private function prepareData(array $data): array
    {
        $prepared = [];
        foreach ($data as $key => $value) {
            // Примусове приведення до рядка
            $prepared[$key] = (string) $value;
        }
        return $prepared;
    }

    /**
     * Внутрішній метод відправки POST запиту.
     * * @param string $endpoint
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function sendPostRequest(string $endpoint, array $data): array
    {
        $url = self::BASE_URL . $endpoint;
        
        // Формуємо JSON з кодуванням UNICODE (щоб зберегти кирилицю)
        $jsonData = json_encode($this->prepareData($data), JSON_UNESCAPED_UNICODE);

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8', // Явно вказуємо utf-8
                    'token'        => $this->token,
                    'User-Agent'   => 'PrivatBank-Autoclient-PHP'
                ],
                'body' => $jsonData
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getBody()->getContents();

            $decodedResponse = json_decode($content, true);

            // Перевірка на помилку декодування JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON Decode Error: " . json_last_error_msg() . " | Raw response: " . $content);
            }

            // Обробка HTTP кодів помилок (400, 401, 500 тощо)
            if ($statusCode >= 400) {
                $this->handleError($statusCode, $decodedResponse);
            }

            return $decodedResponse;

        } catch (GuzzleException $e) {
            throw new Exception("Guzzle HTTP Error: " . $e->getMessage());
        }
    }

    /**
     * Обробка помилок API згідно документації.
     * * @param int $httpCode
     * @param array|null $responseBody
     * @throws Exception
     */
    private function handleError(int $httpCode, ?array $responseBody)
    {
        $message = "API Error ($httpCode)";
        $serviceCode = "";
        
        // Додаємо повідомлення про помилку з відповіді
        if (isset($responseBody['message'])) {
            $message .= ": " . $responseBody['message'];
        }
        
        // Додаємо сервісний код помилки (наприклад, PMTSRV0112)
        if (isset($responseBody['serviceCode'])) {
            $serviceCode = $responseBody['serviceCode'];
            $message .= " [Service Code: $serviceCode]";
        }
        
        // Можна розширити логіку для специфічних кодів помилок, якщо потрібно
        // Наприклад, PMTMDL0016 - "Вказано одночасно і картку та рахунок одержувача"
        
        throw new Exception($message, $httpCode);
    }
}
