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

    public function  getOrder($idOrder){
        try {
            $response = $this->client->request('GET', "orders/$idOrder", [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY,
                    'display' => 'full',
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $order =  json_decode($response->getBody(), true);
                if(!$order){
                    return $order;
                }
                return $order;
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }
    }
    public function changeOrderStatus($idOrder, $idOrderState){
$xmlData = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">
    <order_history>
        <id_order>$idOrder</id_order>
        <id_order_state>$idOrderState</id_order_state>
        <id_employee>1</id_employee>  
    </order_history>
</prestashop>";

        $response = $this->client->request('POST', 'order_histories', [
            'query' => [
                'ws_key' => PRESTASHOP_API_KEY
            ],
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
            'body' => $xmlData
        ]);
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
