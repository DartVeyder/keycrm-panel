<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


class Base
{
    protected function request($endpoint, $method, $params = []){

        $client = new Client();
        try {
            $response = $client->request($method, $endpoint, $params);

            // Get the response body as a string
            return json_decode($response->getBody(), 1);
        }catch (RequestException $e) {

            // Обробка помилок
            echo 'Request Error: ' . $e->getMessage();
            return null;
        }
    }

    protected function saveLog($text, $path){
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }

}
