<?php

use GuzzleHttp\Client;
use Shuchkin\SimpleXLSX;

class KastaV2
{
    private $db;
    private $products;
    public $error = [];
    public function __construct(){
        $this->db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
        $this->products = $this->products();
    }
    public  function products( )
    {
        $data = [];
        $cursor = null;
        do{
            $response = $this->request('/products/list'. $cursor, 'GET');
            foreach ($response['items'] as &$item){
                $item['barcode'] = $item['barcode'][0] ?? $item['unique_sku_id'];
                $item['name_color'] =$item['name_uk'] . "|". mb_strtolower($item['color']);
            }
            $data = array_merge($response['items'], $data);

            if($response['cursor']){
                $cursor = "?cursor=".$response['cursor'];
            }

        }while($response['cursor'] != null);


        return $data;
    }

    public function productsStock(){
        $products = $this->products();
        $data = [];
        foreach ($products as $product){
           $data[ $product['barcode'][0]] = $product['total_stock'];
        }

        return $data;
    }

    public function getCategories(){
        return $this->request('/supplier-content/category/all', 'GET');
    }

    public function getCategoryDetails($kindId, $affiliationId, $kayName = ''){
        $response =  $this->request("/supplier-content/category/details?kind_id=$kindId&affiliation_id=$affiliationId", 'GET');

        $details =  array_column($response['schema'],null, 'key_name');

        if($kayName){
            return  array_column($details[$kayName]['value_ids'] , 'id','value');
        }

        return $details;
    }

    public function createCharacteristics($categoryDetails){

    }

    public function createProducts($data){
       $groupProducts =  $this->grouped($data);

    }

    public function uploadImage($data){
        return $this->request("/supplier-content/submit/image", 'POST', $data);
    }
    public function uploadProduct($data){
        return $this->request("/supplier-content/submit/products", 'POST', $data);
    }

