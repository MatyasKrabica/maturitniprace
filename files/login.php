<?php
// Přihlášení uživatele
session_start();
require_once 'src/php/Database.php';
require_once 'src/classes/UserManager.php';
require_once 'src/php/settings.php';
require_once 'src/classes/ActionLogManager.php';
require_once 'src/php/locales.php';

date_default_timezone_set('Europe/Prague');

$locale = getLocale();

$db = new Database();
$userManager = new UserManager($db);
$logM = new ActionLogManager($db);

$errors = [];
$old_username = '';
$ip = Settings::getUserIp(); 

// Zpracování přihlašovacího formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $old_username = $username;

    if (empty($username) || empty($password)) {
        $errors[] = t('error_fill_credentials', 'Vyplňte uživatelské jméno a heslo.');
    } else {
        $result = $userManager->login($username, $password);

        // Neúspěšné přihlášení
        if ($result['error']) {
            $errors[] = $result['message'];
            $logM->logAction(0, 'LOGIN_FAILED', "Neúspěšný pokus o přihlášení (jméno: $username). IP: $ip");
        } else {
            // Úspěšné přihlášení – uložení do session a přesměrování
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['user_role'] = $result['user_role'];
            $_SESSION['user_rank'] = $result['user_rank']; 
            $_SESSION['is_banned'] = $result['is_banned'];
            $_SESSION['banned_until'] = $result['banned_until'];
            $_SESSION["is_admin"] = ($_SESSION['user_rank'] >= Settings::MIN_RANK_FOR_SUPPORT);

            $logM->logAction((int)$result['id'], 'USER_LOGIN', "Uživatel se úspěšně přihlásil. IP: $ip");
            
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <title><?= t('login_title', 'Přihlášení') ?> | <?= t('nav_home', 'Systém') ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
</head>

<body class="bg-gray-100 dark:bg-[#0a0a0c] text-gray-900 dark:text-gray-200 flex items-center justify-center min-h-screen m-0 p-4 transition-colors duration-300">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"><?= t('login_welcome_back', 'Vítejte zpět') ?></h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2"><?= t('login_subtitle_main', 'Přihlaste se ke svému účtu') ?></p>
        </div>

        <div class="bg-white dark:bg-white/5 backdrop-blur-xl p-8 rounded-3xl border border-gray-200 dark:border-white/10 shadow-xl">
            
            <?php if (!empty($errors)): ?>
                <div class="mb-6 space-y-2">
                    <?php foreach ($errors as $error): ?>
                        <div class="bg-red-500/10 border border-red-500/20 text-red-500 px-4 py-3 rounded-xl text-sm flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300"><?= t('login_username', 'Uživatelské jméno') ?></label>
                    <input type="text" name="username" 
                           value="<?= htmlspecialchars($old_username) ?>" 
                           class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-emerald-500 dark:focus:border-emerald-500 outline-none transition-all"
                           required autofocus placeholder="např. admin">
                </div>

                <div>
                    <div class="flex justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= t('login_password', 'Heslo') ?></label>
                    </div>
                    <input type="password" name="password" 
                           class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-emerald-500 dark:focus:border-emerald-500 outline-none transition-all"
                           required placeholder="••••••••">
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl shadow-lg shadow-blue-600/20 transition-all active:scale-[0.98]">
                    <?= t('login_btn', 'Přihlásit se') ?>
                </button>
            </form>

            <div class="mt-8 text-center border-t border-gray-100 dark:border-white/5 pt-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <?= t('login_no_account', 'Ještě nemáte účet?') ?>
                    <a href="register.php" class="text-blue-500 hover:text-blue-600 font-semibold ml-1"><?= t('login_register_link', 'Zaregistrujte se') ?></a>
                </p>
            </div>
        </div>
        
        <div class="text-center mt-6">
             <button onclick="document.documentElement.classList.toggle('dark')" class="text-[10px] text-gray-400 uppercase tracking-widest opacity-50 hover:opacity-100 transition-opacity">
                <?= t('login_toggle_theme', 'Přepnout režim') ?>
             </button>
        </div>
    </div>

    <script>
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</body>
</html>
