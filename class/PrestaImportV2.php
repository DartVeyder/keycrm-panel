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
        $rows[] = ['Parent ID', 'ID','SKU','PARENT SKU', 'Price',  'Discount Price', 'Quantity', 'Size', 'Color', 'Is active', 'Is added', 'Product name', 'Short description', 'Description', 'Images',  'Main Category', 'Subcategory_1','Image','Default', 'Is Preorder','Date','sku color'];
        $current_parent_sku = '';
        foreach ($offers as $offer){
            $isDefault = '';

            $parentSku = $offer['product']['parentSku'];
            $isAdded = $offer['product']['isAddedPrestashop'] ?? 0;
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

            if(@$offer['isPreorderOffer']){
                $offer['stock'] = 20;
            }

             if ($offer['stock'] > 0 && $current_parent_sku != $parentSku) {
                // новий parentSku – скидаємо
                $current_parent_sku = $parentSku;
                $isDefault = ($offer['stock'] > 0) ? 1 : '';
            } else {
                // наступні варіанти того ж parentSku
                $isDefault = '';
            } 

            if(empty( $offer['product']['name'])){
                continue;
            }

            if (empty( $offer['size'])) {
                $offer['size'] = '';
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
                $skuColor = '';
            }else{
                $skuColor =  $parentSku . abs(crc32($offer['color']));
            }

            if ($parentSku == '') {
                continue;
            }

            
          
           
            $row = [
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
                $isDefault,
                $offer['isPreorderOffer']   ?? '',
                date("Y-m-d H:i:s"),
                $skuColor 
            ]  ;
//              $values = array_map(function($v) {
//     if ($v === null || $v === '') return "''"; // порожні лапки для пустих значень
//     if (is_numeric($v)) return $v;            // числа без лапок
//     return "'" . addslashes($v) . "'";        // екранізація рядків
// }, $row);

// $sql = "INSERT INTO products_log (
//     keycrm_parent_id, keycrm_id, sku, parent_sku, price, discount_price, quantity,
//     size, color, is_active, is_added, product_name,
//     short_description, description, images, main_category,
//     subcategory_1, image, is_default, is_preorder, created_at
// ) VALUES (" . implode(",", $values) . ")"; 
//             $db->query($sql); 
            $rows[] =  $row;
        }

        $xlsx = Shuchkin\SimpleXLSXGen::fromArray( $rows );
        $xlsx->saveAs($filename);
         //$db->update('marketplaces', ['updated_at' => date("Y-m-d H:i:s"),'updated_analitic' => date("Y-m-d H:i:s")], 'name = ?', ['prestashop']);
        
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
