<?php

class LiqPayPayment {
    private $publicKey;
    private $privateKey;

    public function __construct($publicKey, $privateKey) {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    public function refund($orderId, $amount) {
        $params = array(
            'action'       => 'refund',
            'version'      => '3',
            'public_key'   => $this->publicKey,
            'order_id'     => $orderId,
            'amount'       => $amount
        );

        $data = base64_encode(json_encode($params));
        $signature = base64_encode(sha1($this->privateKey . $data . $this->privateKey, 1));

        $post = array(
            'data'      => $data,
            'signature' => $signature
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.liqpay.ua/api/request');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("LiqPay CURL Error: " . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("LiqPay API Error: Empty or invalid response");
        }
        
        if (isset($result['result']) && $result['result'] === 'error') {
            $err = $result['err_description'] ?? 'Unknown error';
            $rawJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            echo "\n[Детальна відповідь LiqPay (order_id: {$orderId})]:\n" . $rawJson . "\n";
            $logMsg = date('[Y-m-d H:i:s]') . " LiqPay Error for order_id {$orderId}: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents(__DIR__ . '/../logs/liqpay_debug.log', $logMsg, FILE_APPEND);
            throw new Exception("LiqPay Error: " . $err);
        }
        
        if (isset($result['status']) && in_array($result['status'], ['error', 'failure'])) {
            $err = $result['err_description'] ?? 'Unknown error';
            $rawJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            echo "\n[Детальна відповідь LiqPay (order_id: {$orderId})]:\n" . $rawJson . "\n";
            $logMsg = date('[Y-m-d H:i:s]') . " LiqPay Error for order_id {$orderId}: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents(__DIR__ . '/../logs/liqpay_debug.log', $logMsg, FILE_APPEND);
            throw new Exception("LiqPay Error: " . $err);
        }

        // Логуємо успішні відповіді теж для розуміння
        $logMsg = date('[Y-m-d H:i:s]') . " LiqPay Success for order_id {$orderId}: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents(__DIR__ . '/../logs/liqpay_debug.log', $logMsg, FILE_APPEND);

        return $result;
    }
}
