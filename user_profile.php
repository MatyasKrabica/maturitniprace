<?php
// Profil uživatele – heslo, avatar, Google Fit propojení
session_start();
require_once 'src/php/ban_check.php';
require_once 'src/php/Database.php';
require_once 'src/classes/UserManager.php';
require_once 'src/php/settings.php';
require_once 'src/classes/ActionLogManager.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();
$userManager = new UserManager($db);
$conn = $db->getConnection();
$user_id = (int)$_SESSION['user_id'];

$logM = new ActionLogManager($db);
$ip = Settings::getUserIp();

$msg = "";
$msg_type = ""; 

// Změna hesla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if ($newPass !== $confirmPass) {
        $msg = "Nová hesla se neshodují.";
        $msg_type = "error";
    } else {
        $result = $userManager->changePassword($user_id, $oldPass, $newPass);
        if ($result['success']) {
            $msg = "Heslo bylo úspěšně změněno.";
            $msg_type = "success";
            $logM->logAction(0, 'CHANGE_PASSWORD_OK', "Změna hesla: (ID:".$_SESSION['user_id']." ). IP: $ip");
        } else {
            $msg = $result['error'];
            $msg_type = "error";
            $logM->logAction(0, 'CHANGE_PASSWORD_ERROR', "Byl pokus o změnu hesla: (ID:".$_SESSION['user_id']." ). IP: $ip ale proběhl neuspěšně");
        }
    }
}

// Načtení profilu uživatele z DB
$sql = "SELECT u.*, r.name as region_name, c.name as city_name 
        FROM users u 
        LEFT JOIN regions r ON u.region_id = r.id
        LEFT JOIN cities c ON u.city_id = c.id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) die("Chyba: Uživatel nenalezen.");

$avatarUrl = $userManager->getAvatar($user);
$isAdmin = (isset($_SESSION['user_rank']) && $_SESSION['user_rank'] > 1);
$is_google_linked = !empty($user['google_access_token']);
?>
<!doctype html>
<html lang="cs">

