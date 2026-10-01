<?php

use GuzzleHttp\Client;

class Prestashop extends Base
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => PRESTASHOP_API_URL,
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Отримати продукт за reference
     *
     * @param string $reference Унікальний код товару
     * @return array|null Дані про товар або null, якщо запит не успішний
     */
    public function getProductByReference(string $reference): ?array
    {
        try {
            $response = $this->client->request('GET', 'products', [
                'query' => [
                    'ws_key'            => PRESTASHOP_API_KEY,
                    'display'           => 'full',
                    'filter[reference]' => $reference,
                    'output_format'     => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }

        return null;
    }

    public function getOrder($idOrder)
    {
        try {
            $response = $this->client->request('GET', "orders/$idOrder", [
                'query' => [
                    'ws_key'        => PRESTASHOP_API_KEY,
                    'display'       => 'full',
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $order = json_decode($response->getBody(), true);
                if (!$order) {
                    return null;
                }
                return $order['orders'][0];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }

        return null;
    }
    public function changeOrderStatus($idOrder, $idOrderState)
    {
        $xmlData = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">
    <order_history>
        <id_order>$idOrder</id_order>
        <id_order_state>$idOrderState</id_order_state>
        <id_employee>1</id_employee>  
    </order_history>
</prestashop>";

        $response = $this->client->request('POST', 'order_histories', [
            'query'   => [
                'ws_key' => PRESTASHOP_API_KEY
            ],
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
            'body'    => $xmlData
        ]);
    }

    public function getProductImagesByReference(string $reference): ?array
    {
        try {
            $response = $this->client->request('GET', 'combinations', [
                'query' => [
                    'ws_key'            => PRESTASHOP_API_KEY,
                    'display'           => 'full',
                    'filter[reference]' => $reference,
                    'output_format'     => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
        }

        return null;
    }

    public function getStockAvailables()
    {
        try {
            $response = $this->client->request('GET', 'stock_availables', [
                'query' => [
                    'ws_key'        => PRESTASHOP_API_KEY,
                    'display'       => '[id, id_product, id_product_attribute, quantity]',
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['stock_availables'];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function getProducts($display = 'full')
    {
        try {
            $response = $this->client->request('GET', 'products', [
                'query' => [
                    'ws_key'        => PRESTASHOP_API_KEY,
                    'display'       => $display,
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['products'];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function getApiProducts($reference = null)
    {
        try {
            $url = PRESTASHOP_SITE_URL . '/admin298rbunic/keycrm/api-product.php';

            // Якщо передано $reference — додаємо його як параметр до URL
            if ($reference !== null) {
                $url .= '?reference=' . urlencode($reference);
            }

            $response = $this->request($url, 'GET');

            return $response['data']['products'] ?? null;
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }


    public function getCombinations($display = 'full')
    {
        try {
            $response = $this->client->request('GET', 'combinations', [
                'query' => [
                    'ws_key'        => PRESTASHOP_API_KEY,
                    'display'       => $display,
                    'output_format' => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true)['combinations'];
            }
        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function getPreorderProducts()
    {
        try {
            $response = file_get_contents(PRESTASHOP_SITE_URL . '/admin298rbunic/keycrm/api-preorder.php');
            return json_decode($response, true);

        } catch (\Exception $e) {
            // Логування або обробка помилки
            error_log($e->getMessage());
            return null;
        }
    }

    public function addTrackingNumber($id_order, $tracking_number, $id_carrier = 22, $kc_order_id = null)
    {
        // Захист даних (Sanitization)
        $safe_tracking_number = pSQL($tracking_number);
        $safe_id_order        = (int) $id_order;

        // Формуємо базовий SQL
        $sql = "UPDATE `" . _DB_PREFIX_ . "order_carrier` 
            SET `tracking_number` = '$safe_tracking_number' 
            WHERE `id_order` = $safe_id_order";

        // Якщо передано ID перевізника, додаємо умову
        if ($id_carrier) {
            $safe_id_carrier  = (int) $id_carrier;
            $sql             .= " AND `id_carrier` = $safe_id_carrier";
        }
        Logger::addLog(
            "Трек-номер {$tracking_number} додано до замовлення {$id_order} (KC ID: {$kc_order_id})",
            1,
            null,
            'Order',
            $id_order,
            true
        );
        // Виконуємо запит
        return Db::getInstance()->execute($sql);
    }

    public function hasOrderMessage($id_order, $search_text)
    {
        try {
            $response = $this->client->request('GET', 'messages', [
                'query' => [
                    'ws_key'           => PRESTASHOP_API_KEY,
                    'filter[id_order]' => (int) $id_order,
                    'display'          => 'full',
                    'output_format'    => 'JSON',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                if (isset($data['messages']) && is_array($data['messages'])) {
                    foreach ($data['messages'] as $msg) {
                        if (mb_strpos($msg['message'], $search_text) !== false) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        return false;
    }

    public function addOrderMessage($id_order, $messageText)
    {
        $id_order = (int) $id_order;
        $xmlData  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">
    <message>
        <id_order>$id_order</id_order>
        <message><![CDATA[$messageText]]></message>
        <private>0</private>
    </message>
</prestashop>";

        try {
            $response = $this->client->request('POST', 'messages', [
                'query'   => [
                    'ws_key' => PRESTASHOP_API_KEY
                ],
                'headers' => [
                    'Content-Type' => 'text/xml',
                ],
                'body'    => $xmlData
            ]);

            $success = ($response->getStatusCode() === 201 || $response->getStatusCode() === 200);

            if ($success) {
                try {
                    $order = new Order($id_order);
                    if (Validate::isLoadedObject($order)) {
                        $customer = new Customer((int) $order->id_customer);
                        if (Validate::isLoadedObject($customer)) {
                            $varsTpl = [
                                '{lastname}'   => $customer->lastname,
                                '{firstname}'  => $customer->firstname,
                                '{id_order}'   => $order->id,
                                '{order_name}' => $order->getUniqReference(),
                                '{message}'    => $messageText,
                            ];

                            $subject = 'Повідомлення щодо вашого замовлення';

                            Mail::Send(
                                (int) $order->id_lang,
                                'order_merchant_comment',
                                $subject,
                                $varsTpl,
                                $customer->email,
                                $customer->firstname . ' ' . $customer->lastname,
                                null,
                                null,
                                null,
                                null,
                                _PS_MAIL_DIR_,
                                true,
                                (int) $order->id_shop
                            );
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Email sending error: " . $e->getMessage());
                }
            }

            return $success;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function uploadProductImage($id_product, $filePath)
    {
        try {
            $response = $this->client->request('POST', "images/products/$id_product", [
                'query'     => [
                    'ws_key' => PRESTASHOP_API_KEY
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath)
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
                $xml = simplexml_load_string($response->getBody()->getContents());
                if (isset($xml->image->id)) {
                    return (int) $xml->image->id;
                }
            }
        } catch (\Exception $e) {
            error_log("Помилка завантаження фото: " . $e->getMessage());
        }
        return false;
    }

    public function deleteProductImage($id_product, $id_image)
    {
        try {
            $response = $this->client->request('DELETE', "images/products/$id_product/$id_image", [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY
                ]
            ]);

            return ($response->getStatusCode() === 200 || $response->getStatusCode() === 204);
        } catch (\Exception $e) {
            error_log("Помилка видалення фото ($id_image): " . $e->getMessage());
        }
        return false;
    }

    public function linkImageToCombination($id_combination, $id_image)
    {
        try {
            // Отримуємо поточну комбінацію
            $response = $this->client->request('GET', "combinations/$id_combination", [
                'query' => [
                    'ws_key' => PRESTASHOP_API_KEY
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $xml = simplexml_load_string($response->getBody()->getContents());

                // Видаляємо не потрібні елементи, що можуть викликати помилку при PUT
                if (isset($xml->combination->manufacturer_name)) {
                    unset($xml->combination->manufacturer_name);
                }
                if (isset($xml->combination->quantity)) {
                    unset($xml->combination->quantity);
                }

                // Додаємо зв'язок з фото
                if (!isset($xml->combination->associations)) {
                    $xml->combination->addChild('associations');
                }
                if (!isset($xml->combination->associations->images)) {
                    $xml->combination->associations->addChild('images');
                }

                // Перевіряємо чи вже є таке фото, щоб не дублювати
                $alreadyHas = false;
                if ($xml->combination->associations->images->image) {
                    foreach ($xml->combination->associations->images->image as $img) {
                        if ((int) $img->id === (int) $id_image) {
                            $alreadyHas = true;
                            break;
                        }
                    }
                }

                if (!$alreadyHas) {
                    $imageNode = $xml->combination->associations->images->addChild('image');
                    $imageNode->addChild('id', $id_image);

                    // Збираємо новий XML з кореневим вузлом <prestashop>
                    $putXml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
                    $putXml .= "<prestashop xmlns:xlink=\"http://www.w3.org/1999/xlink\">\n";
                    $putXml .= $xml->combination->asXML();
                    $putXml .= "</prestashop>";

                    $putResponse = $this->client->request('PUT', "combinations/$id_combination", [
                        'query'   => [
                            'ws_key' => PRESTASHOP_API_KEY
                        ],
                        'headers' => [
                            'Content-Type' => 'text/xml',
                        ],
                        'body'    => $putXml
                    ]);

                    return ($putResponse->getStatusCode() === 200);
                }
                return true; // Вже прив'язано
            }
        } catch (\Exception $e) {
            error_log("Помилка прив'язки фото до комбінації $id_combination: " . $e->getMessage());
        }
        return false;
    }
}
