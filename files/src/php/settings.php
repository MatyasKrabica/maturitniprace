<?php
// Nastavení aplikace: ranky, XP prahy, pomocné metody
class Settings
{
    // Ranky uživatelů podle oprávnění
    const RANKS = [
        0 => 'Uživatel',
        1 => 'Tvůrce výzev',
        2 => 'Podpora',
        3 => 'Administrátor',
        4 => 'Majitel'
    ];

    // Prahy skóre pro jednotlivé levely (skóre = vážené kroky z aktivity)
    // Chůze: 10k kroků = 100 bodů/den, Běh: 10k = 150/den, Turistika: 10k = 120/den
    const SCORE_THRESHOLDS = [
        0  => 0,
        1  => 100,
        2  => 350,
        3  => 800,
        4  => 1800,
        5  => 3500,
        6  => 7000,
        7  => 14000,
        8  => 25000,
        9  => 40000,
        10 => 60000,
    ];

    // XP prahy (zachováno pro zpětnou kompatibilitu s bonusovým XP za výzvy)
    const ROLE_XP = [
        0 => 0,
        1 => 100,
        2 => 250,
        3 => 500,
        4 => 1000,
        5 => 2000,
        6 => 3500,
        7 => 5000,
        8 => 7500,
        9 => 10000,
        10 => 15000
    ];

    // Názvy levelů (1–11)
    const ROLES = [
        0 => '1',
        1 => '2',
        2 => '3',
        3 => '4',
        4 => '5',
        5 => '6',
        6 => '7',
        7 => '8',
        8 => '9',
        9 => '10',
        10 => '11'
    ];

    // Minimální rank pro přístup do admin/support sekce
    const MIN_RANK_FOR_SUPPORT = 2;

    // Typy aktivit Google Fit: ID => locale klíč
    const ACTIVITY_TYPES = [
        7  => 'activity_type_walking',
        8  => 'activity_type_running',
        48 => 'activity_type_hiking',
    ];

    public static function getActivityName(int $typeId): string
    {
        $key = self::ACTIVITY_TYPES[$typeId] ?? null;
        if (!$key) return (string)$typeId;
        return t($key, $key);
    }

    public static function getRankName(int $rank): string
    {
        return self::RANKS[$rank] ?? t('unknown_rank', 'Neznámý rank');
    }

    public static function getRoleName(int $role): string
    {
        return self::ROLES[$role] ?? t('unknown_level', 'Neznámý level');
    }

    // Vrátí index levelu podle celkového skóre z aktivity
    public static function calculateLevelFromScore(int $score): int
    {
        $level = 0;
        foreach (self::SCORE_THRESHOLDS as $index => $threshold) {
            if ($score >= $threshold) $level = $index;
            else break;
        }
        return $level;
    }

    // Zachováno pro zpětnou kompatibilitu (používá XP prahy)
    public static function calculateRoleIndex(int $xp): int
    {
        $currentRole = 0;
        foreach (self::ROLE_XP as $index => $requiredXp) {
            if ($xp >= $requiredXp) {
                $currentRole = $index;
            } else {
                break;
            }
        }
        return $currentRole;
    }

    public static function getUserIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
}



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_rank'])) {
    $_SESSION['user_rank'] = 0;
}

$_SESSION["is_admin"] = ($_SESSION['user_rank'] >= Settings::MIN_RANK_FOR_SUPPORT);
