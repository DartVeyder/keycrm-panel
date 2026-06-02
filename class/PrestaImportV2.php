<?php
require_once('MySQLDB.php');


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Shuchkin\SimpleXLSX;
class PrestaImportV2
{
    public function generateListProductsXLSX($offers, $filename, $type = 'import')
    {
        $db = new MySQLDB(HOST, DBNAME, USERNAME, PASSWORD);

        $rows               = [];
        $rows[]             = ['Parent ID', 'ID', 'SKU', 'PARENT SKU', 'Price', 'Discount Price', 'Quantity', 'Size', 'Color', 'Is active', 'Is added', 'Product name', 'Short description', 'Description', 'Images', 'Main Category', 'Subcategory_1', 'Image', 'Default', 'Is Preorder', 'Date', 'sku color'];
        $current_parent_sku = '';
        foreach ($offers as $offer) {
            $isDefault    = '';
            $categoryName = '';
            $parentSku    = $offer['product']['parentSku'];
            $isAdded      = $offer['product']['isAddedPrestashop'] ?? 1;
            $isActive     = $offer['product']['isActivePrestashop'] ?? 0;

            $sku = $offer['sku'];
            if (!$parentSku) {
                continue;
            }

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


            if ($offer['price'] == 0) {
                $offer['price'] = $offer['product']['max_price'];
            }



            $price        = (double) (isset($offer['product']['fullPrice'])) ? $offer['product']['fullPrice'] : $offer['price'];
            $specialPrice = (double) (isset($offer['product']['specialPrice'])) ? $offer['product']['specialPrice'] : $offer['price'];



            $discountPrice = $price - $specialPrice;
            $discountPrice = ($discountPrice > 0) ? $discountPrice : '';

            if ($discountPrice >= $price) {
                $discountPrice = '';
            }

            if ($type == 'import') {
                if ($offer['product_id'] <= 1887) {
                    continue;
                }
            }

            if (@$offer['isPreorderOffer']) {
                $offer['stock'] = 20;
            }

            if ($offer['stock'] > 0 && $current_parent_sku != $parentSku) {
                // новий parentSku – скидаємо
                $current_parent_sku = $parentSku;
                $isDefault          = ($offer['stock'] > 0) ? 1 : '';
            } else {
                // наступні варіанти того ж parentSku
                $isDefault = '';
            }

            if (empty($offer['product']['name'])) {
                continue;
            }

            if (empty($offer['size'])) {
                $offer['size'] = '';
            } else {
                $offer['size'] = trim(str_replace(['_8888', '8888_', ' 8888', '8888 ', '8888', '_88', '88_', ' 88', '88 ', '88'], '', $offer['size']));
            }
            if (empty($offer['color'])) {
                continue;
            } else {
                $offer['color'] = trim(str_replace(['_8888', '8888_', ' 8888', '8888 ', '8888', '_88', '88_', ' 88', '88 ', '88'], '', $offer['color']));
            }

            if ($offer['sku'] == '') {
                continue;
            }

            $is8888 = false;
            $prefix = '';

            if (strpos($offer['sku'], '8888_') !== false) {
                $prefix = '8888_';
                $is8888 = true;
            } elseif (strpos($offer['sku'], '8888') === 0) {
                $prefix = '8888';
                $is8888 = true;
            } elseif (strpos($offer['sku'], '88_') !== false) {
                $prefix = '88_';
                $is8888 = true;
            } elseif (strpos($offer['sku'], '88') === 0) {
                $prefix = '88';
                $is8888 = true;
            } elseif (strpos($offer['sku'], 'В_') !== false) {
                $prefix = 'В_';
            } elseif (strpos($offer['sku'], 'В') !== false) {
                $prefix = 'В';
            }

            if ($prefix !== '' && strpos($parentSku, $prefix) !== 0) {
                $parentSku = $prefix . $parentSku;
            }

            if (strpos($offer['sku'], '_') !== false) {
                if (strpos($offer['sku'], 'В') === false && !$is8888) {
                    continue;
                }
            }

            if (strpos($offer['size'], '_') !== false) {
                continue;
            }
            if (strpos($offer['size'], 'В') !== false) {
                continue;
            }
            if (mb_strpos($offer['size'], 'БРАК') !== false || mb_strpos($offer['size'], 'ФОТО') !== false) {
                continue;
            }
            if (preg_match('/^[FX]\d+$/i', trim($offer['size'])) || preg_match('/^\d{6,}$/', trim($offer['size']))) {
                continue;
            }
            // Видаляємо слова "ЗРАЗОК", "ВЗІРЕЦЬ", "ЧОРНИЙ", "СЕРТИФІКАТ" та цифри біля них (напр. "№1", "3")
            $offer['size'] = preg_replace('/(?:ЗРАЗОК|ВЗІРЕЦЬ|ВЗІРЕЦІЬ|ЧОРНИЙ|СЕРТИФІКАТ)\s*№?\s*\d*/iu', '', $offer['size']);
            // Видаляємо просто "№" з цифрою, якщо десь залишилось
            $offer['size'] = preg_replace('/№\s*\d+/iu', '', $offer['size']);
            
            // Замінюємо кириличні букви розмірів на латиницю (це вирішить проблему змішаних "ХL", "М/L" тощо)
            $offer['size'] = str_replace(
                ['Х', 'х', 'С', 'с', 'М', 'м', 'Л', 'л'],
                ['X', 'X', 'S', 'S', 'M', 'M', 'L', 'L'],
                $offer['size']
            );

            // Виправляємо латинську "C", яку часто вводять замість "С"
            $offer['size'] = str_ireplace('C/M', 'S/M', $offer['size']);
            if (trim(strtoupper($offer['size'])) === 'C') $offer['size'] = 'S';
            if (trim(strtoupper($offer['size'])) === 'CM') $offer['size'] = 'S/M';
            if (trim(strtoupper($offer['size'])) === 'SM') $offer['size'] = 'S/M';
            if (trim(strtoupper($offer['size'])) === 'ML') $offer['size'] = 'M/L';
            if (trim(strtoupper($offer['size'])) === 'XSS') $offer['size'] = 'XS/S';
            
            // Видаляємо всі інші кириличні символи та знак №, якщо залишився
            $offer['size'] = preg_replace('/[А-Яа-яЁёІіЇїЄєҐґ№]+/u', '', $offer['size']);
            
            // Замінюємо дефіси та дужки
            $offer['size'] = str_replace(['-', '(', ')'], ['/', '', ''], $offer['size']);
            $offer['size'] = trim(preg_replace('/\s+/', ' ', $offer['size']));

            if (strpos($offer['color'], '_') !== false) {
                continue;
            }

            //            if ( strpos($offer['size'], ' ') !== false) {
//                if (strpos($offer['size'], 'ONE SIZE') === false) {
//                    continue;
//                }
//            }

            if (!$isAdded) {
                continue;
            }


            $isB = (strpos($offer['sku'], 'В') !== false);

            if ($isB) {
                $isActive     = 0;
                $categoryName = 'LAST CHANCE';
            } elseif ($is8888) {
                $categoryName = 'OUTLET %';
            }

            if ($isB || $is8888) {
                $skuColor = '';
            } else {
                $skuColor = $parentSku . abs(crc32($offer['color']));
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
                $categoryName,
                '',
                '',
                $isDefault,
                $offer['isPreorderOffer'] ?? '',
                date("Y-m-d H:i:s"),
                $skuColor
            ];
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
            $rows[] = $row;
        }

        $xlsx = Shuchkin\SimpleXLSXGen::fromArray($rows);
        $xlsx->saveAs($filename);
        //$db->update('marketplaces', ['updated_at' => date("Y-m-d H:i:s"),'updated_analitic' => date("Y-m-d H:i:s")], 'name = ?', ['prestashop']);

        if (PRESTASHOP_RESPONSE) {
            echo SimpleXLSX::parse($filename)->toHTML();
        }

        return $rows;
    }

