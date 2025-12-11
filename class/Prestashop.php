<?php
include('../../config/config.inc.php');
include('../../init.php');
include('/functions.php');
include('/header.inc.php');

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
                    return null;
                }
                return $order['orders'][0];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }

        return null;
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

    public function getStockAvailables(){
        try {
            $response = $this->client->request('GET', 'stock_availables', [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY,
                    'display' => '[id, id_product, id_product_attribute, quantity]',
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['stock_availables'];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function getProducts($display = 'full'){
        try {
            $response = $this->client->request('GET', 'products', [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY,
                    'display' => $display ,
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['products'];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function getApiProducts($reference = null) {
        try {
            $url = 'https://twice.com.ua/admin298rbunic/keycrm/api-product.php';

            // Якщо передано $reference — додаємо його як параметр до URL
            if ($reference !== null) {
                $url .= '?reference=' . urlencode($reference);
            }

            $response = $this->request($url, 'GET');

            return $response['data']['products'] ?? null;
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }


    public function getCombinations($display = 'full'){
        try {
            $response = $this->client->request('GET', 'combinations', [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY,
                    'display' => $display ,
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['combinations'];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function getPreorderProducts(){
        try {
            $response =  file_get_contents('https://twice.com.ua/admin298rbunic/keycrm/api-preorder.php');
             return json_decode($response, true);

        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function addTrackingNumber($id_order, $tracking_number, $id_carrier = 22)
{
    // Захист даних (Sanitization)
    $safe_tracking_number = pSQL($tracking_number);
    $safe_id_order = (int)$id_order;
    
    // Формуємо базовий SQL
    $sql = "UPDATE `" . _DB_PREFIX_ . "order_carrier` 
            SET `tracking_number` = '$safe_tracking_number' 
            WHERE `id_order` = $safe_id_order";

    // Якщо передано ID перевізника, додаємо умову
    if ($id_carrier) {
        $safe_id_carrier = (int)$id_carrier;
        $sql .= " AND `id_carrier` = $safe_id_carrier";
    }

    // Виконуємо запит
    return Db::getInstance()->execute($sql);
}


}
