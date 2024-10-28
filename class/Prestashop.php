<?php

class Prestashop extends Base
{
    private string  $API_URL = 'https://twice.com.ua/api';
    public function products($filter = []){
        $query =  [
            'ws_key' => PRESTASHOP_API_KEY,
            'output_format' => 'JSON',
            'display' => 'full'
        ];

        $query = array_merge($query, $filter );

        $request = $this->request($this->API_URL . '/products','GET', [
            'query' => $query
        ]);

        dd( $request);
    }
}