    public function startUpdatePriceStock()
    {
        try {
            $client   = new Client();
            $response = $client->get('https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport', [
                'query' => [
                    'settings'      => 7,
                    'id_shop_group' => 1,
                    'id_shop'       => 1,
                    'secure_key'    => '30aa0bdb68fa671e64a2ba3a4016aec0',
                    'action'        => 'importProducts',
                ]
            ]);

            // Виводимо статус-код відповіді
            echo 'Status Code: ' . $response->getStatusCode() . "\n";

            // Виводимо тіло відповіді
            echo 'Response Body: ' . $response->getBody();
            return $response->getBody();
        } catch (RequestException $e) {
            // Обробляємо можливі помилки запиту
            echo 'Request failed: ' . $e->getMessage();
            return $e->getMessage();
        }
    }
    public function startUpdatePriceStockChangeStatus()
    {
        try {
            $client   = new Client();
            $response = $client->get('https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport', [
                'query' => [
                    'settings'      => 9,
                    'id_shop_group' => 1,
                    'id_shop'       => 1,
                    'secure_key'    => '30aa0bdb68fa671e64a2ba3a4016aec0',
                    'action'        => 'importProducts',
                ]
            ]);

            // Виводимо статус-код відповіді
            echo 'Status Code: ' . $response->getStatusCode() . "\n";

            // Виводимо тіло відповіді
            echo 'Response Body: ' . $response->getBody();
            return $response->getBody();
        } catch (RequestException $e) {
            // Обробляємо можливі помилки запиту
            echo 'Request failed: ' . $e->getMessage();
            return $e->getMessage();
        }
    }

    public function startImport()
    {
        try {
            $client   = new Client();
            $response = $client->get('https://twice.com.ua/module/simpleimportproduct/ScheduledProductsImport', [
                'query' => [
                    'settings'      => 6,
                    'id_shop_group' => 1,
                    'id_shop'       => 1,
                    'secure_key'    => '30aa0bdb68fa671e64a2ba3a4016aec0',
                    'action'        => 'importProducts',
                ]
            ]);

            // Виводимо статус-код відповіді
            echo 'Status Code: ' . $response->getStatusCode() . "\n";

            // Виводимо тіло відповіді
            echo 'Response Body: ' . $response->getBody();
            return $response->getBody();
        } catch (RequestException $e) {
            // Обробляємо можливі помилки запиту
            echo 'Request failed: ' . $e->getMessage();
            return $e->getMessage();
        }
    }



    public function saveLog($text, $path)
    {
        $file = fopen($path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }
}
