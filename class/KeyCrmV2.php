<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class KeyCrmV2
{
    public function listProducts($product_ids = null, $numPages = null)
    {

        if($product_ids){
            $filter = "filter[product_id]=$product_ids";
        }else{
            $filter = '';
        }

        $offers = $this->offers( $filter, $numPages);


        $products = $this->products( $filter , $numPages);

        if($product_ids){
            $filterOffersStock = "filter[offers_id]=". implode(',',array_column($offers,'id')) ;
        }else{
            $filterOffersStock = '';
        }

        $offersStock = $this->offersStock( $filterOffersStock, $numPages);

        foreach ($offers as &$offer) {
            $offer['product'] = $products[$offer['product_id']];
            $offer['stock'] = $offersStock[$offer['id']]['stock'] ?? 0;
        }

        return $offers;
    }

    public function offersStock($filter = '',$numPages = null): array
    {
        $page = 1;
        $limit = 50;
        $allData = [];

        do {
            $url = "/offers/stocks?limit={$limit}&filter[details]=true&$filter&page={$page}";

            try {
                $response = $this->request($url); // Викликаємо метод request для отримання даних
            } catch (Exception $e) {
                // Логування помилки, якщо запит не вдався
                echo "Помилка при отриманні даних акцій: " . $e->getMessage();
                break;
            }

            if (isset($response['data'])) {
                foreach ($response['data'] as &$offer) {
                    // Отримуємо дані складу
                    $offer['stock'] = $this->getStockWIthWarehouse($offer['warehouse'], ["Інтернет", "Twice Магазин"]);
                }
                // Об'єднуємо дані з усіма отриманими записами
                $allData = array_merge($allData, $response['data']);
            }

            // Перевіряємо наявність наступної сторінки
            $nextPageUrl = $response['next_page_url'] ?? null;
            if($page){
                if($page == $numPages){
                    break;
                }
            }
            $page++;

            // Затримка для запобігання перевищенню ліміту запитів, якщо це потрібно
            sleep(1);

        } while ($nextPageUrl); // Продовжуємо, поки є наступні сторінки

        return array_column($allData, null, 'id');
    }

    private function getStockWIthWarehouse($warehouses, $selected_names =[])
    {
        $sum = 0;
        foreach ($warehouses as $warehouse) {
            if (in_array($warehouse["name"], $selected_names)) {
                $sum += $warehouse["quantity"] - $warehouse["reserve"];
            }
        }

        return ($sum < 0) ? 0 : $sum;
    }

    public function offers($filter = '',$numPages = null): array
    {
        $page = 1;
        $limit = 50;
        $allData = [];
        $prestashop = new Prestashop();
        $getPreorderProducts = $prestashop->getPreorderProducts();
        $preorderProducts = array_column($getPreorderProducts['response'], null, 'reference');

        do {
            $url = "/offers?limit={$limit}&filter[is_archived]=false&include=product&sort=-product_id&$filter&page={$page}";

            try {
                $response = $this->request($url); // Assuming this method sends the request and returns the response
            } catch (Exception $e) {
                // Логування помилки
                echo "Помилка при отриманні пропозицій: " . $e->getMessage();
                break;
            }

            // Якщо відповідь містить дані, додаємо їх до allData
            if (isset($response['data'])) {
                foreach ($response['data'] as &$offer) {
                    // Отримуємо властивості пропозиції
                    if ($getOfferProperties = $this->getOfferProperties($offer['properties'])) {
                        $offer = array_merge($offer, $getOfferProperties);
                    }

                    // Якщо є преордерний товар, додаємо додаткові дані
                    if (array_key_exists($offer['sku'], $preorderProducts)) {
                        $offer['preorder_stock'] = $preorderProducts[$offer['sku']]['pre_order_product_quantity_limit'];
                        $offer['isPreorderOffer'] = 1;
                    }
                }

                // Об'єднуємо дані з усіма отриманими записами
                $allData = array_merge($allData, $response['data']);


            }

            // Перевіряємо, чи є наступна сторінка
            $nextPageUrl = $response['next_page_url'] ?? null;
            if($page){
                if($page == $numPages){
                    break;
                }
            }
            // Збільшуємо номер сторінки для наступного запиту
            $page++;

        } while ($nextPageUrl); // Продовжуємо, поки є наступні сторінки

        return $allData;
    }


    private  function getOfferProperties($properties){
        $data = [];
        foreach ($properties as $property){
            if( mb_strtolower( $property['name']) == 'колір'){
                $data['color'] = $property['value'];
            }

            if( mb_strtolower( $property['name']) == 'розмір'){
                $data['size'] = mb_strtoupper($property['value']);
            }
        }

        return $data;
    }


    public function products($filter = '',$numPages = null): array
    {
        $page = 1;
        $limit = 50;
        $allData = [];
        $categories = $this->categories();

        do {
            $url = "/products?limit={$limit}&include=custom_fields&$filter&page={$page}";

            // Викликаємо запит і обробляємо помилки
            try {
                $response = $this->request($url);
            } catch (Exception $e) {
                // Логування помилки
                echo "Помилка при отриманні продуктів: " . $e->getMessage();
                break;
            }

            // Перевіряємо, чи є дані в відповіді
            if (isset($response['data'])) {
                foreach ($response['data'] as &$product) {
                    // Додаємо категорії
                    $product['category'] = $categories[$product['category_id']] ?? [];

                    // Отримуємо кастомні поля
                    if ($getProductCustomFields = $this->getProductCustomFields($product['custom_fields'])) {
                        $product = array_merge($product, $getProductCustomFields);
                    }
                }
                // Об'єднуємо з уже отриманими даними
                $allData = array_merge($allData, $response['data']);
            }

            // Перевіряємо, чи є наступна сторінка
            $nextPageUrl = $response['next_page_url'] ?? null;
            if($page){
                if($page == $numPages){
                    break;
                }
            }
            // Збільшуємо номер сторінки для наступного запиту
            $page++;
        } while ($nextPageUrl);

        return array_column($allData, null, 'id');
    }


    private function getProductCustomFields($customFields)
    {
        $fields = ["CT_1007" => 'shortDescription',
            "CT_1009" => "parentSku" ,
            "CT_1011" => "isAddedPrestashop",
            "CT_1012" => "isActivePrestashop",
            "CT_1015" => "isAddedIntertop",
            "CT_1014" => "fullPrice",
            "CT_1010" => "specialPrice",
            "CT_1026" => "isPreorder",
            "CT_1029" => "isAddedKasta",
        ];
        $activeField = ['Так' => 1, 'Ні' => 0];
        $data = [];
        foreach ($customFields as  $customField){
            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1007'){
                $data[$fields[$customField['uuid']] ] = $customField['value'];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1009'){
                $data[$fields[$customField['uuid']]] = $customField['value'];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1011'){
                $data[$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
            }
            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1026'){
                $data[$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1012'){
                $data[$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1015'){
                $data[$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1029'){
                $data[$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1014'){
                $data[$fields[$customField['uuid']]] =  $customField['value'];
            }

            if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1010') {
                $data[$fields[$customField['uuid']]] = $customField['value'];
            }
        }

    return  $data ;
    }

    public function webhookOrder(){
        $json = file_get_contents('php://input');
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // fetch RAW input
            $json = file_get_contents('php://input');
            // decode json
            $object = json_decode($json, 1);
            // expecting valid json
            if (json_last_error() !== JSON_ERROR_NONE) {
                die(header('HTTP/1.0 415 Unsupported Media Type'));
            }
            file_put_contents('test_webhook.txt', print_r($object, true),FILE_APPEND);
            return $object['context'];
        }

        return null;
    }

    public function categories(){
        $page = 1;
        $limit = 50;
        $allData = [];

        do {
            $url = "/products/categories?limit=$limit&page={$page}";

            $response = $this->request($url); // Assuming this method sends the request and returns the response

            // If the response contains data, append it to the allData array
            if (isset($response['data'])) {
                $allData = array_merge($allData, $response['data']);
            }

            // Get the next page URL from the response
            $nextPageUrl = $response['next_page_url'] ?? null;

            // Increment the page number
            $page++;
        } while ($nextPageUrl);

            $categories = array_column($allData, null, 'id');
            // Сортуємо масив за ключем id
            ksort($categories);

            foreach ($categories as &$category) {
                if (!empty($category['parent_id']) && isset($categories[$category['parent_id']])) {
                    $parent = $categories[$category['parent_id']];
                    $category['parent_name'] = $parent['name'];
                    $category['full_name'] = $parent['name'] . ">" . $category['name'];
                } else {
                    $category['full_name'] = $category['name'];
                }
            }



        return $categories ;
    }

    private function request($endpoint, $method = "GET", $body = [])
    {
        $client = new Client();

        try {
            // Виконуємо запит до API
            $response = $client->request($method, KEYCRM_API_URL . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . KEYCRM_API_TOKEN,
                    'Accept' => 'application/json',
                ],
                'json' => $body
            ]);
        } catch (ClientException $e) {
            // Обробка помилки клієнта (наприклад, 400 чи 429)
            if ($e->getCode() == 429) {
                echo date("Y-m-d H:i:s")." API перевищено ліміт запитів. Чекаємо перед повтором..." . "</br>";
                sleep(20); // Затримка перед повтором
                return $this->request($endpoint, $method, $body); // Повторюємо запит
            } else {
                throw new Exception("Помилка запиту до API: " . $e->getMessage());
            }
        } catch (RequestException $e) {
            // Загальна обробка помилок запиту
            throw new Exception("Помилка при виконанні запиту: " . $e->getMessage());
        }


        // Отримуємо тіло відповіді як рядок і парсимо в масив
        return json_decode($response->getBody()->getContents(), true);
    }
    public function addTagOrder($orderId, $tagId){
       return $this->request("/order/$orderId/tag/$tagId",'POST');
    }

    public function updateClient($clientId,$data){
        return $this->request("/buyer/$clientId",'PUT',$data);
    }

    public function updateOrder($orderId,$data){
        return $this->request("/order/$orderId",'PUT',$data);
    }

    public function orders($filter = ''){
        $page = 1;
        $limit = 50;
        $allData = [];

        do {
            $url = "/orders?limit={$limit}&include=payments&$filter&page={$page}";

            $response = $this->request($url);

            if (isset($response['data'])) {
                foreach ($response['data'] as &$product){
                    if($getProductCustomFields = $this->getProductCustomFields($product['custom_fields'])){
                        $product = array_merge($product,  $getProductCustomFields);
                    }
                }
                $allData = array_merge($allData,  $response['data']);
            }

            $nextPageUrl = $response['next_page_url'] ?? null;

            $page++;
        } while ($nextPageUrl);
    }
}
