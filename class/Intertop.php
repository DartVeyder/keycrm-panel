<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Intertop
{
    private string $token;
    public $productsKeycrm = [];
    public  function auth()
    {
         $request =  $this->request('/auth', 'POST', [
              'form_params' => [
                  'app_key' => INTERTOP_API_APP_KEY,
                  'app_secret' => INTERTOP_API_APP_SECRET,
              ]
          ]);

         if($request['status'] == 'error'){
             return false;
         }

         $this->setToken($request['data']['access_token']['token']) ;
    }

    public function updateQuantity($offers)
    {
        return $this->request('/offers/quantity', 'PATCH', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'offers' => $offers
            ]
        ]);
    }

    public function getDataToUpdateQuantity()
    {
        $data = [];
        $products = $this->getProducts() ;
        foreach ($products as $product){
            $data  = array_merge($data ,$this->getProductOffersBarcode( $product['article'])  );

        }
        return $data;
    }

    private  function getOfferBarcode($data, $article)
    {
       ;
        $barcodesWithMp = array_map(function($item) use ($article) {
            return [
                'barcode' => $item["barcode"], // Додаємо ключ 'barcode'
                'article' => $article,          // Додаємо ключ 'article'
                'quantity' => $this->productsKeycrm[$item["barcode"]],
                "warehouse_external_id" => "default"
            ];
        }, $data);

        return $barcodesWithMp;
    }

    public function getProductsKeycrm()
    {
        $keyCrm = new KeyCrm();
        $products = $keyCrm->products();
        // Отримуємо масив SKU
        $skus = array_column($products, 'sku');

        // Отримуємо масив значень quantity - in_reserve
                $values = array_map(function($item) {
                    return $item['quantity'] - $item['in_reserve'];
                }, $products);

        // Формуємо асоціативний масив без циклу
                $result = array_combine($skus, $values);

        return $result;
    }

    public function getProductOffersBarcode($article)
    {


        $request = $this->request('/products/' . $article . '/offers', 'GET', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ]);

        return $this->getOfferBarcode($request['data']['items'] , $article);

    }

    public function getProducts() {
        $allItems = []; // Initialize an array to store all items.
        $offset = 0; // Start with the first page.

        do {

            $data = $this->request('/products?limit=300', 'GET', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                ],
            ]);

            if ($data['code'] !== 200 || $data['status'] !== 'success') {
                // Handle error appropriately (logging, throwing exception, etc.)
                break;
            }


            $allItems = array_merge($allItems, $data['data']['items']);

            // Get the total number of records to determine if more pages exist.
            $totalRecords = $data['data']['pagination']['total_records'];
            $limit = $data['data']['pagination']['limit'];

            // Calculate the new offset for the next request.
            $offset += $limit;
        } while ($offset < $totalRecords); // Continue until we reach the total number of records.

        return $allItems; // Return all fetched items.
    }

    private function request($endpoint, $method, $params = []){

        $client = new Client();
        try {
            $response = $client->request($method, INTERTOP_API_URL . $endpoint, $params);

            // Get the response body as a string
            return json_decode($response->getBody()->getContents(), 1);
        }catch (RequestException $e) {
            // Обробка помилок
            echo 'Request Error: ' . $e->getMessage();
            return null;
        }
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }
}
