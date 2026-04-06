<?php

// Připojení k databázi přes MySQLi
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

class Database {
    private $host    = DB_HOST;
    private $user    = DB_USER;
    private $pass    = DB_PASS;
    private $db_name = DB_NAME;
    private $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db_name);

        if ($this->conn->connect_error) {
            die("Chyba připojení k databázi: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
        $this->migrateSchema();
    }

    // Přidá chybějící sloupce (spustí se automaticky při každém připojení)
    private function migrateSchema(): void
    {
        $this->conn->query("
            ALTER TABLE users
                ADD COLUMN IF NOT EXISTS total_score INT NOT NULL DEFAULT 0
        ");
    }

    public function getConnection() {
        return $this->conn;
    }
}