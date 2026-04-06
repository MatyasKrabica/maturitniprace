<?php
// Správa výzěv a XP (zahájení, splňování, žebríčky, historie)
require_once __DIR__ . '/../php/Database.php';
require_once 'UserManager.php';

class ChallengeManager {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * @param int $userId ID uživatele
     * @return array|null aktivní výzva nebo null
     */
    public function getActiveChallenge(int $userId) {
        $conn = $this->db->getConnection();
        $sql = "SELECT uc.id as uc_id, uc.challenge_id, uc.expires_at, 
                       c.title, c.xp_reward, c.xp_penalty, c.description, c.goal_steps 
                FROM user_challenges uc 
                JOIN challenges c ON uc.challenge_id = c.id 
                WHERE uc.user_id = ? AND uc.status = 'active' 
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    /**
     * @param int $id ID výzvy
     * @return array|null detail výzvy nebo null
     */
    public function getChallengeById(int $id): ?array {
        $conn = $this->db->getConnection();
        $sql = "SELECT c.*, ci.name as city_name, r.name as region_name 
                FROM challenges c
                LEFT JOIN cities ci ON c.city_id = ci.id
                LEFT JOIN regions r ON c.region_id = r.id
                WHERE c.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result) ?: null;
    }

    /**
     * @param int $userId ID uživatele
     * @param int $userChallengeId ID záznamu user_challenges
     * @param int $reward XP odměna
     * @return bool úspěch
     */
    public function completeChallenge(int $userId, int $userChallengeId, int $reward): bool {
        $conn = $this->db->getConnection();
        mysqli_begin_transaction($conn);
        try {
            $stmt1 = mysqli_prepare($conn, "UPDATE user_challenges SET status = 'completed', completed_at = NOW() WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt1, 'ii', $userChallengeId, $userId);
            mysqli_stmt_execute($stmt1);

            $stmt2 = mysqli_prepare($conn, "UPDATE users SET xp = xp + ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt2, 'ii', $reward, $userId);
            mysqli_stmt_execute($stmt2);

            $userManager = new UserManager($this->db);
            $userManager->refreshUserRole($userId);

            mysqli_commit($conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            return false;
        }
    }

    public function getChallengesWithDistance(?float $userLat, ?float $userLng): array {
        $conn = $this->db->getConnection();

        if ($userLat !== null && $userLng !== null) {
            $sql = "SELECT c.*, ci.name as city_name, r.name as region_name,
                           CASE WHEN c.city_id IS NOT NULL AND ci.latitude IS NOT NULL THEN
                               ROUND(6371 * ACOS(LEAST(1,
                                   COS(RADIANS(?)) * COS(RADIANS(ci.latitude)) * COS(RADIANS(ci.longitude) - RADIANS(?))
                                   + SIN(RADIANS(?)) * SIN(RADIANS(ci.latitude))
                               )), 1)
                           ELSE NULL END as distance_km
                    FROM challenges c
                    LEFT JOIN cities ci ON c.city_id = ci.id
                    LEFT JOIN regions r ON c.region_id = r.id
                    ORDER BY (distance_km IS NULL) ASC, distance_km ASC, c.id DESC";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ddd', $userLat, $userLng, $userLat);
        } else {
            $sql = "SELECT c.*, ci.name as city_name, r.name as region_name, NULL as distance_km
                    FROM challenges c
                    LEFT JOIN cities ci ON c.city_id = ci.id
                    LEFT JOIN regions r ON c.region_id = r.id
                    ORDER BY c.id DESC";
            $stmt = mysqli_prepare($conn, $sql);
        }

        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) { $data[] = $row; }
        return $data;
    }

    /**
     * @param string|null $country kód země (např. CZ)
     * @param int|null $regionId ID kraje
     * @param int|null $cityId ID města
     * @return array seznam výzěv seřazených podle relevance
     */
    public function getChallenges(?string $country, ?int $regionId, ?int $cityId): array {
        $conn = $this->db->getConnection();
        $sql = "SELECT c.*, ci.name as city_name, r.name as region_name,
                CASE 
                    WHEN c.city_id = ? THEN 4
                    WHEN c.region_id = ? THEN 3
                    WHEN c.country_code = ? THEN 2
                    ELSE 1 
                END as relevance_score
                FROM challenges c
                LEFT JOIN cities ci ON c.city_id = ci.id
                LEFT JOIN regions r ON c.region_id = r.id
                ORDER BY relevance_score DESC, c.id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iis', $cityId, $regionId, $country);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) { $data[] = $row; }
        return $data;
    }

    /**
     * @param int $userId ID uživatele
     * @param int $challengeId ID výzvy
     * @return bool úspěch
     */
    public function startUserChallenge(int $userId, int $challengeId): bool {
        $conn = $this->db->getConnection();
        if ($this->getActiveChallenge($userId)) return false;

        $stmt = mysqli_prepare($conn, "SELECT time_limit_hours FROM challenges WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $challengeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$res) return false;

        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$res['time_limit_hours']} hours"));
        $sql = "INSERT INTO user_challenges (user_id, challenge_id, started_at, expires_at, status) 
                VALUES (?, ?, NOW(), ?, 'active')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iis', $userId, $challengeId, $expiresAt);
        return mysqli_stmt_execute($stmt);
    }

    /**
     * @param array $data data výzvy z formuláře
     * @return bool úspěch
     */
    public function createChallenge($data) {
        $conn = $this->db->getConnection();
        
        $title = $data['title'];
        $description = $data['description'];
        $goal_steps = (int)$data['goal_steps'];
        $activity_type = isset($data['activity_type']) ? (int)$data['activity_type'] : 7;
        $xp_reward = (int)$data['xp_reward'];
        $xp_penalty = (int)$data['xp_penalty'];
        $time_limit = (int)$data['time_limit_hours'];
        $country = !empty($data['country_code']) ? $data['country_code'] : null;
        $region = !empty($data['region_id']) ? (int)$data['region_id'] : null;
        $city = !empty($data['city_id']) ? (int)$data['city_id'] : null;
        $repeatable = isset($data['is_repeatable']) ? (int)$data['is_repeatable'] : 1;

        $sql = "INSERT INTO challenges (title, description, goal_steps, activity_type, xp_reward, xp_penalty, time_limit_hours, country_code, region_id, city_id, is_repeatable) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssiiiiisiii', 
            $title, $description, $goal_steps, $activity_type, $xp_reward, $xp_penalty, $time_limit, $country, $region, $city, $repeatable
        );
        
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * @param int $userId ID uživatele
     * @return array historie výzěv (completed/failed)
     */
    public function getUserChallengeHistory(int $userId): array {
        $conn = $this->db->getConnection();
        $sql = "SELECT uc.*, c.title, c.xp_reward, c.xp_penalty 
                FROM user_challenges uc 
                JOIN challenges c ON uc.challenge_id = c.id 
                WHERE uc.user_id = ? AND uc.status != 'active' 
                ORDER BY uc.expires_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) { $data[] = $row; }
        return $data;
    }

    /**
     * @param int $id ID výzvy
     * @param array $data nová data
     * @return bool úspěch
     */
    public function updateChallenge(int $id, array $data): bool {
        $conn = $this->db->getConnection();
        $title = $data['title'];
        $description = $data['description'];
        $goal_steps = (int)$data['goal_steps'];
        $activity_type = isset($data['activity_type']) ? (int)$data['activity_type'] : 7;
        $xp_reward = (int)$data['xp_reward'];
        $xp_penalty = (int)$data['xp_penalty'];
        $time_limit = (int)$data['time_limit_hours'];
        $country = !empty($data['country_code']) ? $data['country_code'] : null;
        $region = !empty($data['region_id']) ? (int)$data['region_id'] : null;
        $city = !empty($data['city_id']) ? (int)$data['city_id'] : null;
        $repeatable = isset($data['is_repeatable']) ? (int)$data['is_repeatable'] : 0;

        $sql = "UPDATE challenges SET 
                title=?, description=?, goal_steps=?, activity_type=?, xp_reward=?, xp_penalty=?, 
                time_limit_hours=?, country_code=?, region_id=?, city_id=?, is_repeatable=? 
                WHERE id=?";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssiiiiisiiii', 
            $title, $description, $goal_steps, $activity_type, $xp_reward, $xp_penalty, 
            $time_limit, $country, $region, $city, $repeatable, $id
        );
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }

