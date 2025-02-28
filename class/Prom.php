<?php

use GuzzleHttp\Client;

class Prom extends Base
{
    private $url = 'https://my.prom.ua/api/v1';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function products(){
        $response = $this->request($this->url . '/products/list','GET', [
            'headers' => [
                'Authorization' => 'Bearer ' . PROM_API_KEY,
                'Content-Language' => 'uk',
            ],
            'query' => [
                'limit' => 100,
            ],
        ]);
        return $response['products'];
    }
}
