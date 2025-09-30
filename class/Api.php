<?php
class Api {
    private $db;

    public function __construct(MySQLDB $db) {
        $this->db = $db;
    }

    private function response($data, $code = 200) {
        http_response_code($code);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Статистика по одному SKU, групування по датах 

    public function getQuantityBySku(string $sku) {
    $sql = "
        SELECT 
            date_created AS date,
            quantity,
            quantity_1c
        FROM products_log
        WHERE sku = ?
        GROUP BY date_created 
        ORDER BY date_created  ASC
    ";

    $data = $this->db->fetchAll($sql, [$sku]);

    $this->response([
        "status" => "success",
        "sku" => $sku,
        "quantity_by_date" => $data
    ]);
}

}
