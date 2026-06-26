<?php
require_once('vendor/autoload.php');
require_once('config.php');
require_once('class/KeyCrmV2.php');

$keyCrm = new KeyCrmV2();

// ТУТ ВКАЖІТЬ ID ВАШОГО ТЕСТОВОГО ЗАМОВЛЕННЯ В KEYCRM
$testOrderId = 289117;

echo "<h1>Тестування повернення для замовлення #{$testOrderId}</h1>";
echo "<pre>";

$order = $keyCrm->order($testOrderId);

if (!$order || !isset($order['id'])) {
    die("Помилка: Замовлення #{$testOrderId} не знайдено в KeyCRM.");
}



echo "Замовлення знайдено. Запускаємо refund.php...\n\n";

// Викликаємо скрипт повернення
require_once('refund.php');

echo "\n\nТестування завершено.</pre>";
