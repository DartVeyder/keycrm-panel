<?php
require_once ('MySQLDB.php');


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Shuchkin\SimpleXLSX;
class PrestaImportV2
{
    public function generateListProductsXLSX($offers, $filename, $type = 'import')
    {
        $db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

        $rows = [];
        $rows[] = ['Parent ID', 'ID','SKU','PARENT SKU', 'Price',  'Discount Price', 'Quantity', 'Size', 'Color', 'Is active', 'Is added', 'Product name', 'Short description', 'Description', 'Images',  'Main Category', 'Subcategory_1','Image', 'Date'];

        foreach ($offers as $offer){


            $parentSku = $offer['product']['parentSku'];
            $isAdded = $offer['product']['isAddedPrestashop'] ?? 1;
            $isActive = $offer['product']['isActivePrestashop'] ?? 0;

            $sku = $offer['sku'];

//            $data = [
//                'keycrm_offer_id' => $offer['id'],
//                'keycrm_product_id' => $offer['product_id'],
//                'sku' =>$offer['sku'],
//                'parent_sku' => $offer['product']['parentSku'],
//                'name' => $offer['product']['name'],
//                'category' => $offer['product']['category']['full_name'],
//                'price' => $offer['price'],
//                'keycrm_stock' => $offer['stock'],
//                'updated_at'=> date("Y-m-d H:i:s"),
//            ];
//
//            $db->insertOrUpdate("analitic_products_stock", $data , "keycrm_offer_id");


            if($offer['price'] == 0){
                $offer['price'] = $offer['product']['max_price'];
            }



            $price = (double)(isset($offer['product']['fullPrice']))?$offer['product']['fullPrice']: $offer['price'];
            $specialPrice = (double)(isset($offer['product']['specialPrice']))? $offer['product']['specialPrice']: $offer['price'];



            $discountPrice = $price - $specialPrice;
            $discountPrice = ($discountPrice > 0)? $discountPrice: '';

            if($discountPrice >= $price){
                $discountPrice = '';
            }

            if($type == 'import'){
                if( $offer['product_id']  <= 1887){
                    continue;
                }
            }

            if($offer['isPreorderOffer']){
                $offer['stock'] = $offer['preorder_stock'];
            }

            if(empty( $offer['product']['name'])){
                continue;
            }
            if (empty( $offer['size'])) {
                continue;
            }
            if (empty($offer['color'])) {
                continue;
            }

            if ($offer['sku'] == '') {
                continue;
            }



            if (strpos($offer['sku'], '_') !== false) {
                if (strpos($offer['sku'], 'В') === false) {
                    continue;
                }
            }

            if ( strpos($offer['size'], '_') !== false) {
                continue;
            }
            if ( strpos($offer['size'], 'В') !== false) {
                continue;
            }

            if ( strpos($offer['color'], '_') !== false) {
                continue;
            }

//            if ( strpos($offer['size'], ' ') !== false) {
//                if (strpos($offer['size'], 'ONE SIZE') === false) {
//                    continue;
//                }
//            }

            if(!$isAdded){
                continue;
            }

            if(!$parentSku){
                continue;
            }

            if (strpos($offer['sku'], 'В') !== false) {
                $parentSku =  $offer['sku'];
                $offer['sku'] = '';
                $offer['color'] = '';
                $offer['size'] = '';
            }

            if ($parentSku == '') {
                continue;
            }

            $rows[] =  [
                $offer['product_id'],
                $offer['id'],
                $offer['sku'],
                $parentSku,
                $price,
                $discountPrice,
                $offer['stock'],
                $offer['size'],
                mb_strtolower($offer['color']),
                $isActive,
                $isAdded,
                $offer['product']['name'],
                '',
                '',
                '',
                'Twice',
                '',
                '',
                date("Y-m-d H:i:s")
            ]  ;
        }

        $xlsx = Shuchkin\SimpleXLSXGen::fromArray( $rows );
        $xlsx->saveAs($filename);
       // $db->update('marketplaces', ['updated_at' => date("Y-m-d H:i:s"),'updated_analitic' => date("Y-m-d H:i:s")], 'name = ?', ['prestashop']);
        if(PRESTASHOP_RESPONSE){
            echo SimpleXLSX::parse($filename)->toHTML();
        }

        return $rows;
    }

    public function startUpdatePriceStock(){
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
    public function startUpdatePriceStockChangeStatus(){
        try {
            $client = new Client();
            $response = $client->get('https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport', [
                'query' => [
                    'settings' => 9,
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



    public function saveLog($text, $path){
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }
}
