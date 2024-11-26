<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Intertop
{
    private string $token;
    public $productsKeycrm = [];
    private $keycrm;

    public function __construct()
    {
        $this->keycrm =  new KeyCrm();
        $this->auth();
    }

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

    public  function create()
    {
        $productsCF = $this->getAddedProductWithCF();
        $offersKeycrm = $this->keycrm->product('[product_id]=' . implode(',',array_column($productsCF, 'id')));

        $groupedProducts = $this->grouped($offersKeycrm, $productsCF  );

        foreach ($groupedProducts  as $item){
            $this->createProductArray($item);

        }
        dd( $groupedProducts);
    }
    public function createProductArray($product){
        $data = [];
        $offers = [];
        $result = [];
        foreach ($product['colors'] as $key => $color){
            $colorId = $this->getColorId($key);
            $article = $product['color_article'] .$colorId;
            $productIT = [
                'vendor_code' =>  $product['color_article'],
                'color_article' =>  $product['color_article'],
                'article' => $article ,
                'active' => true,
                'sort' => 100,
                'category_id' => 2,
                'name' =>[['lang' => 'ua', 'value' => $product['product']['name']],['lang' => 'ru', 'value' => $product['product']['name']]],
                'description' =>[['lang' => 'ua', 'value' => $product['product']['description']],['lang' => 'ru', 'value' => $product['product']['description']]],
                'props'  => [
                    [
                        'id' => 7,
                        'value' =>15119
                    ],
                    [
                        'id' => 31,
                        'value' =>5323
                    ],
                    [
                        'id' => 32,
                        'value' =>6086
                    ],
                    [
                        'id' => 13,
                        'value' =>921
                    ],
                ]
            ];

            $request = $this->request('/products', 'POST', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                ], 'form_params' => $productIT]
            );




                $request = $this->request('/products/'.$article , 'PATCH', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken(),
                        ], 'form_params' => $productIT]
                );


            foreach ($color['items'] as $offer){
                $offerIT = [
                    'barcode'=>$offer['sku'],
                    'active' => true,
                    'base_price' => [
                        "amount" =>$offer['price'],
                        "currency" => 'UAH' ,
                    ],
                    "quantity" =>$offer['quantity'],
                ];

                $request = $this->request('/products/'.$article .'/offers', 'POST', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken(),
                        ], 'form_params' => $offerIT]
                ) ;
                print_r($request);
            }
        }

        dd( $data,$product );
    }

    private function getColorId($value)
    {
        $colors = [
            "бежевий" => "478",
            "білий" => "479",
            "безбарвний" => "480",
            "бірюзовий" => "481",
            "бордовий" => "482",
            "бронзовий" => "483",
            "блакитний" => "484",
            "колір не зазначено" => "485",
            "жовтий" => "486",
            "зелений" => "487",
            "золотий" => "488",
            "кораловий" => "489",
            "коричневий" => "490",
            "червоний" => "491",
            "малиновий" => "492",
            "помаранчевий" => "494",
            "рожевий" => "495",
            "салатовий" => "496",
            "срібний" => "497",
            "сірий" => "498",
            "синій" => "499",
            "бузковий" => "500",
            "фіолетовий" => "501",
            "чорний" => "502",
            "м'ятний" => "5796",
            "графіт" => "5798",
            "молочний" => "5799",
            "тауп" => "5800",
            "хакі" => "5801",
            "темно-сірий" => "6011",
            "світло-сірий" => "6038",
            "білий/чорний" => "6110"
        ];

        return (int) $colors[$value];
    }

    private  function  grouped($offersKeycrm, $productsCF  ){
        $grouped = [];

        foreach ($offersKeycrm as $item) {
            // Skip items where "sku" starts with "5555_"
            if (strpos($item['sku'], '_') !== false) {
                continue;
            }

            $productId = $item['product_id'];

            // Find color property
            $color = null;
            foreach ($item['properties'] as $property) {
                if ($property['name'] === "Колір") {
                    $color = $property['value'];
                    break;
                }
            }

            // Generate vendor_code (numeric only)
            $vendorCode = $color
                ? $item['id'] . abs(crc32($color)) // ID + numeric hash of the color
                : $item['id'] . '00000'; // Default for no color

            // Initialize grouping structure
            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'product' => $item['product'], // Add product details once per product_id
                    'colors' => [],
                    'color_article' => $productsCF[$item['product_id']]['parentSku'],
                    'product_id' => $productId
                ];
            }
            if ($color) {
                if (!isset($grouped[$productId]['colors'][$color])) {
                    $grouped[$productId]['colors'][$color] = [
                        'vendor_code' => $vendorCode,
                        'items' => []
                    ];
                }
                $grouped[$productId]['colors'][$color]['items'][] = $item;
            } else {
                if (!isset($grouped[$productId]['colors']['Без кольору'])) {
                    $grouped[$productId]['colors']['Без кольору'] = [
                        'vendor_code' => $vendorCode,
                        'items' => []
                    ];
                }
                $grouped[$productId]['colors']['Без кольору']['items'][] = $item;
            }
        }

       return   $grouped ;
    }



    public function getAddedProductWithCF(): array{

        $products = [];
        $productsKeycrm =  $this->keycrm->listProductsCustomFields();

        foreach ($productsKeycrm as $id => $product){
            if($product['isAddedIntertop']){
                $product['id'] = $id;
                $products[$id] =  $product;

            }
        }

        return $products;
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

    function saveProductsToJson(array $data, string $filename) {
        // Конвертуємо масив в JSON формат
        $jsonData = json_encode($data, JSON_PRETTY_PRINT);

        // Перевіряємо, чи вдалося конвертувати масив в JSON
        if ($jsonData === false) {
            return false; // Помилка конвертації
        }

        // Записуємо JSON дані у файл
        if (file_put_contents($filename, $jsonData) === false) {
            return false; // Помилка запису у файл
        }

        return true; // Успішне збереження
    }

    function readProductsFromJson(string $filename) {
        // Зчитуємо вміст файлу
        $jsonData = file_get_contents($filename);

        // Перевіряємо, чи вдалося зчитати файл
        if ($jsonData === false) {
            return false; // Помилка зчитування
        }

        // Конвертуємо JSON дані в масив
        $data = json_decode($jsonData, true);

        // Перевіряємо, чи вдалося конвертувати JSON в масив
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false; // Помилка конвертації
        }

        return $data; // Повертаємо зчитаний масив
    }


    public function getDataToUpdateQuantity()
    {
        $data = [];
        $products = $this->getProducts() ;
        $fileProducts = $this->readProductsFromJson('uploads/products.json');

        if( $fileProducts && count($products) == $fileProducts['total_records']){
            foreach ($fileProducts['data'] as $key => $product){
                $data[] = [
                    'barcode' => $product['barcode'],
                    'article' =>$product['article'],
                    'quantity' => ($this->productsKeycrm[$product["barcode"]] < 0) ? 0 : $this->productsKeycrm[$product["barcode"]],
                    "warehouse_external_id" => "default"
                ];
            }
            return   $data;
        }


        foreach ($products as $product){
            $data  = array_merge($data ,$this->getProductOffersBarcode( $product['article'])  );
        }


        $this->saveProductsToJson(['total_records' => count($products), 'data' =>$data], 'uploads/products.json');
        return $data;
    }

    private  function getOfferBarcode($data, $article)
    {
        $barcodesWithMp = array_map(function($item) use ($article) {
            return [
                'barcode' => $item["barcode"], // Додаємо ключ 'barcode'
                'article' => $article,          // Додаємо ключ 'article'
                'quantity' => ($this->productsKeycrm[$item["barcode"]] < 0) ? 0 : $this->productsKeycrm[$item["barcode"]],
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

    private function request($endpoint, $method, $params = [])
    {
        $client = new Client();
        try {
            $response = $client->request($method, INTERTOP_API_URL . $endpoint, $params);

            // Get the response body as a string
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            // Обробка помилок
            if ($e->hasResponse()) {
                $errorResponse =  $e->getResponse()->getBody()->getContents() ;
                if (isset($errorResponse['message'])) {
                    echo 'Error Message: ' . $errorResponse['message'];
                } else {
                    echo 'Request Error: ' . $e->getMessage();
                }
            } else {
                echo 'Request Error: ' . $e->getMessage();
            }
            return json_decode($errorResponse, true);
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

    public function saveLog($text, $path){
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }
}
