<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Intertop
{
    private string $token;
    public $productsKeycrm = [];
    private $keycrm;
    private $prestashop;

    public function __construct()
    {
        $this->keycrm =  new KeyCrm();
        $this->prestashop =  new Prestashop();
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
        $result = '';
        foreach ($groupedProducts  as $item){
            $createProductArray = $this->createProductArray($item);
            $return[] =   $createProductArray;

        }
        $this->renderTable($return);
        $log[date('Y-m-d H:i:s')] = $return;
        $this->saveLog(json_encode($log, JSON_UNESCAPED_UNICODE),'logs/log-import-intertop.txt');

    }

    // Function to render the table
    private function renderTable($data) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                    <th>Article</th>
                    <th>Color</th>
                    <th>Color Article</th>  
                    <th>Moderate Troubles</th>
                    <th>Errors</th>
                    <th>Offer SKU</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Offer Status</th>
               </tr>";

        foreach ($data as $item) {
            $article = $item['article'];

            foreach ($item['productColors'] as $colorArticle => $colorData) {
                $categoryID = $colorData['responseCreate']['data']['category_id'];
                $active = $colorData['responseCreate']['data']['active'] ? 'Yes' : 'No';
                $status = '';
                if (isset($colorData['responseCreate']['data']['moderate_troubles'])) {
                    $troubles = implode('<br>', $colorData['responseCreate']['data']['moderate_troubles']);
                    $status = "Created: ". $colorData['responseCreate']['status'];
                }else{
                    $troubles = implode('<br>', $colorData['responseUpdate']['data']['moderate_troubles']);
                    $status = "Updated: ". $colorData['responseUpdate']['status'];
                }

                $errors = '';

                // Перевірка на помилки в data['errors']
                if (isset($colorData['responseCreate']['data']['errors'])) {
                    $errorMessages = [];
                    foreach ($colorData['responseCreate']['data']['errors'] as $field => $fieldErrors) {
                        foreach ($fieldErrors as $error) {
                            $errorMessages[] = "{$field}: {$error}";
                        }
                    }
                    $errors = implode(", ", $errorMessages);
                }


                foreach ($colorData['offers'] as $offer) {
                    $sku = $offer['sku'];
                    $quantity = $offer['responseOfferUpdate']['data']['quantity'];
                    $statusOffer = $offer['responseOfferUpdate']['status'];

                    echo "<tr>";
                    echo "<td>{$article}</td>";
                    echo "<td>{$colorData['color']}</td>";
                    echo "<td>{$colorArticle}</td>";
                    echo "<td>{$troubles}</td>";
                    echo "<td>{$errors}</td>";
                    echo "<td>{$sku}</td>";
                    echo "<td>{$quantity}</td>";
                    echo "<td>{$status}</td>";
                    echo "<td>{$statusOffer}</td>";
                    echo "</tr>";
                }
            }
        }

        echo "</table>";
    }


    public function createProductArray($product){
        $data = [];
        $offers = [];
        $result = [];
        $log = '';
        foreach ($product['colors'] as $key => $color){


            $colorId = $this->getColorId($key);
            $article = $product['color_article'] . $this->generateColorCode($key);

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
                    ]
                ]
            ];

            if($this->getColorId($key) > 0){
                $productIT['props'][]= [
                    'id' => 8,
                    "value" => [$this->getColorId($key)]
                ];
            }


            $responseCreate = $this->request('/products', 'POST', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                    'Accept' => 'application/json',
                ], 'json' => $productIT]
            );

            /*if( $responseCreate['status_code'] == "validation_error"){
                $responseUpdate = $this->request('/products/'.$article , 'PATCH', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken(),
                            'Accept' => 'application/json',
                        ], 'json' => $productIT]
                );
            }*/


            $result[$article] = [
                'colorArticle' => $article,
                'request' => $productIT,
                'color' => $key,
                'responseCreate' =>$responseCreate,
                'responseUpdate' =>$responseUpdate
            ];


            $images = [];
            foreach ($color['items'] as $offer){
                $sizeId = $this->getDictionarySizeId( $offer['properties'][1]['value']);
                if(!$sizeId){
                    continue;
                }
                $offerIT = [
                    'barcode'=>$offer['sku'],
                    'active' => true,
                    'base_price' => [
                        "amount" =>$offer['price'],
                        "currency" => 'UAH' ,
                    ],
                    'discount_price' => [
                        "amount" =>$offer['price'],
                        "currency" => 'UAH' ,
                    ],
                    "quantity" =>$offer['quantity'],
                    'size_id' => $sizeId
                ];


                $responseOfferCreate[] = $this->request('/products/'.$article .'/offers', 'POST', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken(),
                            'Accept' => 'application/json',
                        ], 'json' => $offerIT]
                ) ;
                $responseOfferUpdate[] = $this->request('/products/'.$article .'/offers/'.$offer['sku'], 'PATCH', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->getToken(),
                            'Accept' => 'application/json',
                        ], 'json' => $offerIT]
                ) ;
                unset($offer['product']);
                $result[$article]['offers'][$offer['sku']] = [
                    'sku' => $offer['sku'],
                    'data' => $offer,
                    'requestOffer' => $offerIT,
                    'responseOfferCreate'=>$responseOfferCreate,
                    'responseOfferUpdate'=>$responseOfferUpdate
                ];


