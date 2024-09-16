<?php

use GuzzleHttp\Client;

class KeyCrm
{
    public  function products(){
        $offers =   $this->request('/offers?limit=100000000&include=product');
//        $products =   $this->request('/products?limit=100000000&include=custom_fields&filter[product_id]=1891');
//        dd( $products);
        return $offers['data'];
    }

    public function listProductsShortDescription($filter = ''){
        $data = [];
        $products =  $this->request('/products?limit=100000000&include=custom_fields&'.$filter);
        $products = array_column( $products['data'], 'custom_fields', 'id');
        foreach ($products as $id => $product){
            foreach ($product as  $customField){
                if(isset($customField['name'] ) && $customField['name'] == 'сео опис товару'){
                    $data[$id] = $customField['value'];
                }
            }
        }

        return  $data ;
    }


    public  function  product($filter){
        $offers =   $this->request('/offers?limit=100000000&include=product&filter'.$filter);
        return $offers['data'];
    }

    private function request($endpoint){
        $client = new Client();
        $response = $client ->request('GET',  KEYCRM_API_URL . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' .KEYCRM_API_TOKEN,
                'Accept' => 'application/json',
            ],
        ]);

        // Get the response body as a string
        return json_decode($response->getBody()->getContents(),1);
    }
}
