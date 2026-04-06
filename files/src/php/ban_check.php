<?php
// Kontrola banu a stavu uživatele (includováno na chráněných stránkách)

// Inicializace DB a manageru pokud ještě nejsou k dispozici
if (!isset($db)) {
    require_once 'Database.php';
    $db = new Database();
}
if (!isset($userManager)) {
    require_once __DIR__ . '/../classes/UserManager.php';
    $userManager = new UserManager($db); 
}
require_once 'settings.php'; 

// Zjištění přihlášeného uživatele
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$isUserLoggedIn = $currentUserId > 0;

if ($isUserLoggedIn) {
    // Načtení dat uživatele a aktualizace session
    $user = $user ?? $userManager->getUserById($currentUserId);
    
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['role'] = (int)($user['role'] ?? 0); 
    $_SESSION['user_rank'] = (int)($user['user_rank'] ?? 0);
    $_SESSION['is_banned'] = (int)($user['is_banned'] ?? 0);
    $_SESSION['banned_until'] = $user['banned_until'];
    
    $_SESSION['user_rank'] = $_SESSION['user_rank']; 
    $_SESSION["is_admin"] = ($_SESSION['user_rank'] >= Settings::MIN_RANK_FOR_SUPPORT);


    $isBanned = $_SESSION['is_banned'] == 1;

    if ($isBanned) {
        // Kontrola zda ban ještě platí nebo už vypršel
        $bannedUntil = $_SESSION['banned_until'];
        $banIsExpired = $bannedUntil && strtotime($bannedUntil) < time();

        if ($banIsExpired) {
            $userManager->unbanUser($currentUserId, 0); 
            
            $_SESSION['is_banned'] = 0;
            $_SESSION['banned_until'] = null;
            $isBanned = false; 
        }
        
        if ($isBanned) {
            // Zbannovaný uživatel smí jen na tyto stránky
            $currentPage = basename($_SERVER['PHP_SELF']);
            
            $allowedPagesForBannedUser = [
                'dashboard.php',
                'ticket.php',
                'logout.php',
                'login.php',
            ];
            
            if (!in_array($currentPage, $allowedPagesForBannedUser)) {
                header('Location: dashboard.php'); 
                exit;
            }
        }
    }
}