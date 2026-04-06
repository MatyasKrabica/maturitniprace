<?php
// Správa uživatelů (přihlášení, registrace, profil, heslo, avatar)
require_once __DIR__ . '/../php/Database.php';
require_once __DIR__ . '/../php/settings.php';
class UserManager
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    /**
     * @param string $username přihlašovací jméno
     * @param string $password heslo
     * @return array výsledek přihlášení (error, id, role...)
     */
    public function login(string $username, string $password): array
    {
        $conn = $this->db->getConnection();

        $sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($password, $user['password'])) {
            return ['error' => true, 'message' => 'Chybné jméno nebo heslo.'];
        }

        $isBanned = (int)($user['is_banned'] ?? 0);
        $bannedUntil = $user['banned_until'];

        if ($isBanned === 1) {
            if ($bannedUntil && strtotime($bannedUntil) < time()) {
                $this->unbanUser($user['id'], 0); // 0 = Systém
                $isBanned = 0;
                $bannedUntil = null;

                $user['is_banned'] = 0;
                $user['banned_until'] = null;
            }
        }

        return [
            'error' => false,
            'id' => $user['id'],
            'role' => (int)$user['role'],
            'user_rank' => (int)($user['user_rank'] ?? 0),
            'is_banned' => $isBanned,
            'banned_until' => $bannedUntil
        ];
    }
    /**
     * @param int $id ID uživatele
     * @return array data uživatele
     */
    public function getUserById(int $id): array
    {
        $conn = $this->db->getConnection();

        $sql = "SELECT u.*, 
                   c.name AS city_name, 
                   r.name AS region_name 
            FROM users u 
            LEFT JOIN cities c ON u.city_id = c.id 
            LEFT JOIN regions r ON c.region_id = r.id 
            WHERE u.id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);

        return $data ?: [];
    }

    public function getUsernameById(int $id): string
    {
        $conn = $this->db->getConnection();
        $sql = "SELECT username FROM users WHERE id = ? LIMIT 1";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        return $user['username'] ?? 'Neznámý uživatel';
    }

    /**
     * @param string $username uživatelské jméno
     * @return array|null data uživatele nebo null
     */
    public function getUserByUsername(string $username): ?array
    {
        $conn = $this->db->getConnection();
        $sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return $data;
    }
    /**
     * @param array $data POST data z formuláře
     * @param array $errors reference na pole chyb
     * @return int|null ID nového uživatele nebo null při chybě
     */
    public function register(array $data, array &$errors): ?int
    {
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        $first = trim($data['first_name'] ?? '');
        $last = trim($data['last_name'] ?? '');
        $country = trim($data['country_code'] ?? '');
        $region_id = !empty($data['region_id']) ? (int)$data['region_id'] : null;
        $city_id = !empty($data['city_id']) ? (int)$data['city_id'] : null;

        if (strlen($username) < 3) $errors[] = 'Uživatelské jméno je příliš krátké.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Neplatný email.';
        if (strlen($password) < 6) $errors[] = 'Heslo musí mít alespoň 6 znaků.';
        if ($password !== $passwordConfirm) $errors[] = 'Hesla se neshodují.';

        if (empty($errors)) {
            $conn = $this->db->getConnection();
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = 'Uživatel nebo email již existuje.';
            }
            mysqli_stmt_close($stmt);
        }

        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $utc_datetime = gmdate('Y-m-d H:i:s');

            $conn = $this->db->getConnection();
            $sql = "INSERT INTO users (username, email, password, first_name, last_name, country_code, region_id, city_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssssiis', $username, $email, $passwordHash, $first, $last, $country, $region_id, $city_id, $utc_datetime);

            if (mysqli_stmt_execute($stmt)) {
                $newId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                return $newId;
            } else {
                $errors[] = 'DB Error: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
        return null;
    }
    /**
     * @param int $userId ID uživatele ke změně
     * @param array $data nová data
     * @param bool $isAdmin zda akci provádí admin
     * @param array $errors reference na pole chyb
     * @return bool úspěch
     */
    public function updateUser(int $userId, array $data, bool $isAdmin, array &$errors): bool
    {
        $conn = $this->db->getConnection();

        $email = trim($data['email'] ?? '');
        $first = trim($data['first_name'] ?? '');
        $last = trim($data['last_name'] ?? '');
        $country = trim($data['country_code'] ?? '');
        $region_id = !empty($data['region_id']) ? (int)$data['region_id'] : null;
        $city_id = !empty($data['city_id']) ? (int)$data['city_id'] : null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Neplatný email.';
            return false;
        }

        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($stmt, 'si', $email, $userId);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_fetch($stmt)) {
            $errors[] = 'Tento email již používá jiný uživatel.';
            mysqli_stmt_close($stmt);
            return false;
        }
        mysqli_stmt_close($stmt);

        $sql = "UPDATE users SET email=?, first_name=?, last_name=?, country_code=?, region_id=?, city_id=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssiii', $email, $first, $last, $country, $region_id, $city_id, $userId);
        $updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                $errors[] = 'Heslo musí mít min. 6 znaků.';
            } else {
                $hash = password_hash($data['password'], PASSWORD_BCRYPT);
                $stmtP = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
                mysqli_stmt_bind_param($stmtP, 'si', $hash, $userId);
                mysqli_stmt_execute($stmtP);
                mysqli_stmt_close($stmtP);
            }
        }

        if ($isAdmin) {
            $rank = isset($data['rank']) ? (int)$data['rank'] : null;
            if ($rank !== null) {
                $stmtR = mysqli_prepare($conn, "UPDATE users SET rank=?, role=? WHERE id=?");
                mysqli_stmt_bind_param($stmtR, 'iii', $rank, $rank, $userId);
                mysqli_stmt_execute($stmtR);
                mysqli_stmt_close($stmtR);
            }

            if (array_key_exists('banned_until', $data)) {
                $bannedUntil = !empty($data['banned_until']) ? $data['banned_until'] : null;
                $isBanned = $bannedUntil ? 1 : 0;
                $stmtB = mysqli_prepare($conn, "UPDATE users SET is_banned=?, banned_until=? WHERE id=?");
                mysqli_stmt_bind_param($stmtB, 'isi', $isBanned, $bannedUntil, $userId);
                mysqli_stmt_execute($stmtB);
                mysqli_stmt_close($stmtB);
            }
        }

        return $updated && empty($errors);
    }
    /**
     * @param int $userId ID mazaného uživatele
     * @param int $performedByUserId ID admina provádějícího akci
     * @return bool úspěch
     */
    public function deleteUser(int $userId, int $performedByUserId): bool
    {
        $conn = $this->db->getConnection();
        if ($userId === $performedByUserId) return false;

        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }
    /**
     * @param int $userId ID uživatele k odbanování
     * @param int $performedByUserId ID admina provádějícího akci
     * @return bool úspěch
     */
    public function unbanUser(int $userId, int $performedByUserId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = mysqli_prepare($conn, "UPDATE users SET is_banned=0, banned_until=NULL WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }
    /**
     * @param int $userId ID uživatele
     * @return int nový index role
     */
    public function refreshUserRole(int $userId): int
    {
        $conn = $this->db->getConnection();

        $res = mysqli_query($conn, "SELECT total_score FROM users WHERE id = $userId");
        $userRow = mysqli_fetch_assoc($res);

        if (!$userRow) return 0;

        // Level se počítá ze skóre z aktivity, ne z bonusového XP
        $newRole = Settings::calculateLevelFromScore((int)$userRow['total_score']);

        $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $newRole, $userId);
        mysqli_stmt_execute($stmt);

        return $newRole;
    }
    /**
     * @param array $user data uživatele z DB
     * @return string URL avataru
     */
    public function getAvatar(array $user): string
    {
        $defaultAvatar = "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=random&color=fff";

        if (!empty($user['profile_image'])) {

            $filePath = __DIR__ . '/../uploads/avatar/' . basename($user['profile_image']);

            if (file_exists($filePath)) {
                return '/src/uploads/avatar/' . rawurlencode($user['profile_image']);
            }
        }

        return $defaultAvatar;
    }

    public function getUserAvatarTicket(string $username): string
    {
        
        $usernameEscaped = mysqli_real_escape_string($this->db->getConnection(), $username);
        $query = "SELECT profile_image FROM users WHERE username = '$usernameEscaped' LIMIT 1";
        $result = mysqli_query($this->db->getConnection(), $query);
        $userData = mysqli_fetch_assoc($result);

        $defaultAvatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random&color=fff";

        if ($userData && !empty($userData['profile_image'])) {
            $filePath = __DIR__ . '/../uploads/avatar/' . basename($userData['profile_image']);
            if (file_exists($filePath)) {
                return '/src/uploads/avatar/' . rawurlencode($userData['profile_image']);
            }
        }

        return $defaultAvatar;
    }

    public function getAvatarTicketByAnswer(string $username): string
    {
        $conn = $this->db->getConnection(); 
        
        $usernameSafe = mysqli_real_escape_string($conn, $username);
        $sql = "SELECT profile_image FROM users WHERE username = '$usernameSafe' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);

        $defaultAvatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=random&color=fff";

        if ($row && !empty($row['profile_image'])) {
            $filePath = __DIR__ . '/../uploads/avatar/' . basename($row['profile_image']);
            if (file_exists($filePath)) {
                return '/src/uploads/avatar/' . rawurlencode($row['profile_image']);
            }
        }

        return $defaultAvatar;
    }

    /**
     * @param int $userId ID uživatele
     * @param array $file $_FILES pole
     * @return array výsledek (success, error)
     */
    public function uploadAvatar(int $userId, array $file): array
    {
        $uploadDir = __DIR__ . '/../uploads/avatar/';
        $defaultAvatar = '1.png';

        if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'upload_error'];
        if ($file['size'] > 2 * 1024 * 1024) return ['success' => false, 'error' => 'too_large'];

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detectedType, $allowedTypes)) return ['success' => false, 'error' => 'invalid_type'];

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = "avatar_" . $userId . "_" . time() . "." . $extension;
        $targetPath = $uploadDir . $newFileName;

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $oldImg = $stmt->get_result()->fetch_assoc()['profile_image'] ?? null;

        if ($oldImg && $oldImg !== $defaultAvatar && file_exists($uploadDir . $oldImg)) {
            unlink($uploadDir . $oldImg);
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $update = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $update->bind_param("si", $newFileName, $userId);
            $update->execute();
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'move_failed'];
    }

    /**
     * @param int $userId ID uživatele
     * @param string $oldPassword staré heslo
     * @param string $newPassword nové heslo
     * @return array výsledek (success, error)
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Původní heslo není správné.'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Nové heslo musí mít alespoň 6 znaků.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newHash, $userId);

        if ($updateStmt->execute()) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Chyba při ukládání do databáze.'];
    }
}