    public function grouped($data){
        $grouped = [];
        $kastaProducts = $this->products;
        $kastaSkuProducts = array_column($kastaProducts, 'barcode');

        foreach ($data as $item) {
            if (strpos($item['sku'], '_') !== false) {
                if (strpos($item['sku'], 'В') === false) {
                    continue;
                }
            }


            if ( strpos($item['size'], '_') !== false) {
                continue;
            }

            if ( strpos($item['color'], '_') !== false) {
                continue;
            }

            //$item['color'] = mb_strtolower($item['color']);

            if ( strpos($item['color'], ' ') !== false) {
                continue;
            }

            if ( strpos($item['size'], 'В') !== false) {
                continue;
            }

            if(!$item['product']['isAddedKasta']){
                continue;
            }

            $productId = $item['product_id'];

            // Find color property
            $color = $item['color'];
            if(in_array($item['sku'], $kastaSkuProducts)){
                continue;
            }


            // Generate vendor_code (numeric only)

            $vendorCode = $color
                ? $item['product']['parentSku'] . abs(crc32($color)) // ID + numeric hash of the color
                : $item['product']['parentSku'] . '00000'; // Default for no color

            // Initialize grouping structure
            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'product' => $item['product'], // Add product details once per product_id
                    'colors' => [],
                    'color_article' => $item['product']['parentSku'],
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

        return $grouped;
    }

    public function generateDataCreateProducts($products){
        dump($products);
        $errors = [];
        $prestashop = new Prestashop();
        $corectColor = [
            "Шоколадний" => "Коричневий"
        ];
        $kastaProducts = array_unique(array_column($this->products , 'name_color'))  ;
        foreach ($products as $product){
            $data = [];

            $category = $product["product"]["category"]["name"];
            $category_parent_name =  $product["product"]["category"]["parent_name"];
            $kasta_kind_keycrm_category = $this->db->fetchOne("SELECT * FROM kasta_kind_keycrm_category WHERE keycrm_category_name  LIKE ? OR keycrm_category_name LIKE ?", ["%$category%","%$category_parent_name%"] );

            $prestashopProducts = $prestashop->getApiProducts($product["color_article"]) ;
            $colorsImage = array_column($prestashopProducts , 'images', 'color') ;

           // dump($colorsImage);
           // dump($kasta_kind_keycrm_category);
            if(!$kasta_kind_keycrm_category){
                $errors[] = 'kasta_kind_keycrm_category: none';
                $this->saveLog('kasta_kind_keycrm_category: none', 'logs/kasta_created_product.txt');
                dump($errors);
                continue;
            }
            $getCategoryDetails = $this->getCategoryDetails($kasta_kind_keycrm_category['kind_id'], $kasta_kind_keycrm_category['affiliation_id']);

            $kastaSizes =  array_column($getCategoryDetails['kasta_size']['sizecharts'][0]['sizes'] , 'id','value') + array_column($getCategoryDetails['kasta_size']['sizecharts'][1]['sizes']   , 'id','value') + array_column($getCategoryDetails['kasta_size']['sizecharts'][2]['sizes']   , 'id','value')  ;
            $kastaColor = array_column($getCategoryDetails[3]['value_ids'] , 'id','value');

            $subgroup = array_column($getCategoryDetails[14]['value_ids'] , 'id','value');
            $subgroupId =   $subgroup[$kasta_kind_keycrm_category['subgroup']];

            if(!$subgroupId){
                $errors[] = 'kasta_subgroup_id: none';
                $this->saveLog('kasta_subgroup_id: none', 'logs/kasta_created_product.txt');

            }

            $group = array_column($getCategoryDetails[13]['value_ids'] , 'id','value');
            $groupId =  $group[$kasta_kind_keycrm_category['group_name']];

            if(!$groupId){
                $errors[] = 'kasta_group_id: none';
                $this->saveLog('kasta_group_id: none', 'logs/kasta_created_product.txt');
            }


            foreach ($product['colors'] as $key => $color){

                $colors = [];
                if(!in_array($product["product"]['name']."|".mb_strtolower($key), $kastaProducts)){


                    $colors['kind_id'] =  (int)$kasta_kind_keycrm_category['kind_id'];
                    $colors['affiliation_id'] =  (int)$kasta_kind_keycrm_category['affiliation_id'];

                    $image = $colorsImage[$key][0];
                    if (!$image) {
                        $errors[] = 'image: none';
                        $this->saveLog($key . '- image: none', 'logs/kasta_created_product.txt');
                    }else{
                        $image = $this->uploadImage(['url' => $image]);
                        $this->saveLog($key . '- image: '. $image['path'], 'logs/kasta_created_product.txt');
                    }

                    foreach ($color['items'] as $size){
                        $size['color'] = $corectColor[$size['color']] ?? $size['color'];
                        $fullPrice = $size['product']['fullPrice'];
                        $specialPrice =  $size['product']['specialPrice'];


                        $colorId =  $kastaColor[$size['color']];
                        if(!$colorId){
                            $errors[] = $size['sku'] . '- kasta_color_id: none';
                            $this->saveLog($size['sku'] . '- kasta_color_id: none', 'logs/kasta_created_product.txt');
                        }

                        if (strpos($size['size'], '/') !== false) {
                            $size['size'] = strtok($size['size'], '/');
                        }

                        $sizeId =  $kastaSizes[$size['size']];
                        if(!$sizeId){
                            $sizeId = $this->sizeMapping($size['size']);
                        }

                        if(!$sizeId){
                            $errors[] = $size['sku'] .'- kasta_size_id: none';
                            $this->saveLog($size['sku'] .'- kasta_size_id: none', 'logs/kasta_created_product.txt');
                        }


                        $colors['data'][] = [
                            "color" => $size['color'],
                            "images" => [
                                $image['path']
                            ],
                            "name_uk" => $size['product']['name'],
                            "description_uk" => $size['product']['description'],
                            "brand" => "Twice",
                            "code" => $color['vendor_code'],
                            "old_price" => (double)(isset($fullPrice))? $fullPrice: $size['price'],
                            "new_price" => (double)(isset($specialPrice))? $specialPrice: $size['price'],
                            "docflow_code" =>  $size['sku'],
                            "barcode" => [$size['sku']],
                            "size" => $size['size'],
                            "stock" => $size['stock'],
                            "characteristics" => [
                                [
                                    "data" => [
                                        "ids" => [1830]
                                    ],
                                    "key_name" => "12"
                                ],
                                [
                                    "data" => [
                                        "ids" => [804]
                                    ],
                                    "key_name" => "6"
                                ],
                                [
                                    "data" => [
                                        "ids" => [787]
                                    ],
                                    "key_name" => "1" // Країна
                                ],
                                [
                                    "data" => [
                                        "ids" => [$subgroupId]
                                    ],
                                    "key_name" => "14" // Підгрупа
                                ],
                                [
                                    "data" => [
                                        "ids" => [$groupId]
                                    ],
                                    "key_name" => "13" // Група
                                ],
                                [
                                    "data" => [
                                        "ids" => [ $colorId ]
                                    ],
                                    "key_name" => "3" // Колір
                                ],
                                [
                                    "data" => [
                                        "sizes" => [
                                            "kasta_size" => $sizeId
                                        ]
                                    ],
                                    "key_name" => "kasta_size"
                                ]
                            ]
                        ];

                    }
                }
               dump($colors);
               $uploadProduct = $this->uploadProduct($colors);
               $this->saveLog(json_encode($uploadProduct), 'logs/kasta_created_product.txt');
                dump($uploadProduct);

                dump($errors);
                exit;
                $errors = [];
            }

        }


    }

    public function listBarcodes()
    {
        $data = [];
        $products =  $this->products();
        foreach ($products  as $product){
            if(isset($product['barcode'][0])){
                $data[] = $product['barcode'][0];
            }else{
                $data[] = $product['code'];
            }
        }
        return $data;

//
//        $barcodes =  array_column($products, "barcode" );
//
//        return array_filter(array_map(function($item) {
//            return isset($item[0]) ? $item[0] : null;
//        }, $barcodes));

    }
    public function formatDataStock($products, $inBarcodes) {

        $data = array_filter(array_map(function($product) use ($inBarcodes) {
            if (in_array($product['sku'], $inBarcodes)) {
                return [
                    'barcode' => $product['sku'],
                    'stock' => $product['quantity'] - $product['in_reserve']
                ];
            }
            return null;
        }, $products));

        return array_values($data); // Для перезапису індексів масиву
    }

    public function readDiscount()
    {
        if ( $xlsx = SimpleXLSX::parse('xlsx/discounts.xlsx') ) {
            $rows = $xlsx->rows();
            unset($rows[0]);
            return array_column($rows, 1,0);
        } else {
            echo SimpleXLSX::parseError();
        }
    }

    private  function calculateDiscountPrice($price, $percentage = 0)
    {
        $result = ( $price * $percentage / 100);
        return (int) round($price - $result );
    }
    public function formatDataPrice($products, $inBarcodes) {

        $data = array_filter(array_map(function($product) use ($inBarcodes) {
            if (in_array($product['sku'], $inBarcodes)) {
                $fullPrice = $product['product']['fullPrice'];
                $specialPrice =  $product['product']['specialPrice'];
                $price = (double)(isset($fullPrice))? $fullPrice: $product['price'];

                $specialPrice = (double)(isset($specialPrice))? $specialPrice: $product['price'];

                return [
                    'barcode' => $product['sku'],
                    'old_price' =>  $price,
                    'new_price' =>  $specialPrice,
                ];
            }
            return null;
        }, $products));

        return array_values($data); // Для перезапису індексів масиву
    }

    public function saveLog($text, $path){
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }

    public function updatePrice($items)
    {
        $data =  [
            'items' =>  $items
        ];
        $text = date("Y-m-d H:i:s"). " update price  "  ;
        $this->saveLog($text , 'logs/cron.txt');
        return $this->request('/products/update-price', 'POST',$data );

    }


    public function updateStock($items)
    {
        $data =  [
            'items' =>  $items
        ];
        $text = date("Y-m-d H:i:s"). " update stock  "  ;
        $this->saveLog($text , 'logs/cron.txt');
       return $this->request('/products/update-stock', 'POST',$data );

    }

    private function request($endpoint, $method, $data = []) {
        $client = new Client();

        try {
            $response = $client->request($method, KASTA_API_URL . $endpoint, [
                'headers' => [
                    'Authorization' => KASTA_API_TOKEN,
                    'Accept' => 'application/json',
                ],
                'json' => $data
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Отримати тіло відповіді (JSON з описом помилки)
            $errorBody = $e->getResponse()->getBody()->getContents();

            // Спробувати розпарсити JSON або вивести як є
            echo "Client error:\n" . $errorBody;
            $this->saveLog("Client error:\n" . $errorBody, 'logs/kasta_request.txt');

            // Можна також кинути виняток далі або повернути null
            return null;
        } catch (\Exception $e) {
            // Інші помилки, наприклад, з'єднання
            echo "General error:\n" . $e->getMessage();
            $this->saveLog(  "General error:\n" . $e->getMessage(), 'logs/kasta_request.txt');
            return null;
        }
    }


    private function sizeMapping($size)
    {
        $sizeMap = [
            32 => '3XS',
            34 => 'XXS',
            36 => 'XS',
            38 => 'S',
            40 => 'M',
            42 => 'L',
            44 => 'XL',
            46 => 'XXL',
            48 => '3XL',
            50 => '4XL',
            52 => '5XL',
            54 => '6XL',
            56 => '7XL',
            58 => '8XL',
            60 => '9XL',
            62 => '10XL',
            64 => '11XL',
            66 => '12XL',
            68 => '13XL',
            70 => '14XL',
            72 => '15XL'
        ];
        return isset($sizeMap[$size]) ? $sizeMap[$size] : null;
    }
}
