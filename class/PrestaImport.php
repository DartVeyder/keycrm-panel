<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Shuchkin\SimpleXLSX;

require_once ('class/KeyCrm.php');
class PrestaImport
{
    private $keyCrm;
    private $productIds = [];
    public function __construct()
    {
        $this->keyCrm = new KeyCrm();
    }

    public  function generateData(){
        $data = [];
      //   $offers = $this->keyCrm->product('[product_id]=1891');
   // dd( $offers);
       $offers = $this->keyCrm->products();

        foreach ($offers as $offer){
            $product = $offer['product'];
            if( $offer['product_id']  <= 1887 ){
                continue;
            }

            if(empty( $product['name'])){
                continue;
            }

            if ( strpos($offer['sku'], '_') !== false) {
                continue;
            }

            if(!in_array($offer['product_id'], $this->productIds)){
                $this->productIds[] = $offer['product_id'];
            }

            if( !$data[$offer['product_id']]){
                $data[$offer['product_id']][$offer['id']]['description'] = $product['description'];
                $data[$offer['product_id']][$offer['id']]['images'] = implode(',',$product['attachments_data'] );
            }
            $data[$offer['product_id']][$offer['id']]['name'] = $product['name'] ;
            $data[$offer['product_id']][$offer['id']]['sku'] = $offer['sku'];
            $data[$offer['product_id']][$offer['id']]['image'] = $offer['thumbnail_url'];
            $data[$offer['product_id']][$offer['id']]['price'] = $offer['price'];
            $data[$offer['product_id']][$offer['id']]['quantity'] = $offer['quantity'];

            //$data[$offer['product_id']][$offer['id']]['size'] =  mb_strtoupper($offer['properties'][1]['value']);
            //$data[$offer['product_id']][$offer['id']]['color'] = $offer['properties'][0]['value'];

            $data[$offer['product_id']][$offer['id']]= array_merge($data[$offer['product_id']][$offer['id']], $this->getProperties($offer['properties'] ));

        }



        return $data;
    }

    private  function getProperties($properties){
        $data = [];
        foreach ($properties as $property){
            if( $property['name'] == 'Колір'){
                $data['size'] = $property['value'];
            }

            if( $property['name'] == 'Розмір'){
                $data['color'] = mb_strtoupper($property['value']);
            }
        }

        return $data;
    }

    public function findCommonPartInSKU($skus) {
        if (empty($skus)) {
            return '';
        }

        // Знайти найкоротший SKU
        $shortestSku = min(array_map('strlen', $skus));
        $referenceSku = current(array_filter($skus, function($sku) use ($shortestSku) {
            return strlen($sku) == $shortestSku;
        }));

        // Перевірити всі можливі підрядки
        for ($len = strlen($referenceSku); $len > 0; $len--) {
            for ($start = 0; $start <= strlen($referenceSku) - $len; $start++) {
                $substring = substr($referenceSku, $start, $len);
                $isCommon = true;

                foreach ($skus as $sku) {
                    if (strpos($sku, $substring) === false) {
                        $isCommon = false;
                        break;
                    }
                }

                if ($isCommon) {
                    return $substring;
                }
            }
        }

        return '';
    }
    private function findCommonPrefix($strings) {
        if (empty($strings)) {
            return '';
        }

        $prefix = $strings[0];

        foreach ($strings as $string) {
            while (strpos($string, $prefix) !== 0) {
                $prefix = substr($prefix, 0, -1);
                if ($prefix === '') {
                    return '';
                }
            }
        }

        return $prefix;
    }
    public function generateXLS($data, $filename = 'uploads/output.xlsx') {
        if(!$data){
            die('None data');
        }
        $listProductsShortDescription = $this->keyCrm->listProductsShortDescription('filter[product_id]=' . implode(',', $this->productIds));

        $rows = [];
        $rows[] = ['Parent ID', 'ID', 'Description', 'Short description', 'Images', 'Product name', 'SKU','PARENT SKU', 'Price', 'Quantity', 'Size', 'Color', 'Main Category', 'Subcategory_1','Image'];

        // Write the data
        foreach ($data as $parentId => $items) {
            $parentSku =  $this->findCommonPartInSKU(array_column($items, 'sku'));

            foreach ($items as $id => $item) {
                $rows[] =  [
                    $parentId,
                    $id,
                    isset($listProductsShortDescription[$parentId]) ? trim($listProductsShortDescription[$parentId]) : '',
                    isset($item['description']) ? trim($item['description']) : '',
                    isset($item['images']) ? $item['images'] : '',
                    $item['name'],
                    $item['sku'],
                    $parentSku,
                    $item['price'],
                    $item['quantity'],
                    $item['size'],
                    $item['color'],
                    'Twice',
                    '',
                    $item['image'],
                ]  ;
            }
        }

        $xlsx = Shuchkin\SimpleXLSXGen::fromArray( $rows );
        $xlsx->saveAs($filename);

        echo SimpleXLSX::parse($filename)->toHTML();
    }

    public function startImport(){
        try {
            $client = new Client();
            $response = $client->get('https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport', [
                'query' => [
                    'settings' => 6,
                    'id_shop_group' => 1,
                    'id_shop' => 1,
                    'secure_key' => '30aa0bdb68fa671e64a2ba3a4016aec0',
                    'action' => 'importProducts',
                ]
            ]);

            // Виводимо статус-код відповіді
            echo 'Status Code: ' . $response->getStatusCode() . "\n";

            // Виводимо тіло відповіді
            echo 'Response Body: ' . $response->getBody();
            return  $response->getBody();
        } catch (RequestException $e) {
            // Обробляємо можливі помилки запиту
            echo 'Request failed: ' . $e->getMessage();
            return $e->getMessage();
        }
    }


    public function generateCSV($data, $filename = 'output.csv') {
        // Open the file for writing
        $fp = fopen($filename, 'w');

        // Add BOM for UTF-8
        fwrite($fp, "\xEF\xBB\xBF");

        // Write the header
        fputcsv($fp, ['Parent ID', 'ID', 'Description', 'Images', 'Product name', 'SKU','PARENT SKU', 'Price', 'Quantity', 'Size', 'Color'], ';');

        // Write the data
        foreach ($data as $parentId => $items) {
            $parentSku =  $this->findCommonPartInSKU(array_column($items, 'sku'));
            foreach ($items as $id => $item) {
                fputcsv($fp, [
                    $parentId,
                    $id,
                    isset($item['description']) ? trim($item['description']) : '',
                    isset($item['images']) ? $item['images'] : '',
                    $item['name'],
                    $item['sku'],
                    $parentSku,
                    $item['price'],
                    $item['quantity'],
                    $item['size'],
                    $item['color']
                ], ';');
            }
        }

        // Close the file
        fclose($fp);

        echo "CSV file '$filename' has been created successfully!";
    }

    public function saveLog($text, $path){
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }
}
