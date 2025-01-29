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

    public  function generateData($filterWithIdProduct = null){
        $data = [];
       //  $offers = $this->keyCrm->product('[product_id]=1935');
  //  dd( $offers);
       $offers = $this->keyCrm->products();

        foreach ($offers as $offer){

            $product = $offer['product'];
            if($filterWithIdProduct){
                if( $offer['product_id']  <= $filterWithIdProduct){
                    continue;
                }
            }

            if(empty( $product['name'])){
                continue;
            }

            if ( strpos($offer['sku'], '_') !== false) {
                continue;
            }
            if ( strpos($offer['sku'], 'B24') !== false) {
                continue;
            }

            if(!in_array($offer['product_id'], $this->productIds)){
                $this->productIds[] = $offer['product_id'];
            }

            if( !$data[$offer['product_id']]){
                $data[$offer['product_id']][$offer['id']]['description'] = $product['description'];
                $data[$offer['product_id']][$offer['id']]['images'] = implode(',',$product['attachments_data'] );
            }
            $quantity  = $offer['quantity'] - $offer['in_reserve'];
            $quantity = ($quantity < 0) ? 0 : $quantity;

            $data[$offer['product_id']][$offer['id']]['name'] = $product['name'] ;
            $data[$offer['product_id']][$offer['id']]['sku'] = $offer['sku'];
            $data[$offer['product_id']][$offer['id']]['image'] = $offer['thumbnail_url'];
            $data[$offer['product_id']][$offer['id']]['price'] = $offer['price'];
            $data[$offer['product_id']][$offer['id']]['quantity'] = $quantity;

            //$data[$offer['product_id']][$offer['id']]['size'] =  mb_strtoupper($offer['properties'][1]['value']);
            //$data[$offer['product_id']][$offer['id']]['color'] = $offer['properties'][0]['value'];

            $data[$offer['product_id']][$offer['id']]= array_merge($data[$offer['product_id']][$offer['id']], $this->getProperties($offer['properties'] ));

        }



        return $data;
    }

    private  function getProperties($properties){
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

    private function isValidShortDescription($string) {
        // Використовуємо mb_strlen для підтримки UTF-8 символів
        $length = mb_strlen($string, 'UTF-8');

        // Перевірка на кількість символів більше 800
        if ($length > 819) {
            return false;
        }

        return true;
    }
    public function generateXLS($data, $filename = 'uploads/output.xlsx', $type="full") {
        if(!$data){
            die('None data');
        }

        $listProductsCustomFields = $this->keyCrm->listProductsCustomFields('filter[product_id]=' . implode(',', $this->productIds));

        $rows = [];
        $rows[] = ['Parent ID', 'ID','SKU','PARENT SKU', 'Price',  'Discount Price', 'Quantity', 'Size', 'Color', 'Is active', 'Is added', 'Product name', 'Short description', 'Description', 'Images',  'Main Category', 'Subcategory_1','Image'];

        // Write the data
        foreach ($data as $parentId => $items) {


            foreach ($items as $id => $item) {
                $shortDescription = $listProductsCustomFields[$parentId]['shortDescription'];

                $parentSku =  $listProductsCustomFields[$parentId]['parentSku'];
                $fullPrice =  $listProductsCustomFields[$parentId]['fullPrice'];
                $specialPrice =  $listProductsCustomFields[$parentId]['specialPrice'];
                $isAdded =  $listProductsCustomFields[$parentId]['isAdded'] ?? 1;
                $isActive =  $listProductsCustomFields[$parentId]['isActive'] ?? 0;

                $price = (double)(isset($fullPrice))? $fullPrice: $item['price'];

                $specialPrice = (double)(isset($specialPrice))? $specialPrice: $item['price'];

                $discountPrice = $price - $specialPrice;
                $discountPrice = ($discountPrice > 0)? $discountPrice: '';

                if(!$this->isValidShortDescription($shortDescription)){
                    $shortDescription = 'Довжина властивості Product->description_short наразі '.mb_strlen($shortDescription, 'UTF-8').' символів. А повинно бути між 0 та 819 символами.';
                }

//                if (preg_match('/[\p{Cyrillic}]/u', $item['size'])) {
//                    continue;
//                }

                if ( strpos($item['size'], '_') !== false) {
                    continue;
                }
                if ( strpos($item['size'], ' ') !== false) {
                    continue;
                }

                if(!$isAdded){
                    continue;
                }

                if(!$parentSku){
                    continue;
                }

                $rows[] =  [
                    $parentId,
                    $id,
                    $item['sku'],
                    $parentSku,
                    $price,
                    $discountPrice,
                    $item['quantity'],
                    $item['size'],
                    mb_strtolower($item['color']),
                    $isActive,
                    $isAdded,
                    $item['name'],
                    '',
                    '',
                    '',
                    'Twice',
                    '',
                    '',
                ]  ;
            }
        }

        $xlsx = Shuchkin\SimpleXLSXGen::fromArray( $rows );
        $xlsx->saveAs($filename);

        echo SimpleXLSX::parse($filename)->toHTML();
    }

    public function startUpdatePrice(){
        try {
            $client = new Client();
            $response = $client->get('https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport', [
                'query' => [
                    'settings' => 7,
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
