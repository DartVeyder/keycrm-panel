<?php

class MySQLDB {
    private $pdo;

    // Конструктор для підключення до бази даних
    public function __construct($host, $dbname, $username, $password) {
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->logError("Помилка підключення: " . $e->getMessage());
        }
    }

    // Метод для створення таблиці
    public function createTable($table, $columns) {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS $table (" . implode(", ", $columns) . ")";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->logError("Помилка створення таблиці: " . $e->getMessage());
        }
    }

    // Метод додавання запису
    public function insert($table, $data) {
        try {
            $columns = implode(", ", array_keys($data));
            $values = implode(", ", array_fill(0, count($data), "?"));
            $stmt = $this->pdo->prepare("INSERT INTO $table ($columns) VALUES ($values)");
            return $stmt->execute(array_values($data));
        } catch (PDOException $e) {
            $this->logError("Помилка вставки: " . $e->getMessage());
            return false;
        }
    }

    // Метод оновлення запису
    public function update($table, $data, $where, $whereParams) {
        try {
            $setClause = implode(", ", array_map(fn($col) => "$col = ?", array_keys($data)));
            $stmt = $this->pdo->prepare("UPDATE $table SET $setClause WHERE $where");
            return $stmt->execute(array_merge(array_values($data), $whereParams));
        } catch (PDOException $e) {
            $this->logError("Помилка оновлення: " . $e->getMessage());
            return false;
        }
    }

    // Метод видалення запису
    public function delete($table, $where, $whereParams) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM $table WHERE $where");
            return $stmt->execute($whereParams);
        } catch (PDOException $e) {
            $this->logError("Помилка видалення: " . $e->getMessage());
            return false;
        }
    }

    // Метод перевірки наявності запису
    public function exists($table, $where, $params) {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM $table WHERE $where LIMIT 1");
            $stmt->execute($params);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            $this->logError("Помилка перевірки наявності запису: " . $e->getMessage());
            return false;
        }
    }

    // Метод для додавання або оновлення запису
    public function insertOrUpdate($table, $data, $uniqueKey) {
        try {
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            $updatePart = implode(', ', array_map(fn($col) => "$col = VALUES($col)", $columns));

            $sql = "INSERT INTO $table (" . implode(',', $columns) . ") 
                VALUES (" . implode(',', $placeholders) . ") 
                ON DUPLICATE KEY UPDATE $updatePart";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array_values($data));
        } catch (PDOException $e) {
            $this->logError("Помилка insertOrUpdate: " . $e->getMessage());
            return false;
        }
    }
    // Метод для виконання SQL-запиту
    public function query($sql, $params = []) {
        try {
            // Підготовка запиту
            $stmt = $this->pdo->prepare($sql);

            // Виконання запиту з параметрами
            $stmt->execute($params);

            // Якщо запит на вибірку (SELECT)
            if (stripos($sql, "SELECT") === 0) {
                // Повертаємо результати у вигляді масиву
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Якщо запит на вставку (INSERT), оновлення (UPDATE) або видалення (DELETE)
            return true;
        } catch (PDOException $e) {
            // Логування помилки
            $this->logError("Помилка виконання запиту: " . $e->getMessage());
            return false;
        }
    }

    public function insertOrUpdateMulti($table, array $dataArray, $uniqueKey)
    {
        try {
            if (empty($dataArray)) {
                throw new Exception("Передано порожній масив даних");
            }

            $columns = array_keys(reset($dataArray));
            $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $updatePart = implode(', ', array_map(fn($col) => "$col = VALUES($col)", $columns));

            $sql = "INSERT INTO $table (" . implode(',', $columns) . ") 
                VALUES " . implode(',', array_fill(0, count($dataArray), $placeholders)) . " 
                ON DUPLICATE KEY UPDATE $updatePart";

            $stmt = $this->pdo->prepare($sql);

            $values = [];
            foreach ($dataArray as $data) {
                $values = array_merge($values, array_values($data));
            }

            return $stmt->execute($values);
        } catch (PDOException $e) {
            $this->logError("Помилка insertOrUpdate: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->logError("Помилка insertOrUpdate: " . $e->getMessage());
            return false;
        }
    }




    public function insertOrUpdatePartial($table, $data, $uniqueKey, $batchSize = 5) {
        try {
            // Розбиваємо дані на частини
            $chunks = array_chunk($data, $batchSize, true);

            foreach ($chunks as $chunk) {
                // Отримаємо колонки та заповнювачі
                $columns = array_keys($chunk);
                $placeholders = array_fill(0, count($columns), '?');

                // Частина для оновлення
                $updatePart = implode(', ', array_map(fn($col) => "$col = VALUES($col)", $columns));

                // Формуємо запит
                $sql = "INSERT INTO $table (" . implode(',', $columns) . ") 
                    VALUES (" . implode(',', $placeholders) . ") 
                    ON DUPLICATE KEY UPDATE $updatePart";

                $stmt = $this->pdo->prepare($sql);
                // Виконуємо запит для кожної частини даних
                if (!$stmt->execute(array_values($chunk))) {
                    $this->logError("Помилка при виконанні часткового запиту");
                    return false;
                }
            }

            return true;
        } catch (PDOException $e) {
            $this->logError("Помилка insertOrUpdatePartial: " . $e->getMessage());
            return false;
        }
    }


    // Метод для отримання всіх записів
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Помилка отримання даних: " . $e->getMessage());
            return [];
        }
    }

    // Перевірка існування таблиці
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        return $this->fetchOne($sql, [$table]) ? true : false;
    }

    // Метод для отримання одного запису
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Помилка отримання одного запису: " . $e->getMessage());
            return false;
        }
    }

    // Метод логування помилок
    private function logError($message) {
        file_put_contents("logs/errors.log", date("[Y-m-d H:i:s] ") . $message . "\n", FILE_APPEND);
    }
}

?>
