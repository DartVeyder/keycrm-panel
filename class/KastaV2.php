<?php

use GuzzleHttp\Client;
use Shuchkin\SimpleXLSX;

class KastaV2
{
    private $db;
    public function __construct(){
        $this->db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);
    }
    public  function products( )
    {
        $data = [];
        $cursor = null;
        do{
            $response = $this->request('/products/list'. $cursor, 'GET');

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

    public function grouped($data){
        $grouped = [];

        foreach ($data as $item) {
            if (strpos($item['sku'], '_') !== false) {
                if (strpos($item['sku'], 'В') === false) {
                    continue;
                }
            }

            $kasta = $this->db->fetchOne("SELECT * FROM kasta WHERE sku  = ?", [$item['sku']] );
            if(  !$kasta || $kasta['status'] == ''){
                continue;
            }

            if ( strpos($item['size'], '_') !== false) {
                continue;
            }

            if ( strpos($item['color'], '_') !== false) {
                continue;
            }

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


        foreach ($products as $product){
            $data = [];
            $category = $product["product"]["category"]["name"];

            $kasta_kind_keycrm_category = $this->db->fetchOne("SELECT * FROM kasta_kind_keycrm_category WHERE keycrm_category_name  LIKE ?", ["%$category%"] );

            dump($kasta_kind_keycrm_category);
            if(!$kasta_kind_keycrm_category){
                continue;
            }
            $getCategoryDetails = $this->getCategoryDetails($kasta_kind_keycrm_category['kind_id'], $kasta_kind_keycrm_category['affiliation_id']);

            $kastaSizes =  array_column($getCategoryDetails['kasta_size']['sizecharts'][0]['sizes'] , 'id','value') + array_column($getCategoryDetails['kasta_size']['sizecharts'][1]['sizes'] , 'id','value')  ;
            $kastaColor = array_column($getCategoryDetails[3]['value_ids'] , 'id','value');

            $subgroup = array_column($getCategoryDetails[14]['value_ids'] , 'id','value');
            $subgroupId =   $subgroup[$kasta_kind_keycrm_category['subgroup']];

//            if(!$subgroupId){
//                continue;
//            }

            $group = array_column($getCategoryDetails[13]['value_ids'] , 'id','value');
            $groupId =  $group[$kasta_kind_keycrm_category['group_name']];

//            if(!$groupId){
//                continue;
//            }

            $colors = [];
            foreach ($product['colors'] as $key => $color){
                $sizes = [];
                    foreach ($color['items'] as $colorItem){

                        $fullPrice = $colorItem['product']['fullPrice'];
                        $specialPrice =  $colorItem['product']['specialPrice'];


                        $colorId =  $kastaColor[$colorItem['color']];
//                        if(!$colorId){
//                            continue;
//                        }

                        $sizeId =  $kastaSizes[$colorItem['size']];
//                        if(!$sizeId){
//                            continue;
//                        }

                        $item = [];
                        $item['kind_id'] =  $kasta_kind_keycrm_category['kind_id'];
                        $item['affiliation_id'] =  $kasta_kind_keycrm_category['affiliation_id'];
                        $item['data'] = [
                            "color" => $colorItem['color'],
                            "images" => [],
                            "name_uk" => $colorItem['product']['name'],
                            "description_uk" => $colorItem['product']['description'],
                            "brand" => "Twice",
                            "code" => $color['vendor_code'],
                            "old_price" => (double)(isset($fullPrice))? $fullPrice: $colorItem['price'],
                            "new_price" => (double)(isset($specialPrice))? $specialPrice: $colorItem['price'],
                            "docflow_code" =>  $colorItem['sku'],
                            "barcode" => [$colorItem['sku']],
                            "size" => $colorItem['size'],
                            "stock" => $colorItem['stock'],
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
                        $sizes[] = $item;

                    }
                $colors[] = $sizes;
            }
            dump($colors);

        }

//        $array = [
//            "kind_id" => 31,
//            "affiliation_id" => 2098,
//            "update" => true,
//            "data" => [
//                [
//                    "color" => "Синій",
//                    "images" => [
//                        "https://hub.kasta.ua/supplier-content-uploads/b2store/supplier_provided_link/691/b7e/0d4/471/a4d/2be/d0e/5ee/ef6.jpeg"
//                    ],
//                    "name_uk" => "СОРОЧКА БАВОВНЯНА В КЛІТИНКУ СИНЯ TW 11",
//                    "brand" => "Twice",
//                    "description_uk" => "СОРОЧКА БАВОВНЯНА В КЛІТИНКУ СИНЯ TW 11",
//                    "code" => "25073004280",
//                    "old_price" => 1790,
//                    "new_price" => 1790,
//                    "docflow_code" => "250730042801",
//                    "barcode" => ["250730042801"],
//                    "characteristics" => [
//                        [
//                            "data" => [
//                                "ids" => [1830]
//                            ],
//                            "key_name" => "12"
//                        ],
//                        [
//                            "data" => [
//                                "ids" => [804]
//                            ],
//                            "key_name" => "6"
//                        ],
//                        [
//                            "data" => [
//                                "ids" => [787]
//                            ],
//                            "key_name" => "1" // Країна
//                        ],
//                        [
//                            "data" => [
//                                "ids" => [75928]
//                            ],
//                            "key_name" => "14" // Підгрупа
//                        ],
//                        [
//                            "data" => [
//                                "ids" => [75458]
//                            ],
//                            "key_name" => "13" // Група
//                        ],
//                        [
//                            "data" => [
//                                "ids" => [675]
//                            ],
//                            "key_name" => "3" // Колір
//                        ],
//                        [
//                            "data" => [
//                                "sizes" => [
//                                    "kasta_size" => 149360
//                                ]
//                            ],
//                            "key_name" => "kasta_size"
//                        ]
//                    ],
//                    "size" => "38",
//                    "stock" => 1
//                ]
//            ]
//        ];

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

    private function request($endpoint, $method, $data = []){
        $client = new Client();
        $response = $client ->request($method,  KASTA_API_URL . $endpoint, [
            'headers' => [
                'Authorization' =>  KASTA_API_TOKEN,
                'Accept' => 'application/json',
            ],
            'json' => $data
        ]);

        // Get the response body as a string
        return json_decode($response->getBody()->getContents(),1);
    }
}
