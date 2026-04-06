<?php
// Nastavení aplikace – klíč/hodnota uložená v DB (tabulka app_settings)
class AppSettings
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->ensureTable();
    }

    // Vytvoří tabulku pokud neexistuje
    private function ensureTable(): void
    {
        $this->conn->query("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT         NOT NULL DEFAULT '',
            updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Načte hodnotu nastavení; vrátí $default pokud klíč neexistuje
    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? $row['setting_value'] : $default;
    }

    // Uloží nebo aktualizuje hodnotu nastavení
    public function set(string $key, string $value): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO app_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }
}
