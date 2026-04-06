<?php
// Admin panel – správa uživatelů, výzev a logů
session_start();
require_once 'src/php/Database.php';
require_once 'src/classes/UserManager.php';
require_once 'src/classes/ChallengeManager.php';
require_once 'src/classes/ActionLogManager.php';
require_once 'src/php/settings.php';
require_once 'src/php/locales.php';
require_once 'src/classes/AppSettings.php';

$locale = getLocale();

// Kontrola oprávnění (pouze admini)
if (!isset($_SESSION['user_id']) || $_SESSION['user_rank'] < 3) {
    header("Location: dashboard.php"); exit;
}

$db = new Database();
$conn = $db->getConnection();
$uM = new UserManager($db);
$chM = new ChallengeManager($db);
$logM = new ActionLogManager($db);
$appSettings = new AppSettings($conn);

$section = $_GET['section'] ?? 'dashboard';
$msg = "";

// Manuální synchronizace Google Fit
if (isset($_POST['run_manual_sync'])) {
    ob_start(); include 'sync-tokens.php'; ob_get_clean();
    $logM->logAction($_SESSION['user_id'], 'ADMIN_SYNC_FIT', "Manuální sync Google Fit.");
    $msg = "Synchronizace Google Fit dokončena.";
}

// Smazání výzvy
if (isset($_GET['delete_challenge'])) {
    $del_id = (int)$_GET['delete_challenge'];
    $conn->query("DELETE FROM challenges WHERE id = $del_id");
    $logM->logAction($_SESSION['user_id'], 'ADMIN_DELETE_CHALLENGE', "Smazána výzva ID: $del_id");
    $msg = "Výzva smazána.";
}

// Uložení upraveného uživatele
if ($section === 'edit_user' && isset($_POST['update_user_full'])) {
    $id = (int)$_POST['user_id'];
    $sql = "UPDATE users SET email=?, first_name=?, last_name=?, country_code=?, region_id=?, city_id=?, user_rank=?, role=?, xp=?, is_banned=?, banned_until=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $bnd = isset($_POST['is_banned']) ? 1 : 0;
    $btl = !empty($_POST['banned_until']) ? $_POST['banned_until'] : null;
    $reg = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
    $cty = !empty($_POST['city_id']) ? (int)$_POST['city_id'] : null;
    $rank = (int)$_POST['user_rank'];
    $role = (int)$_POST['role'];
    $xp = (int)$_POST['xp'];
    $stmt->bind_param("ssssiiiiissi", $_POST['email'], $_POST['first_name'], $_POST['last_name'], $_POST['country_code'], $reg, $cty, $rank, $role, $xp, $bnd, $btl, $id);
    $stmt->execute();
    $logM->logAction($_SESSION['user_id'], 'ADMIN_UPDATE_USER', "Upraven uživatel ID: $id");
    header("Location: admin.php?section=edit_user&id=$id&saved=1");
    exit;
}

// Uzavření sezóny žebříčku – uložj snapshot Top 3
if (isset($_POST['close_leaderboard'])) {
    $seasonLabel = trim($_POST['season_label'] ?? 'Sezóna ' . date('Y'));
    $sql3 = "SELECT u.id, u.username, u.xp, u.profile_image, u.role, c.name AS city_name
             FROM users u LEFT JOIN cities c ON u.city_id = c.id
             WHERE u.is_banned = 0 ORDER BY u.xp DESC LIMIT 3";
    $snap = $conn->query($sql3)->fetch_all(MYSQLI_ASSOC);
    $appSettings->set('leaderboard_closed',    '1');
    $appSettings->set('leaderboard_closed_at', date('Y-m-d H:i:s'));
    $appSettings->set('leaderboard_season_label', $seasonLabel);
    $appSettings->set('leaderboard_snapshot', json_encode($snap, JSON_UNESCAPED_UNICODE));
    $logM->logAction($_SESSION['user_id'], 'ADMIN_LEADERBOARD_CLOSE', "Uzavřena sezóna: $seasonLabel");
    $msg = "Žebříček uzavřen. Vitězé sezóny \"$seasonLabel\" uloženi.";
}

// Znovuotevření sezóny žebříčku
if (isset($_POST['open_leaderboard'])) {
    $appSettings->set('leaderboard_closed', '0');
    $logM->logAction($_SESSION['user_id'], 'ADMIN_LEADERBOARD_OPEN', "Otevřena nová sezóna žebříčku.");
    $msg = "Nová sezóna žebříčku započala.";
}

