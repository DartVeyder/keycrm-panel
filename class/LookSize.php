<?php
use GuzzleHttp\Client;

class LookSize extends Base
{
    private $url = 'https://www.looksize.com/api.php';

    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function getOrder($orderReference){
        if(!$orderReference){
            return null;
        }

        return $this->request($this->url, 'POST', [
            'query' => [
                'api_key' => LOOKSIZE_API_KEY,
                'secret' => LOOKSIZE_SECRET,
                'act' => 'getOrderData',
                'order_id' => $orderReference,
            ]
        ]);
    }




}
