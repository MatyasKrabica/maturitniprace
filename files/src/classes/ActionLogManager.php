<?php
// Logování admin/systémových akcí do tabulky action_logs
require_once __DIR__ . '/../php/Database.php';

/**
 * Správce logů admin/systémových akcí
 */
class ActionLogManager {
    private $db;

    /**
     * Inicializace správce logů
     * 
     * @param Database $db instance databáze
     */
    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * @param int $userId ID uživatele který akci provedl
     * @param string $type typ akce (např. LOGIN_FAILED, ADMIN_BAN_USER)
     * @param string $description popis akce
     * @param int|null $targetId ID cílového uživatele (nebo null)
     * @return bool úspěch
     */
    public function logAction(int $userId, string $type, string $description, ?int $targetId = null): bool {
        $conn = $this->db->getConnection();
        
        $description = substr($description, 0, 65535); 

        if ($targetId === null) {
            $sql = "INSERT INTO action_logs (user_id, target_id, action_type, description) 
                    VALUES (?, NULL, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iss', $userId, $type, $description);
        } else {
            $sql = "INSERT INTO action_logs (user_id, target_id, action_type, description) 
                    VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iiss', $userId, $targetId, $type, $description);
        }
        
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$success) {
            error_log("Action Log Database Error: " . mysqli_error($conn));
        }

        return $success;
    }

    /**
     * @param int $targetId ID uživatele, na kterém byly akce provedeny
     * @return array logy setřízené od nejnovějšího
     */
    public function getLogsByTargetId(int $targetId): array {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT 
                    al.action_type, al.description, al.performed_at, 
                    u.username AS admin_username
                FROM action_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.target_id = ?
                ORDER BY al.performed_at DESC";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $targetId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        $logs = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $logs[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $logs;
    }
}