    /**
     * @param int $userId ID uživatele
     * @param int $userChallengeId ID záznamu user_challenges
     * @param int $xpPenalty XP penalizace
     * @return bool úspěch
     */
    public function cancelChallenge(int $userId, int $userChallengeId, int $xpPenalty): bool {
        $conn = $this->db->getConnection();
        mysqli_begin_transaction($conn);
        try {
            $sql = "UPDATE user_challenges SET status = 'failed' WHERE id = ? AND user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ii', $userChallengeId, $userId);
            mysqli_stmt_execute($stmt);

            $sqlXP = "UPDATE users SET xp = GREATEST(0, xp - ?) WHERE id = ?";
            $stmtXP = mysqli_prepare($conn, $sqlXP);
            mysqli_stmt_bind_param($stmtXP, 'ii', $xpPenalty, $userId);
            mysqli_stmt_execute($stmtXP);

            $uM = new UserManager($this->db);
            $uM->refreshUserRole($userId);

            mysqli_commit($conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            return false;
        }
    }

    /**
     * @param int $limit počet hráčů
     * @return mixed výsledek dotazu (mysqli_result)
     */
    public function getGlobalLeaderboard(int $limit = 10) {
        $conn = $this->db->getConnection();
        $sql = "SELECT id, username, xp, profile_image, role FROM users ORDER BY xp DESC LIMIT ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $limit);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }

    /**
     * @param int $challengeId ID výzvy
     * @param int $limit počet záznamů
     * @return mixed výsledek dotazu (mysqli_result)
     */
    public function getChallengeLeaderboard(int $challengeId, int $limit = 10) {
        $conn = $this->db->getConnection();
        $sql = "SELECT u.username, uc.completed_at 
                FROM user_challenges uc 
                JOIN users u ON uc.user_id = u.id 
                WHERE uc.challenge_id = ? AND uc.status = 'completed' 
                ORDER BY uc.completed_at ASC LIMIT ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $challengeId, $limit);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }
}