//                $prestashopProductCombination = $this->prestashop->getProductImagesByReference($offer['sku'] );
//
//                echo '<pre>';
//
//                if(  $prestashopProductCombination){
//
//                    $associationsImages =  $prestashopProductCombination['combinations'][0]['associations']['images'] ;
//                    $idProduct = $prestashopProductCombination['combinations'][0]['id_product'];
//                    $images = array_merge(   $images, array_column( $associationsImages , 'id'));
//                }


            }
//            $images = array_unique($images);
//            echo "<pre>";
//            foreach ($images as $image){
//                $responsePictures = $this->request('/products/'.$article . '/pictures', 'POST', [
//                        'headers' => [
//                            'Authorization' => 'Bearer ' . $this->getToken(),
//                        ], 'form_params' =>['url' => " https://twice.com.ua/api/images/products/$idProduct/$image?ws_key=4EKKVVWI6A26Q7D4LQE8HGKN8FUASW2P "]]
//                );
//                print_r(  $responsePictures);
//            }



        }
        return  [
            'article' => $product['color_article'],
            'product' => $product['product'],
            'productColors' => $result
        ];


    }

    private function getDictionarySizeId($value){
        $dictionaries = $this->request('/dictionaries/16/values?limit=1223', 'GET', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getToken(),
                ] ]
        );
        $dictionaries = array_column($dictionaries['data']['items'],'id','name');

        return (int)$dictionaries[mb_strtoupper($value)];
    }

    private function generateColorCode(string $color): string {
        // Генеруємо унікальний цифровий код на основі хешу назви кольору
        $hash = crc32($color);

        // Перетворюємо хеш у позитивне число та беремо останні 3 цифри
        $code = abs($hash) % 1000;

        // Додаємо провідні нулі, щоб код завжди був тризначним
        return str_pad((string)$code, 3, '0', STR_PAD_LEFT);
    }


    private function getmaterialId($value){
        $materials = [
            "c-tex/штучний мех" => 729,
            "с-tex/текстиль" => 730,
            "Штучна шкіра" => 733,
            "Штучна шерсть" => 734,
            "штучна шерсть/gore-tex" => 735,
            "Штучний мех" => 736,
            "шкіра/gore-tex" => 738,
            "Шкіра/штучна шкіра" => 739,
            "Шкіра/текстиль" => 740,
            "Мех" => 741,
            "Мех/Gore-tex" => 742,
            "Мікрофібра" => 743,
            "натур. мех на ткан. основі 3" => 744,
            "Поліуретан" => 746,
            "Текстиль" => 748,
            "Текстиль/gore-tex" => 749,
            "текстиль/Rieker-tex" => 750,
            "Текстиль/штучна шкіра" => 751,
            "Фліс" => 752,
            "Шерсть" => 753,
            "Шерсть/gore-tex" => 754,
            "Тенсель" => 755,
            "Полиестер" => 4529,
            "Байка" => 6269,
            "Без підкладки" => 6270,
            "Шкіра" => 6271,
            "Поліамід" => 6272,
            "Штучний мех/мех" => 6700,
            "Поліамід/полиуретан" => 6701,
            "Полиестер/полівінілхлорид" => 6702,
            "Полиестер/спандекс" => 6703,
            "Синтетичний текстиль" => 6704,
            "Текстиль/мікрофібра" => 6705,
            "Хлопок/штучний мех" => 6706,
            "Хлопок/шкіра" => 6707,
            "Хлопок/мікрофібра" => 6708,
            "Хлопок/полиестер" => 6709,
            "Еластан/полиамид" => 6710,
            "Пух" => 6897,
            "Акрил" => 11362,
            "Замша" => 11363,
            "Штучна шкіра/шкіра" => 11364,
            "Штучна шкіра/текстиль" => 11365,
            "Модал" => 11366,
            "Нейлон" => 11367,
            "Нубук" => 11368,
            "Полівінілхлорид (ПВХ)" => 11369,
            "Полиестер/шкіра" => 11370,
            "Полиестер/нейлон" => 11371,
            "Полиестер/полиуретан" => 11372,
            "Полиестер/текстиль" => 11373,
            "Синтетична шкіра" => 11374,
            "Текстиль/полиуретан" => 11375,
            "Хлопок" => 11376,
            "Шерсть/текстиль" => 11377,
            "Атлас" => 11402,
            "Повітропроникна сітка" => 11403,
            "Штучний матеріал" => 11404,
            "Шкіра/мікрофібра" => 11405,
            "Шкіра/полиуретан" => 11406,
            "Лайкра" => 11407,
            "Мембрана" => 11408,
            "Овеча шкіра" => 11409,
            "Овчина" => 11410,
            "Перероблений пластик" => 11411,
            "Перероблений полиестер" => 11412,
            "Полиуретан/нейлон" => 11413,
            "Полиестер/акрил" => 11414,
            "Полиестер/шкіра/полиуретан" => 11415,
            "Полиестер/полиуретан/нейлон" => 11416,
            "Полиестер/текстиль/полиуретан" => 11417,
            "Резина" => 11418,
            "Синтетика" => 11419,
            "Синтетична замша" => 11420,
            "Синтетичний матеріал" => 11421,
            "Віскоза" => 11504,
            "Ацетат" => 11517,
            "Замша/полиуретан" => 11518,
            "Шкіра/полиамид" => 11519,
            "Ліоцел" => 11520,
            "Нейлон/спандекс" => 11521,
            "Полиестер/полиамид" => 11522,
            "Синтапон" => 11523,
            "Текстиль/мех" => 11524,
            "Хлопок/віскоза" => 11525,
            "Етиленвінілацетат (ЕВА)" => 11526,
            "Штучний мех/шерсть" => 11621,
            "Шкіра/шерсть" => 11622,
            "Метал" => 11623,
            "Мех/полиуретан" => 11624,
            "Модакрил" => 11625,
            "Полиуретан/шерсть" => 11626,
            "Полиестер/шерсть" => 11627,
            "Трикотаж" => 11628,
            "Шовк/полиестер" => 11629,
            "Еластомультиестер" => 11630,
            "Веганська шкіра" => 14567
        ];

        return (int) $materials[$value];
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
                $quantity = ($this->productsKeycrm[$product["barcode"]] < 0) ? 0 : $this->productsKeycrm[$product["barcode"]];
                $data[] = [
                    'barcode' => $product['barcode'],
                    'article' =>$product['article'],
                    'quantity' =>  (int)$quantity,
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
                   // echo 'Error Message: ' . $errorResponse['message'];
                } else {
                   // echo 'Request Error: ' . $e->getMessage();
                }
            } else {
               // echo 'Request Error: ' . $e->getMessage();
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
