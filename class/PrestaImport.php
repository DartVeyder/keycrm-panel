<?php
require_once ('class/KeyCrm.php');
class PrestaImport
{
    private $keyCrm;
    public function __construct()
    {
        $this->keyCrm = new KeyCrm();
    }

    public  function generateData(){
        $data = [];
      //  $offers = $this->keyCrm->product('[product_id]=1602');
        $offers = $this->keyCrm->products();

        foreach ($offers as $offer){
            $product = $offer['product'];

            if( $offer['product_id']  <= 1887 ){
                continue;
            }

            if ( strpos($offer['sku'], '_') !== false) {
                continue;
            }

            if( !$data[$offer['product_id']]){
                $data[$offer['product_id']][$offer['id']]['description'] = $product['description'];
                $data[$offer['product_id']][$offer['id']]['images'] = implode(',',$product['attachments_data'] );
            }
            $data[$offer['product_id']][$offer['id']]['name'] = $product['name'] . ' test';
            $data[$offer['product_id']][$offer['id']]['sku'] = $offer['sku'];
            $data[$offer['product_id']][$offer['id']]['image'] = $offer['thumbnail_url'];
            $data[$offer['product_id']][$offer['id']]['price'] = $offer['price'];
            $data[$offer['product_id']][$offer['id']]['quantity'] = $offer['quantity'];
            $data[$offer['product_id']][$offer['id']]['size'] =  mb_strtoupper($offer['properties'][1]['value']);
            $data[$offer['product_id']][$offer['id']]['color'] = $offer['properties'][0]['value'];


        }

        return $data;
    }

    public function findCommonPartInSKU($skus) {
        if (empty($skus)) {
            return '';
        }

        // Знайти найкоротший SKU
        $shortestSku = min(array_map('strlen', $skus));
        $referenceSku = current(array_filter($skus, function($sku) use ($shortestSku) {
            return strlen($sku) == $shortestSku;
        }));

        // Перевірити всі можливі підрядки
        for ($len = strlen($referenceSku); $len > 0; $len--) {
            for ($start = 0; $start <= strlen($referenceSku) - $len; $start++) {
                $substring = substr($referenceSku, $start, $len);
                $isCommon = true;

                foreach ($skus as $sku) {
                    if (strpos($sku, $substring) === false) {
                        $isCommon = false;
                        break;
                    }
                }

                if ($isCommon) {
                    return $substring;
                }
            }
        }

        return '';
    }
    private function findCommonPrefix($strings) {
        if (empty($strings)) {
            return '';
        }

        $prefix = $strings[0];

        foreach ($strings as $string) {
            while (strpos($string, $prefix) !== 0) {
                $prefix = substr($prefix, 0, -1);
                if ($prefix === '') {
                    return '';
                }
            }
        }

        return $prefix;
    }
    public function generateXLS($data, $filename = 'output.xlsx') {
        if(!$data){
            die('None data');
        }
        $rows = [];
        $rows[] = ['Parent ID', 'ID', 'Description', 'Images', 'Product name', 'SKU','PARENT SKU', 'Price', 'Quantity', 'Size', 'Color'];

        // Write the data
        foreach ($data as $parentId => $items) {
            $parentSku =  $this->findCommonPartInSKU(array_column($items, 'sku'));
            foreach ($items as $id => $item) {
                $rows[] =  [
                    $parentId,
                    $id,
                    isset($item['description']) ? trim($item['description']) : '',
                    isset($item['images']) ? $item['images'] : '',
                    $item['name'],
                    $item['sku'],
                    $parentSku,
                    $item['price'],
                    $item['quantity'],
                    $item['size'],
                    $item['color']
                ]  ;
            }
        }


        $xlsx = Shuchkin\SimpleXLSXGen::fromArray( $rows );
        $xlsx->saveAs($filename);
    }


    public function generateCSV($data, $filename = 'output.csv') {
        // Open the file for writing
        $fp = fopen($filename, 'w');

        // Add BOM for UTF-8
        fwrite($fp, "\xEF\xBB\xBF");

        // Write the header
        fputcsv($fp, ['Parent ID', 'ID', 'Description', 'Images', 'Product name', 'SKU','PARENT SKU', 'Price', 'Quantity', 'Size', 'Color'], ';');

        // Write the data
        foreach ($data as $parentId => $items) {
            $parentSku =  $this->findCommonPartInSKU(array_column($items, 'sku'));
            foreach ($items as $id => $item) {
                fputcsv($fp, [
                    $parentId,
                    $id,
                    isset($item['description']) ? trim($item['description']) : '',
                    isset($item['images']) ? $item['images'] : '',
                    $item['name'],
                    $item['sku'],
                    $parentSku,
                    $item['price'],
                    $item['quantity'],
                    $item['size'],
                    $item['color']
                ], ';');
            }
        }

        // Close the file
        fclose($fp);

        echo "CSV file '$filename' has been created successfully!";
    }
}
