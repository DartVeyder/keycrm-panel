<?php

use GuzzleHttp\Client;

class KeyCrm
{
    public  function products(){
        $offers =   $this->request('/offers?limit=100000000&include=product&filter[is_archived]=false');
//        $products =   $this->request('/products?limit=100000000&include=custom_fields&filter[product_id]=1891');
//        dd( $products);
        return $offers['data'];
    }

    public function listProductsCustomFields($filter = ''){
        $fields = ["CT_1007" => 'shortDescription',
            "CT_1009" => "parentSku" ,
            "CT_1011" => "isAdded",
            "CT_1012" => "isActive",
            "CT_1015" => "isAddedIntertop",
            "CT_1014" => "fullPrice",
            "CT_1010" => "specialPrice",
        ];
        $activeField = ['Так' => 1, 'Ні' => 0];
        $data = [];
        $products =  $this->request('/products?limit=100000000&include=custom_fields&'.$filter);
        $products = array_column( $products['data'], 'custom_fields', 'id');


        foreach ($products as $id => $product){
            foreach ($product as  $customField){
                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1007'){
                    $data[$id][$fields[$customField['uuid']] ] = $customField['value'];
                }

                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1009'){
                    $data[$id][$fields[$customField['uuid']]] = $customField['value'];
                }

                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1011'){
                    $data[$id][$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
                }

                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1012'){
                    $data[$id][$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
                }

                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1015'){
                    $data[$id][$fields[$customField['uuid']]] = $activeField[$customField['value'][0]];
                }

                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1014'){
                    $data[$id][$fields[$customField['uuid']]] =  $customField['value'];
                }

                if(isset($customField['name'] ) && $customField['uuid'] == 'CT_1010') {
                    $data[$id][$fields[$customField['uuid']]] = $customField['value'];
                }
            }
        }
        return  $data ;
    }


    public  function  product($filter){
        $offers =   $this->request('/offers?limit=100000000&include=product&filter'.$filter);
        return $offers['data'];
    }
    public function webhookOrder(){
        $json = file_get_contents('php://input');
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // fetch RAW input
            $json = file_get_contents('php://input');
            // decode json
            $object = json_decode($json, 1);
            // expecting valid json
            if (json_last_error() !== JSON_ERROR_NONE) {
                die(header('HTTP/1.0 415 Unsupported Media Type'));
            }
            file_put_contents('test_webhook.txt', print_r($object, true),FILE_APPEND);
            return $object['context'];
        }

        return null;
    }

    private function request($endpoint, $method = "GET"){
        $client = new Client();
        $response = $client ->request($method,  KEYCRM_API_URL . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' .KEYCRM_API_TOKEN,
                'Accept' => 'application/json',
            ],
        ]);

        // Get the response body as a string
        return json_decode($response->getBody()->getContents(),1);
    }

    public function addTagOrder($orderId, $tagId){
       return $this->request("/order/$orderId/tag/$tagId",'POST');
    }
}
