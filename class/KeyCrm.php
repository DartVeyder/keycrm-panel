<?php

use GuzzleHttp\Client;

class KeyCrm
{
    public  function products(){
        $offers =   $this->request('/offers?limit=100000000');
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