// Vytvoření nebo aktualizace výzvy
if ($section === 'challenges' && (isset($_POST['create_challenge']) || isset($_POST['update_challenge']))) {
    $title = $_POST['title']; $desc = $_POST['description'];
    $steps = (int)$_POST['goal_steps']; $reward = (int)$_POST['xp_reward'];
    $penalty = (int)$_POST['xp_penalty']; 
    $limit = (int)$_POST['time_limit_hours']; $actType = (int)$_POST['activity_type'];
    $repeat = isset($_POST['is_repeatable']) ? 1 : 0;
    $cCode = $_POST['country_code'] ?: null; $rId = $_POST['region_id'] ?: null; $cId = $_POST['city_id'] ?: null;

    if (isset($_POST['update_challenge'])) {
        $cid = (int)$_POST['challenge_id'];
        $sql = "UPDATE challenges SET title=?, description=?, goal_steps=?, xp_reward=?, xp_penalty=?, time_limit_hours=?, activity_type=?, country_code=?, region_id=?, city_id=?, is_repeatable=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiiisiii", $title, $desc, $steps, $reward, $penalty, $limit, $actType, $cCode, $rId, $cId, $repeat, $cid);
    } else {
        $sql = "INSERT INTO challenges (title, description, goal_steps, xp_reward, xp_penalty, time_limit_hours, activity_type, country_code, region_id, city_id, is_repeatable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiiisii", $title, $desc, $steps, $reward, $penalty, $limit, $actType, $cCode, $rId, $cId, $repeat);
    }
    $stmt->execute();
    $msg = "Výzva uložena.";
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title>Maturitní Práce Admin-Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body { background-color: #f3f4f6; color: #1f2937; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); }
        .dark body { background-color: #0a0a0c; color: #d1d5db; }
        .dark .glass { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); }
        .dark .custom-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,0.02); }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
        .dark .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .dark select { color-scheme: dark; }
        select option { background-color: #fff; color: #111827; }
        .dark select option { background-color: #1a1a2e; color: #e5e7eb; }
    </style>
</head>
<body class="antialiased min-h-screen transition-colors duration-300">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/10 blur-[120px] rounded-full"></div>
    </div>

    <div class="flex flex-col md:flex-row">
        <aside class="w-full md:w-64 md:fixed md:h-screen glass border-r border-gray-200 dark:border-white/5 p-6 flex flex-col gap-8 z-50 shadow-sm dark:shadow-none transition-colors duration-300">
            <h2 class="text-2xl font-black text-gray-900 dark:text-white italic tracking-tighter">ADMIN<span class="text-yellow-500"> PANEL</span></h2>
            <nav class="flex flex-col gap-2">
                <a href="admin.php?section=dashboard" class="p-4 rounded-xl transition-all <?= $section==='dashboard'?'bg-yellow-500 text-black font-bold shadow-lg shadow-yellow-500/20':'text-gray-500 dark:text-gray-400 hover:bg-white hover:shadow-sm dark:hover:bg-white/5 dark:hover:shadow-none' ?>"><i class="fa-solid fa-chart-line mr-3"></i>Dashboard</a>
                <a href="admin.php?section=users" class="p-4 rounded-xl transition-all <?= ($section==='users'||$section==='edit_user')?'bg-yellow-500 text-black font-bold shadow-lg shadow-yellow-500/20':'text-gray-500 dark:text-gray-400 hover:bg-white hover:shadow-sm dark:hover:bg-white/5 dark:hover:shadow-none' ?>"><i class="fa-solid fa-users mr-3"></i>Uživatelé</a>
                <a href="admin.php?section=challenges" class="p-4 rounded-xl transition-all <?= $section==='challenges'?'bg-yellow-500 text-black font-bold shadow-lg shadow-yellow-500/20':'text-gray-500 dark:text-gray-400 hover:bg-white hover:shadow-sm dark:hover:bg-white/5 dark:hover:shadow-none' ?>"><i class="fa-solid fa-fort-awesome mr-3"></i>Výzvy</a>
                <a href="admin.php?section=leaderboard" class="p-4 rounded-xl transition-all <?= $section==='leaderboard'?'bg-yellow-500 text-black font-bold shadow-lg shadow-yellow-500/20':'text-gray-500 dark:text-gray-400 hover:bg-white hover:shadow-sm dark:hover:bg-white/5 dark:hover:shadow-none' ?>"><i class="fa-solid fa-trophy mr-3"></i>Žebříček</a>
            </nav>
            <div class="mt-auto flex flex-col gap-3">
                <button onclick="toggleTheme()" class="flex items-center justify-center p-3 rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 hover:bg-yellow-500/20 transition-all group font-bold text-gray-600 dark:text-gray-300 text-sm">
                    <span class="dark:hidden group-hover:text-yellow-500 transition-colors"><i class="fa-solid fa-moon mr-2"></i>Tmavý režim</span>
                    <span class="hidden dark:block text-yellow-500"><i class="fa-solid fa-sun mr-2"></i>Světlý režim</span>
                </button>
                <a href="dashboard.php" class="text-red-500 p-4 block text-center font-bold hover:bg-red-500/10 rounded-xl transition-colors"><i class="fa-solid fa-arrow-left mr-2"></i>Zpět do appky</a>
            </div>
        </aside>

        <main class="flex-1 md:ml-64 p-6 md:p-12">
            <?php if ($msg): ?>
                <div class="glass border-l-4 border-yellow-500 p-6 rounded-2xl mb-8 text-gray-900 dark:text-white font-bold shadow-sm dark:shadow-none"><?= $msg ?></div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
                <h1 class="text-4xl font-black text-gray-900 dark:text-white uppercase italic mb-8 tracking-tighter">Dashboard</h1>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="glass p-8 rounded-[2rem] flex flex-col justify-between shadow-sm dark:shadow-none min-h-[400px]">
    <div>
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-gray-500 font-bold uppercase text-xs tracking-widest">Google Fit API</h3>
            <span class="flex h-3 w-3 relative" title="API Online">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
        </div>
        
        <h2 class="text-3xl font-black text-gray-900 dark:text-white uppercase italic tracking-tighter mb-4">
            Manuální<br><span class="text-yellow-500">Synchronizace</span>
        </h2>
        
        <p class="text-gray-600 dark:text-gray-400 text-sm font-light leading-relaxed mb-6">
            Tato akce okamžitě spojí systém se servery Google Fit a vynutí stažení nejnovějších dat o krocích pro všechny aktivní uživatele.
        </p>

        <div class="bg-blue-500/5 dark:bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-8">
            <p class="text-[10px] text-blue-600 dark:text-blue-400 uppercase font-bold tracking-widest flex items-center gap-2">
                <i class="fa-solid fa-circle-info"></i> Info k systému
            </p>
            <p class="text-xs text-gray-700 dark:text-gray-300 mt-2 font-medium">
                Běžná synchronizace je nastavena automaticky přes CRON. Toto tlačítko používejte převážně v případě výpadku nebo zpoždění dat.
            </p>
        </div>
    </div>

    <form method="POST" class="mt-auto">
        <button type="submit" name="run_manual_sync" class="w-full bg-gray-900 dark:bg-white text-white dark:text-black py-5 rounded-2xl font-black uppercase hover:bg-yellow-500 dark:hover:bg-yellow-500 transition-all shadow-xl shadow-gray-900/10 dark:shadow-white/10 text-sm tracking-widest group">
            <i class="fa-solid fa-rotate group-hover:rotate-180 transition-transform duration-500 mr-2"></i>
            Spustit manuální sync
        </button>
    </form>
</div>

                    <div class="glass p-8 rounded-[2rem] lg:col-span-2 shadow-sm dark:shadow-none">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-gray-500 font-bold uppercase text-xs tracking-widest">Systémové Logy</h3>
                            <span class="text-[10px] bg-white dark:bg-white/5 border border-gray-200 dark:border-white/5 px-3 py-1 rounded-full text-gray-500 dark:text-gray-400 font-bold">Posledních 50 akcí</span>
                        </div>
                        
                        <div class="max-h-[400px] overflow-y-auto custom-scroll pr-2">
                            <table class="w-full text-left text-sm">
                                <thead class="sticky top-0 bg-gray-100 dark:bg-[#0a0a0c] text-[10px] uppercase text-gray-500 font-bold z-10 transition-colors duration-300">
                                    <tr><th class="pb-4 pt-2">Uživatel</th><th class="pb-4 pt-2">Akce</th><th class="pb-4 pt-2 text-right">Čas</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                    <?php 
                                    $logs = $conn->query("SELECT al.*, u.username FROM action_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY performed_at DESC LIMIT 50");
                                    while($l = $logs->fetch_assoc()): ?>
                                    <tr class="hover:bg-white dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3 font-bold text-yellow-600 dark:text-yellow-500"><?= htmlspecialchars($l['username'] ?? 'SYSTEM') ?></td>
                                        <td class="py-3 text-gray-700 dark:text-gray-300"><?= htmlspecialchars($l['description']) ?></td>
                                        <td class="py-3 text-right text-[10px] text-gray-500 font-mono"><?= date('d.m. H:i:s', strtotime($l['performed_at'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($section === 'users'): ?>
                <h1 class="text-4xl font-black text-gray-900 dark:text-white uppercase italic mb-8 tracking-tighter">Uživatelé</h1>
                <div class="glass rounded-[2rem] overflow-hidden shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-white dark:bg-white/5 text-[10px] uppercase text-gray-500 font-bold border-b border-gray-200 dark:border-white/5">
                                <tr><th class="p-6">Uživatel</th><th class="p-6">Rank</th><th class="p-6 text-right">Akce</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                <?php $users = $conn->query("SELECT * FROM users ORDER BY id DESC"); while($u = $users->fetch_assoc()): ?>
                                <tr class="hover:bg-white dark:hover:bg-white/5 transition-colors">
                                    <td class="p-6 font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($u['username']) ?> <span class="text-gray-400 dark:text-gray-600 font-normal ml-1">#<?= $u['id'] ?></span></td>
                                    <td class="p-6"><span class="bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/5 text-gray-600 dark:text-gray-300 px-3 py-1 rounded-full text-[10px] uppercase font-bold"><?= Settings::getRankName($u['user_rank']) ?></span></td>
                                    <td class="p-6 text-right"><a href="admin.php?section=edit_user&id=<?=$u['id']?>" class="bg-yellow-500 hover:bg-yellow-600 text-black px-4 py-2 rounded-lg text-xs font-bold uppercase transition-colors shadow-md">Upravit</a></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($section === 'edit_user'): ?>
                <?php
                    $editId = (int)($_GET['id'] ?? 0);
                    $eu = null;
                    if ($editId) {
                        $stmtEu = $conn->prepare("SELECT u.*, c.name AS city_name, r.name AS region_name FROM users u LEFT JOIN cities c ON u.city_id = c.id LEFT JOIN regions r ON u.region_id = r.id WHERE u.id = ?");
                        $stmtEu->bind_param("i", $editId);
                        $stmtEu->execute();
                        $eu = $stmtEu->get_result()->fetch_assoc();
                    }
                    if (!$eu): ?>
                        <p class="text-red-500 font-bold">Uživatel nenalezen.</p>
                    <?php else: ?>
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <a href="admin.php?section=users" class="text-yellow-500 hover:text-yellow-600 text-sm font-bold uppercase tracking-widest transition-colors"><i class="fa-solid fa-arrow-left mr-2"></i>Zpět na seznam</a>
                        <h1 class="text-4xl font-black text-gray-900 dark:text-white uppercase italic tracking-tighter mt-2">
                            Úprava: <span class="text-yellow-500"><?= htmlspecialchars($eu['username']) ?></span>
                            <span class="text-gray-400 dark:text-gray-600 font-normal text-2xl ml-1">#<?= $eu['id'] ?></span>
                        </h1>
                    </div>
                    <?php if (isset($_GET['saved'])): ?>
                        <span class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 px-4 py-2 rounded-xl text-sm font-bold"><i class="fa-solid fa-check mr-2"></i>Uloženo</span>
                    <?php endif; ?>
                </div>

                <form method="POST" class="space-y-8">
                    <input type="hidden" name="user_id" value="<?= $eu['id'] ?>">

                    <div class="glass p-8 rounded-[2rem] shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-yellow-500 mb-6"><i class="fa-solid fa-user mr-2"></i>Osobní údaje</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Uživatelské jméno</label>
                                <input type="text" value="<?= htmlspecialchars($eu['username']) ?>" disabled
                                       class="w-full p-4 rounded-xl font-bold bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-400 cursor-not-allowed">
                                <p class="text-[10px] text-gray-400 mt-1 ml-1">Jméno nelze měnit z admin panelu</p>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($eu['email']) ?>" required
                                       class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Jméno</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($eu['first_name']) ?>"
                                       class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Příjmení</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($eu['last_name']) ?>"
                                       class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors">
                            </div>
                        </div>
                    </div>

                    <div class="glass p-8 rounded-[2rem] shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-yellow-500 mb-6"><i class="fa-solid fa-map-location-dot mr-2"></i>Působiště</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Země</label>
                                <select id="euCountrySelect" name="country_code"
                                        class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors cursor-pointer">
                                    <option value="">-- Vyber zemi --</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Kraj</label>
                                <select id="euRegionSelect" name="region_id"
                                        class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors cursor-pointer disabled:opacity-50">
                                    <option value="">-- Vyber kraj --</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Město</label>
                                <select id="euCitySelect" name="city_id"
                                        class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors cursor-pointer disabled:opacity-50">
                                    <option value="">-- Vyber město --</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($eu['city_name'] || $eu['region_name']): ?>
                        <p class="text-xs text-gray-400 mt-3 ml-1">Aktuálně: <?= htmlspecialchars(($eu['city_name'] ?? '–') . ', ' . ($eu['region_name'] ?? '–')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="glass p-8 rounded-[2rem] shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-yellow-500 mb-6"><i class="fa-solid fa-shield-halved mr-2"></i>Systémová nastavení</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-widest">Rank (oprávnění)</label>
                                <select name="user_rank" class="w-full p-4 rounded-xl font-bold bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors">
                                    <?php foreach (Settings::RANKS as $rk => $rName): ?>
                                        <option value="<?= $rk ?>" <?= $eu['user_rank'] == $rk ? 'selected' : '' ?>><?= $rk ?> – <?= htmlspecialchars($rName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-500 mb-2 tracking-widest">Level (role)</label>
                                <input type="number" name="role" value="<?= (int)$eu['role'] ?>" min="0" max="10"
                                       class="w-full p-4 rounded-xl font-black text-emerald-600 dark:text-emerald-500 bg-white dark:bg-[#121214] border border-emerald-500/30 outline-none focus:border-emerald-500 transition-colors">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-blue-600 dark:text-blue-500 mb-2 tracking-widest">XP</label>
                                <input type="number" name="xp" value="<?= (int)$eu['xp'] ?>" min="0"
                                       class="w-full p-4 rounded-xl font-black text-blue-600 dark:text-blue-500 bg-white dark:bg-[#121214] border border-blue-500/30 outline-none focus:border-blue-500 transition-colors">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-6 border-t border-gray-100 dark:border-white/5">
                            <div class="flex items-center gap-4">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_banned" value="1" <?= $eu['is_banned'] ? 'checked' : '' ?> class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-red-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                </label>
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Zablokován (ban)</span>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-red-600 dark:text-red-500 mb-2 tracking-widest">Ban do (datum)</label>
                                <input type="datetime-local" name="banned_until" value="<?= $eu['banned_until'] ? date('Y-m-d\TH:i', strtotime($eu['banned_until'])) : '' ?>"
                                       class="w-full p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-red-500/30 text-gray-900 dark:text-white outline-none focus:border-red-500 transition-colors">
                            </div>
                        </div>
                    </div>

                    <div class="glass p-6 rounded-[2rem] shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-400">
                            <div><strong>Registrace:</strong> <?= date('d.m.Y H:i', strtotime($eu['created_at'])) ?></div>
                            <div><strong>Google Fit:</strong> <?= $eu['fit_status'] ? '<span class="text-emerald-500">Aktivní</span>' : '<span class="text-gray-500">Neaktivní</span>' ?></div>
                            <div><strong>Profilový obrázek:</strong> <?= htmlspecialchars($eu['profile_image'] ?? '1.png') ?></div>
                            <div><strong>ID:</strong> <?= $eu['id'] ?></div>
                        </div>
                    </div>

                    <button type="submit" name="update_user_full"
                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-black py-5 rounded-2xl uppercase italic shadow-lg shadow-yellow-500/20 transition-all text-sm tracking-widest">
                        <i class="fa-solid fa-save mr-2"></i>Uložit změny
                    </button>
                </form>

                <script>
                (function(){
                    const savedCountry = "<?= htmlspecialchars($eu['country_code'] ?? '') ?>";
                    const savedRegion = "<?= (int)($eu['region_id'] ?? 0) ?>";
                    const savedCity = "<?= (int)($eu['city_id'] ?? 0) ?>";

                    async function euFetch(url) {
                        try { const r = await fetch(url); return await r.json(); } catch(e) { return []; }
                    }
                    function euFill(sel, data, vk, lk, ph) {
                        sel.innerHTML = '<option value="">-- ' + ph + ' --</option>';
                        data.forEach(d => { const o = document.createElement('option'); o.value = d[vk]; o.textContent = d[lk]; sel.appendChild(o); });
                        sel.disabled = data.length === 0;
                    }
                    async function euLoadCountries() {
                        const cs = document.getElementById('euCountrySelect');
                        const data = await euFetch('src/ajax/ajax_countries.php');
                        euFill(cs, data, 'code', 'name', 'Vyber zemi');
                        if (savedCountry) { cs.value = savedCountry; await euLoadRegions(savedCountry); }
                    }
                    async function euLoadRegions(cc) {
                        const rs = document.getElementById('euRegionSelect');
                        const ms = document.getElementById('euCitySelect');
                        rs.innerHTML = '<option value="">-- Vyber kraj --</option>';
                        ms.innerHTML = '<option value="">-- Vyber město --</option>';
                        if (!cc) return;
                        const data = await euFetch('src/ajax/ajax_regions.php?country_code=' + encodeURIComponent(cc));
                        euFill(rs, data, 'id', 'name', 'Vyber kraj');
                        if (savedRegion) { rs.value = savedRegion; await euLoadCities(savedRegion, cc); }
                    }
                    async function euLoadCities(rid, cc) {
                        const ms = document.getElementById('euCitySelect');
                        ms.innerHTML = '<option value="">-- Vyber město --</option>';
                        if (!rid || !cc) return;
                        const data = await euFetch('src/ajax/ajax_cities.php?region_id=' + encodeURIComponent(rid) + '&country_code=' + encodeURIComponent(cc));
                        euFill(ms, data, 'id', 'name', 'Vyber město');
                        if (savedCity) { ms.value = savedCity; }
                    }
                    document.addEventListener('DOMContentLoaded', function() {
                        euLoadCountries();
                        document.getElementById('euCountrySelect').addEventListener('change', function() { euLoadRegions(this.value); });
                        document.getElementById('euRegionSelect').addEventListener('change', function() { euLoadCities(this.value, document.getElementById('euCountrySelect').value); });
                    });
                })();
                </script>
                <?php endif; ?>

            <?php elseif ($section === 'challenges'): ?>
                <?php 
                    $editMode = false; $cData = null;
                    if (isset($_GET['action']) && $_GET['action']==='edit' && isset($_GET['id'])) {
                        $editMode = true; $cData = $chM->getChallengeById((int)$_GET['id']);
                    }
                ?>
                <h1 class="text-4xl font-black text-gray-900 dark:text-white uppercase italic mb-8 tracking-tighter"><?= $editMode ? "Editace Výzvy" : "Nová Výzva" ?></h1>
                
                <div class="glass p-8 rounded-[2rem] mb-12 shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                    <form method="POST" class="space-y-6">
                        <?php if($editMode): ?><input type="hidden" name="challenge_id" value="<?= $cData['id'] ?>"><?php endif; ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="lg:col-span-2">
                                <input type="text" name="title" required value="<?= $editMode ? htmlspecialchars($cData['title']) : '' ?>" placeholder="Název výzvy" class="w-full p-4 rounded-xl font-bold bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-500 ml-2 tracking-widest">XP Reward</label>
                                    <input type="number" name="xp_reward" value="<?= $editMode ? $cData['xp_reward'] : '500' ?>" class="w-full p-4 mt-1 rounded-xl font-black text-emerald-600 dark:text-emerald-500 bg-white dark:bg-[#121214] border border-emerald-500/30 outline-none focus:border-emerald-500 transition-colors">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase font-bold text-red-600 dark:text-red-500 ml-2 tracking-widest">XP Penalty</label>
                                    <input type="number" name="xp_penalty" value="<?= $editMode ? $cData['xp_penalty'] : '100' ?>" class="w-full p-4 mt-1 rounded-xl font-black text-red-600 dark:text-red-500 bg-white dark:bg-[#121214] border border-red-500/30 outline-none focus:border-red-500 transition-colors">
                                </div>
                            </div>
                            
                            <div>
                                <label class="text-[10px] uppercase font-bold text-gray-500 ml-2 tracking-widest">Cílové kroky</label>
                                <input type="number" name="goal_steps" value="<?= $editMode ? $cData['goal_steps'] : '10000' ?>" class="w-full p-4 mt-1 rounded-xl font-bold bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:col-span-2">
                                <select id="countrySelect" name="country_code" data-saved="<?= $editMode?$cData['country_code']:'' ?>" class="p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors"></select>
                                <select id="regionSelect" name="region_id" data-saved="<?= $editMode?$cData['region_id']:'' ?>" class="p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors"></select>
                                <select id="citySelect" name="city_id" data-saved="<?= $editMode?$cData['city_id']:'' ?>" class="p-4 rounded-xl font-medium bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white outline-none focus:border-yellow-500 transition-colors"></select>
                            </div>
                        </div>
                        <button type="submit" name="<?= $editMode?'update_challenge':'create_challenge' ?>" class="w-full bg-emerald-500 hover:bg-emerald-600 text-black font-black py-5 rounded-2xl uppercase italic shadow-lg shadow-emerald-500/20 transition-all text-sm tracking-widest mt-4">
                            <?= $editMode?'Uložit změny':'Vytvořit výzvu' ?>
                        </button>
                    </form>
                </div>

                <?php if(!$editMode): ?>
                <div class="glass rounded-[2rem] overflow-hidden shadow-sm dark:shadow-none border border-gray-200 dark:border-white/5">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-white dark:bg-white/5 text-[10px] uppercase font-bold text-gray-500 border-b border-gray-200 dark:border-white/5">
                                <tr><th class="p-6">Výzva</th><th class="p-6">Reward / Penalty</th><th class="p-6 text-right">Akce</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                <?php $res = $conn->query("SELECT * FROM challenges ORDER BY id DESC"); while($r = $res->fetch_assoc()): ?>
                                <tr class="hover:bg-white dark:hover:bg-white/5 transition-colors">
                                    <td class="p-6 font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($r['title']) ?></td>
                                    <td class="p-6 font-black italic">
                                        <span class="text-emerald-600 dark:text-emerald-500">+<?= $r['xp_reward'] ?></span> 
                                        <span class="text-gray-400 font-light not-italic mx-1">/</span> 
                                        <span class="text-red-600 dark:text-red-500">-<?= $r['xp_penalty'] ?></span>
                                    </td>
                                    <td class="p-6 text-right space-x-2">
                                        <a href="admin.php?section=challenges&action=edit&id=<?= $r['id'] ?>" class="inline-block p-3 bg-gray-100 dark:bg-white/5 text-gray-700 dark:text-white rounded-xl hover:bg-gray-200 dark:hover:bg-white/10 transition-colors"><i class="fa-solid fa-edit"></i></a>
                                        <a href="admin.php?section=challenges&delete_challenge=<?= $r['id'] ?>" class="inline-block p-3 bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-500 rounded-xl hover:bg-red-200 dark:hover:bg-red-500/20 transition-colors" onclick="return confirm('Opravdu smazat?')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($section === 'leaderboard'): ?>
                <?php
                    $isClosed   = $appSettings->get('leaderboard_closed', '0') === '1';
                    $closedAt   = $appSettings->get('leaderboard_closed_at', '');
                    $seasonLabel= $appSettings->get('leaderboard_season_label', '');
                    $snapshot   = json_decode($appSettings->get('leaderboard_snapshot', '[]'), true) ?: [];
                    $medals     = ['🥇','🥈','🥉'];
                ?>
                <h1 class="text-4xl font-black text-gray-900 dark:text-white uppercase italic mb-2 tracking-tighter">🏆 Žebříček</h1>
                <p class="text-sm text-gray-500 mb-8">Správa sezón – uzavři sezónu pro vyhlášení vítězů.</p>

                <!-- Stav sezóny -->
                <div class="glass rounded-3xl p-8 mb-8 border <?= $isClosed ? 'border-red-500/30' : 'border-emerald-500/30' ?> shadow-sm dark:shadow-none">
                    <div class="flex items-center gap-4 mb-6">
                        <span class="w-3 h-3 rounded-full <?= $isClosed ? 'bg-red-500' : 'bg-emerald-500' ?> shadow-lg <?= $isClosed ? 'shadow-red-500/40' : 'shadow-emerald-500/40' ?>"></span>
                        <h2 class="text-xl font-black text-gray-900 dark:text-white">
                            Stav: <span class="<?= $isClosed ? 'text-red-500' : 'text-emerald-500' ?>"><?= $isClosed ? 'UZAVŘENO' : 'OTEVŘENO' ?></span>
                        </h2>
                        <?php if ($isClosed && $closedAt): ?>
                            <span class="text-xs text-gray-400 ml-auto">Uzavřeno: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($closedAt))) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isClosed): ?>
                    <!-- Formulář pro uzavření sezóny -->
                    <form method="POST" onsubmit="return confirm('Uzavřít sezónu a vyhlásit Top 3 vítěze?')">
                        <div class="flex flex-col sm:flex-row gap-4 items-end">
                            <div class="flex-1 flex flex-col gap-1.5">
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-500">Název sezóny</label>
                                <input type="text" name="season_label" value="Sezóna <?= date('Y') ?>"
                                       class="px-4 py-3 rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-sm font-medium outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            <button type="submit" name="close_leaderboard"
                                    class="px-8 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-black uppercase tracking-widest transition-all shadow-lg shadow-red-500/20 whitespace-nowrap">
                                🔒 Uzavřít sezónu
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-3">Po uzavření se na žebříčku zobrazí podium s vítězi. Filtry a tabulka budou skryty.</p>
                    </form>
                    <?php else: ?>
                    <!-- Zobrazení aktuálního snapshotu + možnost otevřít -->
                    <?php if (!empty($snapshot)): ?>
                    <div class="mb-6">
                        <p class="text-[10px] uppercase font-black tracking-widest text-gray-400 mb-4">Uložení vítězové – „<?= htmlspecialchars($seasonLabel) ?>"</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <?php foreach ($snapshot as $i => $p): ?>
                            <div class="glass rounded-2xl p-5 text-center border border-white/10">
                                <div class="text-3xl mb-2"><?= $medals[$i] ?? '' ?></div>
                                <p class="font-black text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($p['username']) ?></p>
                                <p class="text-yellow-500 font-black text-lg italic mt-1">⚡ <?= number_format($p['xp'], 0, ',', ' ') ?> XP</p>
                                <?php if (!empty($p['city_name'])): ?>
                                <p class="text-xs text-gray-400 mt-1">📍 <?= htmlspecialchars($p['city_name']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Spustit novou sezónu? Podium zmizí a žebříček se vrátí do normálního režimu.')">
                        <button type="submit" name="open_leaderboard"
                                class="px-8 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-500/20">
                            🔓 Spustit novou sezónu
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;

            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }

        (function() {
            const theme = localStorage.getItem('theme');
            const html = document.documentElement;
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        })();
    </script>

    <script>
    async function fetchJsonSafe(url) { 
        console.log("AJAX Fetching:", url);
        try { 
            const r = await fetch(url); 
            if(!r.ok) throw new Error("Network error");
            return await r.json(); 
        } catch(e) { 
            console.error("AJAX Error:", e);
            return []; 
        } 
    }

    function fillSelect(sel, data, valKey, lblKey, def) {
        if(!sel) return;
        sel.innerHTML = `<option value="">-- ${def} --</option>`;
        data.forEach(d => {
            const o = document.createElement('option');
            o.value = d[valKey]; o.textContent = d[lblKey];
            if (sel.dataset.saved == d[valKey]) o.selected = true;
            sel.appendChild(o);
        });
        sel.disabled = (data.length === 0 && def !== 'Země');
    }

    async function loadCountries() {
        const cs = document.getElementById('countrySelect');
        if(!cs) return;
        const data = await fetchJsonSafe('src/ajax/ajax_countries.php');
        fillSelect(cs, data, 'code', 'name', 'Země');
        if (cs.dataset.saved || cs.value) await loadRegions(cs.dataset.saved || cs.value);
    }

    async function loadRegions(cCode) {
        const rs = document.getElementById('regionSelect');
        if(!rs || !cCode) return;
        const data = await fetchJsonSafe('src/ajax/ajax_regions.php?country_code=' + encodeURIComponent(cCode));
        fillSelect(rs, data, 'id', 'name', 'Kraj');
        if (rs.dataset.saved || rs.value) await loadCities(rs.dataset.saved || rs.value, cCode);
    }

    async function loadCities(rId, cCode) {
        const ms = document.getElementById('citySelect');
        if(!ms || !rId || !cCode) return;
        const data = await fetchJsonSafe('src/ajax/ajax_cities.php?region_id=' + encodeURIComponent(rId) + '&country_code=' + encodeURIComponent(cCode));
        fillSelect(ms, data, 'id', 'name', 'Město');
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadCountries();
        const cs = document.getElementById('countrySelect');
        const rs = document.getElementById('regionSelect');
        if(cs) cs.addEventListener('change', (e) => { 
            if(rs) rs.dataset.saved = ""; 
            loadRegions(e.target.value); 
        });
        if(rs) rs.addEventListener('change', (e) => { 
            const ms = document.getElementById('citySelect');
            if(ms) ms.dataset.saved = ""; 
            loadCities(e.target.value, cs.value); 
        });
    });
    </script>
</body>
</html>