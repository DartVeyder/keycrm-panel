<?php

use GuzzleHttp\Client;

class KeyCrm
{
    public  function products(){
//        $offers =   $this->request('/offers?limit=1000&include=product&filter[is_archived]=false');
//        $products =   $this->request('/products?limit=100000000&include=custom_fields&filter[product_id]=1891');
//        dd( $products);
        $page = 1;
        $limit = 50;
        $allData = [];

        do {
            $url = "/offers?limit={$limit}&include=product&filter[is_archived]=false&page={$page}";

            $response = $this->request($url); // Assuming this method sends the request and returns the response

            // If the response contains data, append it to the allData array
            if (isset($response['data'])) {
                $allData = array_merge($allData, $response['data']);
            }

            // Get the next page URL from the response
            $nextPageUrl = $response['next_page_url'] ?? null;

            // Increment the page number
            $page++;
            sleep(1);
        } while ($nextPageUrl);


        return $allData;
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
        $page = 1;
        $limit = 50;
        $allData = [];

        do {
            $url = "/products?limit=$limit&include=custom_fields&'.$filter&page={$page}";

            $response = $this->request($url); // Assuming this method sends the request and returns the response

            // If the response contains data, append it to the allData array
            if (isset($response['data'])) {
                $allData = array_merge($allData, $response['data']);
            }

            // Get the next page URL from the response
            $nextPageUrl = $response['next_page_url'] ?? null;

            // Increment the page number
            $page++;
            sleep(1);
        } while ($nextPageUrl);


        //$products =  $this->request('/products?limit=100000000&include=custom_fields&'.$filter);
        $products = array_column( $allData, 'custom_fields', 'id');

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

    private function request($endpoint, $method = "GET", $body = []){
        $client = new Client();
        $response = $client ->request($method,  KEYCRM_API_URL . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' .KEYCRM_API_TOKEN,
                'Accept' => 'application/json',
            ],
            'json' => $body
        ]);

        // Get the response body as a string
        return json_decode($response->getBody()->getContents(),1);
    }

    public function addTagOrder($orderId, $tagId){
       return $this->request("/order/$orderId/tag/$tagId",'POST');
    }


    public function updateClient($clientId,$data){
        return $this->request("/buyer/$clientId",'PUT',$data);
    }
}
