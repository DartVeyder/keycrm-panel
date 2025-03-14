<?php
use GuzzleHttp\Client;

class LookSize extends Base
{
    private $url = 'https://www.looksize.com/api.php';

    private Client $client;
    public $getDataByKey;

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

    public function getSizesClientByOrder(){
        $dataUser = $this->getDataByKey;
        $params = '';
        if($dataUser['userSize']['chest_circ']){
            $params .= "Обхват грудей: " . $dataUser['userSize']['chest_circ'] . "\n";
        }

        if($dataUser['userSize']['waist']){
            $params .= "Обхват талії: " . $dataUser['userSize']['waist'] . "\n";
        }

        if($dataUser['user_size_standart']){
            $params .= "Розмір: " . $dataUser['user_size_standart'] . "\n";
        }
        if($dataUser['userSize']['hips']){
            $params .= "Обхват стегон: " . $dataUser['userSize']['hips'] . "\n";
        }

        return  [
            "custom_fields" => [
                [
                    "uuid" =>"OR_1025",
                    "value" => $params
                ]
            ]
        ];
    }
    public function getSizesClient(){
        $dataUser = $this->getDataByKey;
        $keyCrmData = [];


        if($dataUser['userSize']['chest_circ']){
            $keyCrmData['custom_fields'][] = [
                "uuid" =>"CT_1024",
                "value" => $dataUser['userSize']['chest_circ']
            ];
        }

        if($dataUser['userSize']['waist']){
            $keyCrmData['custom_fields'][] = [
                "uuid" =>"CT_1022",
                "value" => $dataUser['userSize']['waist']
            ];
        }

        if($dataUser['user_size_standart']){
            $keyCrmData['custom_fields'][] = [
                "uuid" =>"CT_1020",
                "value" => $dataUser['user_size_standart']
            ];
        }
        if($dataUser['userSize']['hips']){
            $keyCrmData['custom_fields'][] = [
                "uuid" =>"CT_1023",
                "value" => $dataUser['userSize']['hips']
            ];
        }

        return $keyCrmData;

    }

    public function getDataByKey(string $actionKey){
        $this->getDataByKey =  $this->request($this->url, 'POST', [
            'query' => [
                'api_key' => LOOKSIZE_API_KEY,
                'secret' => LOOKSIZE_SECRET,
                'act' => 'getDataByKey',
                'actionKey' => $actionKey,
            ]
         ]);
    }

}
