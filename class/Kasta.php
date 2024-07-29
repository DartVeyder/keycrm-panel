<?php

use GuzzleHttp\Client;
use Shuchkin\SimpleXLSX;

class Kasta
{
    public  function products()
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

    public function listBarcodes()
    {
        $products =  $this->products();
        $barcodes =  array_column($products, "barcode" );

        return array_filter(array_map(function($item) {
            return isset($item[0]) ? $item[0] : null;
        }, $barcodes));

    }
    public function formatDataStock($products, $inBarcodes) {

        $data = array_filter(array_map(function($product) use ($inBarcodes) {
            if (in_array($product['sku'], $inBarcodes)) {
                return [
                    'barcode' => $product['sku'],
                    'stock' => $product['quantity']
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
        $discounts = $this->readDiscount();
        $data = array_filter(array_map(function($product) use ($inBarcodes,$discounts) {
            if (in_array($product['sku'], $inBarcodes)) {
                return [
                    'barcode' => $product['sku'],
                    'old_price' => $product['price'],
                    'new_price' => $this->calculateDiscountPrice($product['price'], @$discounts[$product['sku']]) ,
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
