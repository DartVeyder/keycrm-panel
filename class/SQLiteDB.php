<?php

class Database {
    private $pdo;
    private $databaseType;

    // Конструктор для підключення до бази даних
    public function __construct($databaseType, $host = '', $dbname = '', $username = '', $password = '', $filename = '') {
        $this->databaseType = $databaseType;

        try {
            if ($this->databaseType == 'sqlite') {
                $this->pdo = new PDO("sqlite:" . $filename);
            } elseif ($this->databaseType == 'mysql') {
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
                $this->pdo = new PDO($dsn, $username, $password);
            }
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
    public function insertOrUpdate($table, $data, $where, $whereParams) {
        $exists = $this->exists($table, $where, $whereParams);
        if ($exists) {
            return $this->update($table, $data, $where, $whereParams);
        } else {
            return $this->insert($table, $data);
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
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
        if ($this->databaseType == 'mysql') {
            $sql = "SHOW TABLES LIKE ?";
        }
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
