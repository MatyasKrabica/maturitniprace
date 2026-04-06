<?php
// Dashboard – přehled výzev a aktivity uživatele
session_start();

require_once 'src/php/ban_check.php';
require_once 'src/php/Database.php';
require_once 'src/classes/UserManager.php';
require_once 'src/classes/ChallengeManager.php';
require_once 'src/php/settings.php';
require_once 'src/php/locales.php';

$locale = getLocale();

date_default_timezone_set('Europe/Prague');

// Zablokovaný uživatel – zobrazit banovou stránku
if (isset($_SESSION['is_banned']) && $_SESSION['is_banned'] == 1) {
?>
    <!doctype html>
    <html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">

    <head>
        <meta charset="utf-8">
        <title>Účet pozastaven</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { darkMode: 'class' }
        </script>
    </head>

        <body class="bg-gray-100 dark:bg-[#0a0a0c] text-gray-900 dark:text-gray-200 flex items-center justify-center h-screen m-0 p-4 transition-colors duration-300">
        <div class="bg-white dark:bg-white/5 backdrop-blur-xl p-10 rounded-3xl border border-red-500/30 text-center max-w-md w-full shadow-2xl">
            <h1 class="text-3xl font-black text-red-500 mb-4 tracking-tighter italic"><?= t('dashboard_banned_title', 'ÚČET ZABLOKOVÁN') ?></h1>
            <p class="mb-8 text-gray-600 dark:text-gray-400"><?= t('dashboard_banned_text', 'Byl Vám omezen přístup k systému. Pokud si myslíte, že jde o chybu, kontaktujte naši podporu.') ?></p>
            <div class="flex flex-col gap-3">
                <a href="ticket.php" class="bg-yellow-500 hover:bg-yellow-600 text-black py-3 rounded-xl font-black transition uppercase text-sm tracking-widest"><?= t('dashboard_banned_ticket', 'Řešit přes Ticket') ?></a>
                <a href="logout.php" class="bg-gray-200 dark:bg-white/10 hover:bg-gray-300 dark:hover:bg-white/20 text-black dark:text-white py-3 rounded-xl font-bold transition text-sm"><?= t('dashboard_banned_logout', 'Odhlásit se') ?></a>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

$db = new Database();
$userManager = new UserManager($db);
$chM = new ChallengeManager($db);

$user = null;
$activeQuest = null;

// Načtení přihlášeného uživatele a jeho aktivní výzvy
if (!empty($_SESSION['user_id'])) {
    $user = $userManager->getUserById((int)$_SESSION['user_id']);
    $avatarUrl = $userManager->getAvatar($user);

    if ($user) {
        $_SESSION['user_rank'] = (int)($user['user_rank'] ?? 0);
        $activeQuest = $chM->getActiveChallenge((int)$_SESSION['user_id']);
    } else {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="utf-8">
    <title>Maturitní práce | Dashboard</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body { background-color: #f3f4f6; color: #1f2937; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); }

        .dark body { background-color: #0a0a0c; color: #d1d5db; }
        .dark .glass { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); }

        .quest-gradient {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.2) 100%);
        }

        .mask-gradient {
            mask-image: linear-gradient(to right, transparent, black);
        }
    </style>
</head>

<body class="antialiased overflow-x-hidden min-h-screen pb-12 transition-colors duration-300">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/10 blur-[120px] rounded-full"></div>
    </div>

    <?php include_once "src/templates/nav.php"; ?>

    <main class="max-w-7xl mx-auto px-6">
        <?php if ($user): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                <div class="lg:col-span-4 space-y-6">
                    <div class="glass rounded-[2.5rem] p-8 text-center relative overflow-hidden group shadow-sm dark:shadow-none">
                        <div class="absolute inset-0 bg-gradient-to-b from-yellow-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>

                        <div class="relative inline-block mb-6">
                            <img src="<?= $avatarUrl ?>" class="w-32 h-32 mx-auto rounded-full border-4 border-white/20 dark:border-white/5 p-1 object-cover shadow-2xl shadow-yellow-500/10" alt="Avatar">
                            <div class="absolute bottom-1 right-1 w-6 h-6 bg-green-500 border-4 border-white dark:border-[#0a0a0c] rounded-full"></div>
                        </div>

                        <h2 class="text-2xl font-black text-gray-900 dark:text-white italic tracking-tight mb-1"><?= htmlspecialchars($user['username']) ?></h2>
                        <p class="text-yellow-600 dark:text-yellow-500 font-bold text-[10px] uppercase tracking-[0.3em] mb-8"><?= Settings::getRankName($_SESSION['user_rank']) ?></p>

                        <div class="grid grid-cols-2 gap-px bg-gray-200 dark:bg-white/5 rounded-2xl overflow-hidden border border-gray-200 dark:border-white/5">
                            <div class="bg-white dark:bg-[#121214] p-4 text-center">
                                <p class="text-[9px] uppercase text-gray-500 font-bold tracking-widest mb-1"><?= t('score_label', 'Skóre') ?></p>
                                <p class="text-xl font-black text-gray-900 dark:text-white italic">★ <?= number_format((int)($user['total_score'] ?? 0), 0, ',', ' ') ?></p>
                                <p class="text-[9px] text-gray-400 mt-1">⚡ <?= (int)($user['xp'] ?? 0) ?> XP</p>
                            </div>
                            <div class="bg-white dark:bg-[#121214] p-4 text-center">
                                <p class="text-[9px] uppercase text-gray-500 font-bold tracking-widest mb-1"><?= t('dashboard_id', 'ID') ?></p>
                                <p class="text-xl font-black text-gray-900 dark:text-white italic"><?= htmlspecialchars($_SESSION['user_id']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="glass rounded-3xl p-7 space-y-5 text-sm shadow-sm dark:shadow-none">
                        <div class="flex justify-between items-center border-b border-gray-100 dark:border-white/5 pb-4">
                            <span class="text-gray-400 dark:text-gray-500 font-bold text-[10px] uppercase tracking-wider italic"><?= t('dashboard_identity', 'Identita') ?></span>
                            <span class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                        </div>
                        <div class="flex justify-between items-center border-b border-gray-100 dark:border-white/5 pb-4">
                            <span class="text-gray-400 dark:text-gray-500 font-bold text-[10px] uppercase tracking-wider italic"><?= t('dashboard_city', 'Město') ?></span>
                            <span class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($user['city_name'] ?? 'Neznámé') ?></span>
                        </div>
                                                <div class="flex justify-between items-center border-b border-gray-100 dark:border-white/5 pb-4">
                            <span class="text-gray-400 dark:text-gray-500 font-bold text-[10px] uppercase tracking-wider italic"><?= t('dashboard_region', 'Kraj') ?></span>
                            <span class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($user['region_name'] ?? 'Neznámé') ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 dark:text-gray-500 font-bold text-[10px] uppercase tracking-wider italic"><?= t('dashboard_active_since', 'Aktivní od') ?></span>
                            <span class="text-gray-600 dark:text-gray-400 font-medium"><?= date('d. m. Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-8">

                    <?php if ($activeQuest): ?>
                        <div class="relative overflow-hidden rounded-[3rem] quest-gradient border border-emerald-500/20 p-8 md:p-14 shadow-lg">
                            <div class="absolute right-0 top-0 h-full w-1/2 opacity-15 pointer-events-none">
                                <img src="https://static.vecteezy.com/system/resources/thumbnails/003/607/543/small/businessman-standing-on-cliff-s-edge-and-looking-at-the-mountain-business-concept-challenge-and-the-goal-vector.jpg" class="object-cover h-full w-full mask-gradient" alt="Background">
                            </div>

                            <div class="relative z-10 max-w-xl">
                                <div class="flex items-center gap-3 mb-6">
                                    <span class="bg-emerald-500 text-black text-[10px] font-black px-4 py-1.5 rounded-full uppercase tracking-tighter"><?= t('dashboard_active_quest_badge', 'Probíhající výzva') ?></span>
                                    <span class="text-gray-600 dark:text-white/40 text-[10px] uppercase font-bold tracking-widest">ID-<?= $activeQuest['uc_id'] ?? '?' ?></span>
                                </div>

                                <h1 class="text-4xl md:text-6xl font-black text-gray-900 dark:text-white mb-6 leading-tight lowercase italic tracking-tighter">
                                    <?= htmlspecialchars($activeQuest['title']) ?>
                                </h1>

                                <p class="text-gray-600 dark:text-gray-400 text-lg leading-relaxed mb-10 line-clamp-3 font-light italic">
                                    "<?= htmlspecialchars($activeQuest['description'] ?? '') ?>"
                                </p>

                                <div class="flex flex-wrap gap-10 mb-10">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400 tracking-widest mb-1"><?= t('dashboard_quest_goal', 'Cíl mise') ?></span>
                                        <span class="text-3xl font-black text-gray-900 dark:text-white italic leading-none">
                                            <?= number_format($activeQuest['goal_steps'], 0, ',', ' ') ?>
                                            <small class="text-xs uppercase text-gray-500 not-italic ml-1"><?= t('steps', 'kroků') ?></small>
                                        </span>
                                    </div>
                                    <div class="flex flex-col border-l border-gray-300 dark:border-white/10 pl-10">
                                        <span class="text-[10px] uppercase font-bold text-yellow-600 dark:text-yellow-500 tracking-widest mb-1"><?= t('dashboard_quest_time_left', 'Zbývající čas') ?></span>
                                        <span class="text-3xl font-black text-gray-900 dark:text-white italic leading-none">
                                            <?php
                                            $diff = (new DateTime())->diff(new DateTime($activeQuest['expires_at']));
                                            echo $diff->h . "h " . $diff->i . "m";
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <a href="challenge.php" class="bg-gray-900 dark:bg-white text-white dark:text-black px-10 py-5 rounded-[1.5rem] font-black hover:bg-yellow-500 transition-all transform hover:scale-105 shadow-xl uppercase text-sm tracking-widest">
                                        <?= t('dashboard_quest_go', 'Přejít k misi') ?>
                                    </a>
                                    <span class="text-gray-500 text-[10px] uppercase font-bold tracking-widest"><?= t('dashboard_quest_end', 'Konec:') ?> <?= date('H:i d/m', strtotime($activeQuest['expires_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="glass rounded-[3rem] p-16 text-center border-dashed border-gray-300 dark:border-white/10 group cursor-pointer hover:border-yellow-500/40 transition-all">
                            <div class="w-24 h-24 bg-gray-100 dark:bg-white/5 rounded-[2rem] flex items-center justify-center mx-auto mb-8 group-hover:rotate-6 transition-transform">
                                <a href="challenge.php"><svg class="w-12 h-12 text-gray-400 dark:text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg></a>
                            </div>
                            <h3 class="text-3xl font-black text-gray-900 dark:text-white mb-3 italic"><?= t('dashboard_no_quest_title', 'Žádné aktivní úkoly') ?></h3>
                            <p class="text-gray-500 mb-10 max-w-sm mx-auto font-light leading-relaxed"><?= t('dashboard_no_quest_text', 'Aktivuj si výzvu a začni sbírat zkušenosti.') ?></p>
                            <a href="challenge.php" class="bg-gray-900 dark:bg-white text-white dark:text-black px-10 py-5 rounded-3xl font-black hover:bg-yellow-500 transition-all inline-block shadow-2xl uppercase text-sm tracking-widest">
                                <?= t('dashboard_no_quest_btn', 'Vybrat nový quest') ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="challenge.php" class="glass p-7 rounded-[2rem] hover:bg-white dark:hover:bg-white/5 transition-all group border border-transparent hover:border-yellow-500/20 shadow-sm dark:shadow-none">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform origin-left">🏰</div>
                            <span class="font-black text-gray-900 dark:text-white block uppercase text-[10px] tracking-widest mb-1"><?= t('dashboard_nav_challenges', 'Přehled') ?></span>
                            <span class="text-gray-500 text-xs"><?= t('dashboard_nav_challenges_sub', 'Všechny Výzvy') ?></span>
                        </a>
                        <a href="leaderboard.php" class="glass p-7 rounded-[2rem] hover:bg-white dark:hover:bg-white/5 transition-all group border border-transparent hover:border-blue-500/20 shadow-sm dark:shadow-none">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform origin-left">🏆</div>
                            <span class="font-black text-gray-900 dark:text-white block uppercase text-[10px] tracking-widest mb-1"><?= t('dashboard_nav_leaderboard', 'Žebříček') ?></span>
                            <span class="text-gray-500 text-xs"><?= t('dashboard_nav_leaderboard_sub', 'Žebříček hráčů') ?></span>
                        </a>
                        <a href="user_profile.php" class="glass p-7 rounded-[2rem] hover:bg-white dark:hover:bg-white/5 transition-all group border border-transparent hover:border-purple-500/20 shadow-sm dark:shadow-none">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform origin-left">👤</div>
                            <span class="font-black text-gray-900 dark:text-white block uppercase text-[10px] tracking-widest mb-1"><?= t('dashboard_nav_profile', 'Postava') ?></span>
                            <span class="text-gray-500 text-xs"><?= t('dashboard_nav_profile_sub', 'Můj profil') ?></span>
                        </a>
                        <a href="ticket.php" class="glass p-7 rounded-[2rem] hover:bg-white dark:hover:bg-white/5 transition-all group border border-transparent hover:border-red-500/20 shadow-sm dark:shadow-none">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform origin-left">🎫</div>
                            <span class="font-black text-gray-900 dark:text-white block uppercase text-[10px] tracking-widest mb-1"><?= t('dashboard_nav_support', 'Podpora') ?></span>
                            <span class="text-gray-500 text-xs"><?= t('dashboard_nav_support_sub', 'Pomoc a dotazy') ?></span>
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="glass rounded-[3rem] p-20 text-center max-w-2xl mx-auto shadow-xl">
                <h2 class="text-4xl font-black text-gray-900 dark:text-white mb-6 italic tracking-tight uppercase"><?= t('dashboard_login_title', 'Přihlásit se') ?></h2>
                <p class="text-gray-500 mb-10 leading-relaxed text-lg"><?= t('dashboard_login_text', 'Pro vstup do svého herního profilu se prosím nejdříve přihlaste.') ?></p>
                <a href="login.php" class="bg-yellow-500 hover:bg-yellow-600 text-black px-12 py-5 rounded-2xl font-black transition-all shadow-xl shadow-yellow-500/20 uppercase tracking-widest text-sm inline-block"><?= t('dashboard_login_btn', 'Přihlásit se k účtu') ?></a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="max-w-7xl mx-auto px-6 mt-20 text-center">
        <div class="h-[1px] bg-gray-300 dark:bg-white/5 w-full mb-8"></div>
        <p class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-700 tracking-[0.4em]"><?= t('footer_rights', '© 2026 Maturitní práce • Všechna práva vyhrazena') ?></p>
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