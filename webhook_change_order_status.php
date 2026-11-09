<?php

try {
    if (file_exists(__DIR__ . '/../../config/config.inc.php')) {
        require_once __DIR__ . '/../../config/config.inc.php';
    }
    if (file_exists(__DIR__ . '/../../init.php')) {
        require_once __DIR__ . '/../../init.php';
    }
    require_once(__DIR__ . '/vendor/autoload.php');

    require_once(__DIR__ . '/config.php');
    require_once(__DIR__ . '/class/Base.php');
    require_once(__DIR__ . '/class/KeyCrmV2.php');
    require_once(__DIR__ . '/class/Prestashop.php');
    require_once(__DIR__ . '/class/LookSize.php');

    $keyCrm = new KeyCrmV2();
    $prestashop = new Prestashop();

    $statusPS = [
        2 => 3, // Обробляється 
        5 => 28, // Виконано
        6 => 6   // Скасовано
    ];

    $orderKC = $keyCrm->webhookOrder();

    if ($orderKC) {

        // --- Автоматичне додавання тегу для копій замовлень ---
        if (!empty($orderKC['parent_id'])) {
            $hasCopyTag = false;
            if (!empty($orderKC['tags'])) {
                foreach ($orderKC['tags'] as $tag) {
                    if (isset($tag['id']) && $tag['id'] == 56) {
                        $hasCopyTag = true;
                        break;
                    }
                }
            }
            if (!$hasCopyTag) {
                try {
                    $keyCrm->addTagOrder($orderKC['id'], 56);
                } catch (\Throwable $te) {
                    error_log("Error adding tag 56: " . $te->getMessage());
                }
            }
        }
        // ------------------------------------------------------------

        $global_source_uuid_raw = $orderKC['global_source_uuid'] ?? '';
        $global_source_uuid = !empty($global_source_uuid_raw) ? explode('-', $global_source_uuid_raw) : [];

        $orderKC_id = $orderKC['id'] ?? null;
        $orderKC_source_id = $orderKC['source_id'] ?? null;
        $idOrder = $global_source_uuid[1] ?? null;
        $orderKS_reference = $global_source_uuid[2] ?? null;
        $groupStatusId = $orderKC['status_group_id'] ?? null;
        $orderStatusId = $orderKC['status_id'] ?? null;
        $kcClientId = $orderKC['client_id'] ?? null;

        if ($groupStatusId == 4 && $orderStatusId == 10) {
            $statusPS[4] = 4;  // в дорозі
        } else if ($groupStatusId == 4 && $orderStatusId == 20) {
            $statusPS[4] = 5;
        }

        $idOrderState = ($groupStatusId !== null && isset($statusPS[$groupStatusId])) ? $statusPS[$groupStatusId] : null;

        $product_ids = '';
        if (defined('UPDATE_STOCK_PRICE_CHANGE_STATUS') && UPDATE_STOCK_PRICE_CHANGE_STATUS) {
            if ($orderStatusId == 4 && $orderKC_id) {
                $order = $keyCrm->order($orderKC_id);
                if ($order && !empty($order['products'])) {
                    $product_ids = implode(',', array_column(array_column($order['products'], 'offer'), 'product_id'));
                    if (file_exists(__DIR__ . '/update_products_price_stock.php')) {
                        include(__DIR__ . '/update_products_price_stock.php');
                    }
                }
            }
        }

        $statusIds = [31, 33, 34, 39, 79, 80, 115, 11, 38, 40, 117, 116];
        if (file_exists(__DIR__ . '/refund_config.php')) {
            require(__DIR__ . '/refund_config.php');
            if (isset($refund_statuses) && is_array($refund_statuses)) {
                $statusIds = $refund_statuses;
            }
        }
        $isRefundStatus = in_array($orderStatusId, $statusIds);
        if ($isRefundStatus && $orderKC_id) {
            // для повернення коштів і скасування замовлення в prestashop не змінюємо статус замовлення в prestashop
            $order = $keyCrm->order($orderKC_id);
            if (file_exists(__DIR__ . '/refund.php')) {
                include(__DIR__ . '/refund.php');
            }
        }

        if ($orderKC_source_id == 18 && !empty($idOrder)) {
            $looksize = new LookSize();
            $orderKS = $keyCrm->order($orderKC_id);
            if ($orderStatusId == 10 && !empty($orderKS['shipping']['tracking_code'])) {
                try {
                    $prestashop->addTrackingNumber((int) $idOrder, $orderKS['shipping']['tracking_code'], 22, $orderKC_id);
                } catch (\Throwable $te) {
                    error_log("Error adding tracking number: " . $te->getMessage());
                }
            }

            if (!empty($orderKS['custom_fields'])) {
                $custom_fields = array_column($orderKS['custom_fields'], 'value', 'id');
                $orderKS_reference = $custom_fields[36] ?? $orderKS_reference;
            }

            if (!empty($orderKS_reference)) {
                try {
                    $orderLS = $looksize->getOrder($orderKS_reference);
                    if (!empty($orderLS['list'])) {
                        $keyCrm->addTagOrder($orderKC_id, 265);
                        $action_key = end($orderLS['list'])['action_key'];
                        $looksize->getDataByKey($action_key);
                        $getSizesClient = $looksize->getSizesClient();
                        if ($getSizesClient && $kcClientId) {
                            $keyCrm->updateClient($kcClientId, $getSizesClient);
                        }

                        $getSizesClientByOrder = $looksize->getSizesClientByOrder();
                        if ($getSizesClientByOrder) {
                            $keyCrm->updateOrder($orderKC_id, $getSizesClientByOrder);
                        }
                    }
                } catch (\Throwable $le) {
                    error_log("LookSize error: " . $le->getMessage());
                }
            }

            $orderPS = $prestashop->getOrder((int) $idOrder);
            $currentStatePS = $orderPS['current_state'] ?? 'N/A';
            $text = date("Y-m-d H:i:s") . " orderKC_id: " . $orderKC_id . " orderPS_id: " . $idOrder . " status_group_id: " . $groupStatusId . " source_id: " . $orderKC_source_id . ' current_state_PS: ' . $currentStatePS . ' order_status_id: ' . $orderStatusId . ' productIDS: ' . $product_ids;
            echo $text;

            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            @file_put_contents($logDir . '/changeOrderStatus.txt', $text . "\n", FILE_APPEND);

            // Не змінюємо статус у PrestaShop, якщо це статус повернення коштів або якщо статус не зіставлено
            if (!$isRefundStatus && $orderPS && !empty($idOrderState)) {
                if ($currentStatePS != $idOrderState) {
                    $prestashop->changeOrderStatus($idOrder, $idOrderState);
                }
            }
        }
    }
} catch (\Throwable $e) {
    http_response_code(200); // Повертаємо 200 для KeyCRM, щоб сервіс не блокував вебхук
    $errorMsg = date("Y-m-d H:i:s") . " [WEBHOOK ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    error_log($errorMsg);
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    @file_put_contents($logDir . '/errors.log', $errorMsg . "\n", FILE_APPEND);
    echo "ERROR: " . $e->getMessage();
}