<head>
    <meta charset="utf-8">
    <title>Profil | Maturitní práce</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        body { background-color: #f3f4f6; color: #1f2937; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }

        .dark body { background-color: #0a0a0c; color: #d1d5db; }
        .dark .glass { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>

<body class="antialiased min-h-screen pb-12 transition-colors duration-300">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-500/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
    </div>

    <?php include_once "src/templates/nav.php"; ?>

    <main class="max-w-4xl mx-auto px-6">

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-2xl text-sm italic border <?= $msg_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-500' : 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-500' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="space-y-8">

            <div class="glass rounded-[3rem] p-10 text-center relative overflow-hidden shadow-sm dark:shadow-none">
                <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-yellow-500/10 to-transparent"></div>

                <div class="relative inline-block mb-6 mt-4">
                    <img src="<?= $avatarUrl ?>"
                        class="w-32 h-32 mx-auto rounded-full border-4 border-white dark:border-white/5 p-1 object-cover shadow-2xl shadow-yellow-500/10"
                        alt="Avatar">

                    <label for="avatar_upload" class="absolute bottom-1 right-1 w-9 h-9 bg-yellow-500 rounded-full flex items-center justify-center cursor-pointer hover:scale-110 transition shadow-lg border-4 border-white dark:border-[#0a0a0c]">
                        <svg class="w-4 h-4 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </label>
                </div>

                <h2 class="text-3xl font-black text-gray-900 dark:text-white italic tracking-tight mb-1"><?= htmlspecialchars($user['username']) ?></h2>
                <div class="inline-block px-4 py-1 rounded-full bg-gray-100 dark:bg-white/5 text-yellow-600 dark:text-yellow-500 text-[10px] font-bold uppercase tracking-[0.2em] mb-8">
                    <?= Settings::getRankName($user['user_rank']) ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl mx-auto text-left">
                    <div class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl border border-gray-200 dark:border-white/5">
                        <span class="block text-[9px] uppercase font-bold text-gray-400 dark:text-gray-500 tracking-widest mb-1 italic">E-mailová adresa</span>
                        <span class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl border border-gray-200 dark:border-white/5">
                        <span class="block text-[9px] uppercase font-bold text-gray-400 dark:text-gray-500 tracking-widest mb-1 italic">Lokalita</span>
                        <span class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($user['city_name'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <div class="glass rounded-[2.5rem] p-8 border border-gray-200 dark:border-white/5 shadow-sm dark:shadow-none">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1 italic uppercase tracking-tight">Google Fit Propojení</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Synchronizace kroků skrz Google Fit API</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <?php if ($is_google_linked): ?>
                            <a href="https://matyaskrabica.cz/src/fit/fit-auth.php" class="bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 text-gray-800 dark:text-white px-5 py-2.5 rounded-xl text-[10px] font-bold transition border border-gray-200 dark:border-white/10 uppercase tracking-widest">
                                Znovu propojit
                            </a>
                            <div class="flex items-center gap-2 bg-emerald-500/10 text-emerald-600 dark:text-emerald-500 px-4 py-2.5 rounded-xl border border-emerald-500/20 text-xs font-bold">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                AKTIVNÍ
                            </div>
                        <?php else: ?>
                            <a href="https://matyaskrabica.cz/src/fit/fit-auth.php" class="bg-gray-900 dark:bg-white text-white dark:text-black px-8 py-3 rounded-xl text-xs font-black transition hover:bg-yellow-500 uppercase tracking-widest shadow-xl">
                                Propojit účet
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="glass rounded-[2.5rem] p-8 border border-gray-200 dark:border-white/5 shadow-sm dark:shadow-none">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2 italic uppercase tracking-tighter">
                    <span class="w-1.5 h-6 bg-yellow-500 rounded-full"></span>
                    Zabezpečení účtu
                </h3>

                <form action="" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-1">
                            <label class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-500 ml-1 italic tracking-widest">Současné heslo</label>
                            <input type="password" name="old_password" required placeholder="••••••••"
                                class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 p-4 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:border-yellow-500/50 transition">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-500 ml-1 italic tracking-widest">Nové heslo</label>
                            <input type="password" name="new_password" required placeholder="••••••••"
                                class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 p-4 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:border-yellow-500/50 transition">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-500 ml-1 italic tracking-widest">Potvrzení</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••"
                                class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 p-4 rounded-xl text-gray-900 dark:text-white focus:outline-none focus:border-yellow-500/50 transition">
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" name="change_password" class="bg-gray-900 dark:bg-white text-white dark:text-black px-8 py-3 rounded-xl text-xs font-black transition hover:bg-yellow-500 uppercase tracking-widest shadow-lg active:scale-95">
                            Aktualizovat heslo
                        </button>
                    </div>
                </form>
            </div>

            <form id="avatarForm" action="src/php/upload_avatar.php" method="POST" enctype="multipart/form-data" class="hidden">
                <input type="file" id="avatar_upload" name="avatar" onchange="document.getElementById('avatarForm').submit()" accept="image/*">
            </form>

        </div>
    </main>

    <footer class="mt-20 text-center pb-10">
        <div class="h-[1px] bg-gray-200 dark:bg-white/5 w-64 mx-auto mb-8"></div>
        <p class="text-[10px] font-bold uppercase tracking-[0.5em] text-gray-400 dark:text-gray-700">Maturitní práce &copy; 2026</p>
    </footer>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const moonIcon = document.getElementById('moon-icon');
            const sunIcon = document.getElementById('sun-icon');

            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                if(moonIcon) moonIcon.classList.add('hidden');
                if(sunIcon) sunIcon.classList.remove('hidden');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                if(sunIcon) sunIcon.classList.add('hidden');
                if(moonIcon) moonIcon.classList.remove('hidden');
            }
        }

        (function() {
            const theme = localStorage.getItem('theme');
            const html = document.documentElement;
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                html.classList.add('dark');
                document.getElementById('sun-icon')?.classList.add('hidden');
                document.getElementById('moon-icon')?.classList.remove('hidden');
            } else {
                html.classList.remove('dark');
                document.getElementById('moon-icon')?.classList.add('hidden');
                document.getElementById('sun-icon')?.classList.remove('hidden');
            }
        })();
    </script>
</body>
</html>