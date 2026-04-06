<?php
// Systém podpory (tickety, odpovědi, kategorie)
require_once __DIR__ . '/../php/Database.php';

/**
 * Správce ticketů.
 */
class TicketManager {
    private $db;

    public const STATUS_CLOSED = 0;
    public const STATUS_OPEN = 1;
    public const STATUS_AWAITING_USER = 2; 
    public const STATUS_AWAITING_SUPPORT = 3; 

    public const OTHER_CATEGORY_ID = 5;

    public const STATUSES = [
        self::STATUS_CLOSED          => 'ticket_status_closed',
        self::STATUS_OPEN            => 'ticket_status_open',
        self::STATUS_AWAITING_USER   => 'ticket_status_awaiting_user',
        self::STATUS_AWAITING_SUPPORT=> 'ticket_status_awaiting_support',
    ];

    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /** @return int ID kategorie 'Ostatní' */
    public function getOtherCategoryId(): int {
        return self::OTHER_CATEGORY_ID; 
    }

    /** @return array seznam kategorií ticketů */
    public function getCategories(): array {
        $conn = $this->db->getConnection();
        $res = mysqli_query($conn, "SELECT id, name FROM ticket_categories ORDER BY name");
        $categories = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $categories[] = $r;
            }
        }
        return $categories;
    }

    /** @return array všechny tickety (pro admin/support) */
    public function getAllTickets(): array {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT 
                    t.id, t.name, t.status, t.time, t.username,
                    tc.name AS category_name
                FROM tickets t
                LEFT JOIN ticket_categories tc ON t.category_id = tc.id
                ORDER BY t.status DESC, t.time DESC";
                
        $res = mysqli_query($conn, $sql);
        
        $tickets = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $r['status_text'] = self::STATUSES[$r['status']] ?? 'unknown';
                $tickets[] = $r;
            }
        }
        return $tickets;
    }

    /**
     * @param string $username uživatelské jméno
     * @return array tickety daného uživatele
     */
    public function getUserTickets(string $username): array {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT 
                    t.id, t.name, t.status, t.time, t.username,
                    tc.name AS category_name
                FROM tickets t
                LEFT JOIN ticket_categories tc ON t.category_id = tc.id
                WHERE t.username = ?
                ORDER BY t.status DESC, t.time DESC";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        $tickets = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['status_text'] = self::STATUSES[$r['status']] ?? 'unknown';
            $tickets[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $tickets;
    }

    /**
     * @param string $username autor ticketu
     * @param string $name název ticketu
     * @param string $initialMessage úvodní zpráva
     * @param int $categoryId ID kategorie
     * @param int $status výchozí stav
     * @return int|null ID nového ticketu nebo null při chybě
     */
    public function createTicket(string $username, string $name, string $initialMessage, int $categoryId, int $status = self::STATUS_OPEN): ?int {
        $conn = $this->db->getConnection();
        
        $timestamp = time();
        
        $sql = "INSERT INTO tickets (username, name, initial_message, category_id, status, time)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssiii', $username, $name, $initialMessage, $categoryId, $status, $timestamp);
        
        if (mysqli_stmt_execute($stmt)) {
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            return $newId;
        } else {
            error_log("DB Error creating ticket: " . mysqli_error($conn));
            mysqli_stmt_close($stmt);
            return null;
        }
    }
    
    /**
     * @param int $ticketId ID ticketu
     * @return array|null data ticketu nebo null
     */
    public function getTicketById(int $ticketId): ?array {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT 
                    t.id, t.name, t.initial_message, t.status, t.time, t.username, t.category_id,
                    tc.name AS category_name
                FROM tickets t
                LEFT JOIN ticket_categories tc ON t.category_id = tc.id
                WHERE t.id = ? LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $ticketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ticket = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        
        if ($ticket) {
            $ticket['status_text'] = self::STATUSES[$ticket['status']] ?? 'unknown';
        }
        
        return $ticket;
    }

    /**
     * @param int $ticketId ID ticketu
     * @return array odpovědi k ticketu
     */
    public function getTicketAnswers(int $ticketId): array {
        $conn = $this->db->getConnection();
        $sql = "SELECT 
                    id, ticket_id, username, text, time, admin
                FROM ticket_answers
                WHERE ticket_id = ? 
                ORDER BY time ASC";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $ticketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        $answers = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $answers[] = $r;
        }
        mysqli_stmt_close($stmt);
        return $answers;
    }

    /**
     * @param int $ticketId ID ticketu
     * @param string $username jméno autora odpovědi
     * @param string $text text odpovědi
     * @param bool $isAdmin zda odpovídá admin
     * @return bool úspěch
     */
    public function addAnswer(int $ticketId, string $username, string $text, bool $isAdmin): bool {
        $conn = $this->db->getConnection();
        $timestamp = time();
        $adminFlag = $isAdmin ? 1 : 0;
        
        $sql = "INSERT INTO ticket_answers (ticket_id, username, text, time, admin) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'issii', $ticketId, $username, $text, $timestamp, $adminFlag);

        if (mysqli_stmt_execute($stmt)) {
            
            $newStatus = $isAdmin ? self::STATUS_AWAITING_USER : self::STATUS_AWAITING_SUPPORT;
            $this->updateTicketStatus($ticketId, $newStatus);
            
            mysqli_stmt_close($stmt);
            return true;
        } else {
            error_log("DB Error adding answer: " . mysqli_error($conn));
            mysqli_stmt_close($stmt);
            return false;
        }
    }
    /**
     * @param int $answerId ID odpovědi
     * @return bool úspěch
     */
    public function deleteAnswer(int $answerId): bool {
        $conn = $this->db->getConnection();
        
        $sql = "DELETE FROM ticket_answers WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $answerId);
        
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $success;
    }   

    /**
     * @param int $ticketId ID ticketu
     * @param int $status nový stav
     * @return bool úspěch
     */
    private function updateTicketStatus(int $ticketId, int $status): bool {
        $conn = $this->db->getConnection();
        $sql = "UPDATE tickets SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $status, $ticketId);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $success;
    }

    /** @param int $ticketId ID ticketu @return bool */
    public function closeTicket(int $ticketId): bool {
        return $this->updateTicketStatus($ticketId, self::STATUS_CLOSED);
    }
    
    /** @param int $ticketId ID ticketu @return bool */
    public function reopenTicket(int $ticketId): bool {
        return $this->updateTicketStatus($ticketId, self::STATUS_OPEN);
    }

    /**
     * @param int $ticketId ID ticketu (smaže i odpovědi)
     * @return bool úspěch
     */
    public function deleteTicket(int $ticketId): bool {
        $conn = $this->db->getConnection();
        
        $stmt1 = mysqli_prepare($conn, "DELETE FROM ticket_answers WHERE ticket_id = ?");
        mysqli_stmt_bind_param($stmt1, 'i', $ticketId);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        $stmt2 = mysqli_prepare($conn, "DELETE FROM tickets WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, 'i', $ticketId);
        $success = mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        return $success;
    }
}
?>