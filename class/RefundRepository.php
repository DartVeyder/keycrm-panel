<?php

/**
 * Клас для роботи з історією повернень у базі даних MySQL.
 * Відповідає за логування, збереження замовлень, пагінацію та фільтрацію.
 */
class RefundRepository
{
    private PDO $pdo;
    private array $refundConfig = [];

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } else {
            if (!defined('HOST') || !defined('DBNAME')) {
                require_once dirname(__DIR__) . '/config.php';
            }
            $dsn = "mysql:host=" . HOST . ";dbname=" . DBNAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, USERNAME, PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $configFile = dirname(__DIR__) . '/refund_config.php';
        if (file_exists($configFile)) {
            require $configFile;
            $this->refundConfig = $config ?? [];
        }

        $this->ensureTables();
    }

    /**
     * Створення таблиць для логів і замовлень, якщо вони ще не існують.
     */
    public function ensureTables(): void
    {
        $sqlOrders = "CREATE TABLE IF NOT EXISTS refund_orders (
            `order_id` VARCHAR(64) NOT NULL PRIMARY KEY,
            `status` VARCHAR(32) NOT NULL DEFAULT 'INFO',
            `payment_system` VARCHAR(255) NULL,
            `system_type` VARCHAR(64) NULL,
            `latest_msg` TEXT NULL,
            `updated_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            INDEX `idx_updated_at` (`updated_at`),
            INDEX `idx_status` (`status`),
            INDEX `idx_system_type` (`system_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $sqlLogs = "CREATE TABLE IF NOT EXISTS refund_logs (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `order_id` VARCHAR(64) NOT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'INFO',
            `message` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            INDEX `idx_order_id` (`order_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->pdo->exec($sqlOrders);
        $this->pdo->exec($sqlLogs);
    }

    /**
     * Запис події повернення в базу даних (в таблицю логів і зведену таблицю замовлень).
     *
     * @param string|int $orderId
     * @param string $message
     * @param string|null $date
     * @return void
     */
    public function log($orderId, string $message, ?string $date = null): void
    {
        $orderId = trim((string)$orderId);
        if ($orderId === '') {
            $orderId = 'UNKNOWN';
        }

        $date = $date ?? date('Y-m-d H:i:s');
        $status = $this->extractStatus($message);
        $cleanMessage = $this->cleanMessageText($message);

        // 1. Запис у повний журнал подій
        $stmtLog = $this->pdo->prepare("INSERT INTO refund_logs (`order_id`, `status`, `message`, `created_at`) VALUES (?, ?, ?, ?)");
        $stmtLog->execute([$orderId, $status, $cleanMessage, $date]);

        // 2. Визначення платіжної системи
        list($detectedSystem, $detectedType) = $this->detectSystem($message);

        // 3. Оновлення / створення запису в зведеній таблиці refund_orders
        $existing = $this->pdo->prepare("SELECT * FROM refund_orders WHERE `order_id` = ? LIMIT 1");
        $existing->execute([$orderId]);
        $row = $existing->fetch();

        $ranks = ['INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'SUCCESS' => 4];

        if (!$row) {
            $stmtInsert = $this->pdo->prepare("INSERT INTO refund_orders 
                (`order_id`, `status`, `payment_system`, `system_type`, `latest_msg`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $orderId,
                $status,
                $detectedSystem,
                $detectedType,
                $cleanMessage,
                $date,
                $date
            ]);
        } else {
            $currentRank = $ranks[$row['status']] ?? 1;
            $newRank = $ranks[$status] ?? 1;
            
            // Зберігаємо важливіший статус (наприклад, якщо був SUCCESS або став SUCCESS)
            $finalStatus = $row['status'];
            if ($status === 'SUCCESS' || ($newRank >= $currentRank && $row['status'] !== 'SUCCESS')) {
                $finalStatus = $status;
            }

            $finalSystem = $detectedSystem ?: $row['payment_system'];
            $finalType = $detectedType ?: $row['system_type'];

            $stmtUpdate = $this->pdo->prepare("UPDATE refund_orders SET 
                `status` = ?, 
                `payment_system` = ?, 
                `system_type` = ?, 
                `latest_msg` = ?, 
                `updated_at` = ? 
                WHERE `order_id` = ?");
            $stmtUpdate->execute([
                $finalStatus,
                $finalSystem,
                $finalType,
                $cleanMessage,
                $date,
                $orderId
            ]);
        }
    }

    /**
     * Отримання списку замовлень з пагінацією та фільтрацією.
     *
     * @param int $page
     * @param int $perPage
     * @param array $filters ['search' => string, 'status' => string, 'system_type' => string]
     * @return array
     */
    public function getPaginatedHistory(int $page = 1, int $perPage = 25, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(200, $perPage));

        $where = [];
        $params = [];

        // Пошук по order_id або тексту повідомлення
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $where[] = "(`order_id` LIKE ? OR `latest_msg` LIKE ? OR `payment_system` LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Фільтр за статусом
        if (!empty($filters['status']) && in_array(strtoupper($filters['status']), ['SUCCESS', 'ERROR', 'WARNING', 'INFO'])) {
            $where[] = "`status` = ?";
            $params[] = strtoupper($filters['status']);
        }

        // Фільтр за типом платіжної системи
        if (!empty($filters['system_type']) && $filters['system_type'] !== 'all') {
            $where[] = "`system_type` = ?";
            $params[] = trim($filters['system_type']);
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // 1. Отримуємо загальну кількість записів
        $countSql = "SELECT COUNT(*) FROM refund_orders {$whereClause}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
        if ($page > $totalPages && $totalRecords > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // 2. Отримуємо дані поточної сторінки
        $dataSql = "SELECT * FROM refund_orders {$whereClause} ORDER BY `updated_at` DESC LIMIT {$offset}, {$perPage}";
        $stmtData = $this->pdo->prepare($dataSql);
        $stmtData->execute($params);
        $rawOrders = $stmtData->fetchAll();

        // 3. Завантажуємо детальні логи для всіх замовлень на цій сторінці
        $orderIds = array_column($rawOrders, 'order_id');
        $messagesByOrder = [];

        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $logsSql = "SELECT `order_id`, `status`, `message`, `created_at` 
                        FROM refund_logs 
                        WHERE `order_id` IN ({$placeholders}) 
                        ORDER BY `created_at` DESC, `id` DESC";
            $stmtLogs = $this->pdo->prepare($logsSql);
            $stmtLogs->execute($orderIds);
            $allLogs = $stmtLogs->fetchAll();

            foreach ($allLogs as $logRow) {
                $oid = $logRow['order_id'];
                if (!isset($messagesByOrder[$oid])) {
                    $messagesByOrder[$oid] = [];
                }
                $messagesByOrder[$oid][] = [
                    'date' => $logRow['created_at'],
                    'status' => $logRow['status'],
                    'text' => $logRow['message'],
                ];
            }
        }

        // 4. Формуємо відповідь, сумісну з інтерфейсом index.php
        $orders = [];
        foreach ($rawOrders as $row) {
            $oid = $row['order_id'];
            $orders[] = [
                'order_id' => $oid,
                'date' => $row['updated_at'],
                'status' => $row['status'],
                'system' => $row['payment_system'] ?: 'Невідомо',
                'system_type' => $row['system_type'] ?: 'unknown',
                'latest_msg' => $row['latest_msg'] ?? '',
                'messages' => $messagesByOrder[$oid] ?? []
            ];
        }

        return [
            'success' => true,
            'history' => $orders,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
            ]
        ];
    }

    /**
     * Імпорт даних з файлу refund.log у базу даних.
     * Запобігає дублюванню.
     *
     * @param string|null $logFile
     * @return int Кількість імпортованих записів
     */
    public function importFromLogFile(?string $logFile = null): int
    {
        $logFile = $logFile ?? (dirname(__DIR__) . '/logs/refund.log');
        if (!file_exists($logFile)) {
            return 0;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return 0;
        }

        $imported = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($lines as $line) {
                $date = null;
                $orderId = null;
                $message = '';

                if (preg_match('/^\[(.*?)\]\s*\[Order #(.*?)\]\s*(.*)$/', $line, $m)) {
                    $date = $m[1];
                    $orderId = trim($m[2]);
                    $message = trim($m[3]);
                } elseif (preg_match('/^\[(.*?)\]\s*Order\s*(\S+)\s*\|\s*(.*)$/', $line, $m)) {
                    $date = $m[1];
                    $orderId = trim($m[2]);
                    $message = trim($m[3]);
                } elseif (preg_match('/^\[(.*?)\]\s*(.*)$/', $line, $m)) {
                    $date = $m[1];
                    $orderId = 'SYSTEM';
                    $message = trim($m[2]);
                }

                if (!$date || !$orderId) {
                    continue;
                }

                // Перевірка на дублікат у логах
                $check = $this->pdo->prepare("SELECT 1 FROM refund_logs WHERE `order_id` = ? AND `created_at` = ? AND `message` = ? LIMIT 1");
                $check->execute([$orderId, $date, $this->cleanMessageText($message)]);
                if ($check->fetchColumn()) {
                    continue;
                }

                $this->log($orderId, $message, $date);
                $imported++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $imported;
    }

    /**
     * Визначення статусу повідомлення.
     *
     * @param string $message
     * @return string
     */
    private function extractStatus(string $message): string
    {
        if (preg_match('/^(SUCCESS|ERROR|WARNING|INFO)\b/i', $message, $m)) {
            return strtoupper($m[1]);
        }
        if (stripos($message, 'SUCCESS') !== false || stripos($message, 'успішно') !== false || stripos($message, 'успішне') !== false) {
            return 'SUCCESS';
        }
        if (stripos($message, 'ERROR') !== false || stripos($message, 'помилка') !== false) {
            return 'ERROR';
        }
        if (stripos($message, 'WARNING') !== false || stripos($message, 'увага') !== false) {
            return 'WARNING';
        }
        return 'INFO';
    }

    /**
     * Очищення префіксу статусу з тексту повідомлення.
     *
     * @param string $message
     * @return string
     */
    private function cleanMessageText(string $message): string
    {
        return preg_replace('/^(SUCCESS|ERROR|WARNING|INFO):\s*/i', '', trim($message));
    }

    /**
     * Визначення платіжної системи та її типу за змістом повідомлення.
     *
     * @param string $message
     * @return array [system, system_type]
     */
    private function detectSystem(string $message): array
    {
        $foundFop = null;
        if (preg_match('/ФОП: (.*?)$/ui', $message, $m)) {
            $foundFop = trim($m[1]);
        } elseif (preg_match('/ключ.*?: (.*?)$/ui', $message, $m)) {
            $foundFop = trim($m[1]);
        }

        if ($foundFop) {
            $system = $foundFop;
            $systemType = $this->refundConfig[$foundFop]['type'] ?? 'unknown';
            return [$system, $systemType];
        }

        $lowerMsg = mb_strtolower($message);
        if (strpos($lowerMsg, 'liqpay') !== false) {
            return ['LiqPay', 'liqpay'];
        } elseif (strpos($lowerMsg, 'частинами') !== false || strpos($lowerMsg, 'payparts') !== false) {
            return ['Оплата частинами (Приват)', 'privat_payparts'];
        } elseif (strpos($lowerMsg, 'приват') !== false || strpos($lowerMsg, 'платіж №ac') !== false) {
            return ['ПриватБанк', 'privatbank'];
        } elseif (strpos($lowerMsg, 'моно') !== false || strpos($lowerMsg, 'monobank') !== false) {
            return ['Monobank', 'monobank'];
        }

        return [null, null];
    }
}

