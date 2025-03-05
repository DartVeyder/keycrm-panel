<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Shuchkin\SimpleXLSX;
class PrestaImportV2
{
    public function generateListProductsXLSX($offers, $filename, $type = 'import')
    {
        $rows = [];
        $rows[] = ['Parent ID', 'ID','SKU','PARENT SKU', 'Price',  'Discount Price', 'Quantity', 'Size', 'Color', 'Is active', 'Is added', 'Product name', 'Short description', 'Description', 'Images',  'Main Category', 'Subcategory_1','Image'];

        foreach ($offers as $offer){


            $parentSku = $offer['product']['parentSku'];
            $fullPrice = $offer['product']['fullPrice'];
            $specialPrice = $offer['product']['specialPrice'];
            $isAdded = $offer['product']['isAddedPrestashop'] ?? 1;
            $isActive = $offer['product']['isActivePrestashop'] ?? 0;

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

            if (strpos($offer['sku'], '_') !== false) {
                if (strpos($offer['sku'], 'В') === false) {
                    continue;
                }
            }

            if (strpos($offer['sku'], 'В') !== false) {
                $parentSku =  $offer['sku'];
                $offer['sku'] = '';
                $offer['color'] = '';
                $offer['size'] = '';
            }

            if ( strpos($offer['size'], '_') !== false) {
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

            $price = (double)(isset($fullPrice))? $fullPrice: $offer['price'];

            $specialPrice = (double)(isset($specialPrice))? $specialPrice: $offer['price'];

            $discountPrice = $price - $specialPrice;
            $discountPrice = ($discountPrice > 0)? $discountPrice: '';
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
            ]  ;
        }
        $xlsx = Shuchkin\SimpleXLSXGen::fromArray( $rows );
        $xlsx->saveAs($filename);

        if(PRESTASHOP_RESPONSE){
            echo SimpleXLSX::parse($filename)->toHTML();
        }

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
