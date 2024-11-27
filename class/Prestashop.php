<?php
use GuzzleHttp\Client;
class Prestashop extends Base
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://twice.com.ua/api/',
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Отримати продукт за reference
     *
     * @param string $reference Унікальний код товару
     * @return array|null Дані про товар або null, якщо запит не успішний
     */
    public function getProductByReference(string $reference): ?array
    {
        try {
            $response = $this->client->request('GET', 'products', [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY,
                    'display' => 'full',
                    'filter[reference]' => $reference,
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }

        return null;
    }

    public function getProductImagesByReference(string $reference): ?array
    {
        try {
            $response = $this->client->request('GET', 'combinations', [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY,
                    'display' => 'full',
                    'filter[reference]' => $reference,
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }

        return null;
    }

}
