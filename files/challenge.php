<?php
// Výzvy – seznam, přijetí a sledování průběhu
session_start();

require_once 'src/php/Database.php';
require_once 'src/classes/UserManager.php';
require_once 'src/php/settings.php';
require_once 'src/php/ban_check.php';
require_once 'src/classes/ChallengeManager.php';
require_once 'src/php/locales.php';

$locale = getLocale();

// Kontrola přihlášení
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$userManager = new UserManager($db);
$chM = new ChallengeManager($db);

// Načtení uživatele, aktivní výzvy a souradnic města
$user_id = $_SESSION['user_id'] ?? 0;
$user = $userManager->getUserById($user_id);
if (!$user) { header("Location: login.php"); exit; }

$isAdmin = (isset($_SESSION['user_rank']) && $_SESSION['user_rank'] > 1);

$activeQuest = $chM->getActiveChallenge($user_id);
$history = $chM->getUserChallengeHistory($user_id);
$userLat = null;
$userLng = null;
if ($user['city_id']) {
    $cs = $conn->prepare("SELECT latitude, longitude FROM cities WHERE id = ?");
    $cs->bind_param("i", $user['city_id']);
    $cs->execute();
    $coords = $cs->get_result()->fetch_assoc();
    $userLat = isset($coords['latitude'])  ? (float)$coords['latitude']  : null;
    $userLng = isset($coords['longitude']) ? (float)$coords['longitude'] : null;
}

// Filtry a řazení z URL parametrů
$localRadius = max(5, min(200, (int)($_GET['local_radius'] ?? 30)));
$gCountry    = $_GET['g_country'] ?? '';
$gRegion     = (int)($_GET['g_region'] ?? 0);
$gMaxKm      = (int)($_GET['g_km'] ?? 0);
$gSort       = in_array($_GET['g_sort'] ?? '', ['distance', 'xp', 'steps']) ? $_GET['g_sort'] : 'distance';

$allChallenges   = $chM->getChallengesWithDistance($userLat, $userLng);
$filterCountries = [];
$filterRegions   = [];
foreach ($allChallenges as $c) {
    if (!empty($c['country_code'])) $filterCountries[$c['country_code']] = true;
    if (!empty($c['region_id']) && !empty($c['region_name'])) $filterRegions[(int)$c['region_id']] = $c['region_name'];
}
ksort($filterCountries);
asort($filterRegions);

$msg = "";
$view = $_GET['view'] ?? 'all';
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Zrušení aktivní výzvy
if (isset($_POST['cancel_quest']) && $activeQuest) {
    if ($chM->cancelChallenge($user_id, $activeQuest['uc_id'], $activeQuest['xp_penalty'])) {
        header("Location: https://matyaskrabica.cz/challenge.php?msg=cancelled"); exit;
    }
}

// Přijetí nové výzvy
if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['id'])) {
    if (!$activeQuest && $chM->startUserChallenge($user_id, (int)$_GET['id'])) {
        header("Location: src/fit/check_challenge.php?challenge_id=" . (int)$_GET['id'] . "&init=1"); exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cancelled') $msg = "\u274c " . t('challenge_cancelled_msg', 'Výzva byla zrušena a XP odečteny.');
    if ($_GET['msg'] === 'completed') $msg = "\ud83c\udf89 " . t('challenge_completed_msg', 'Gratulujeme! Výzva splněna, XP připsány.');
}

$localChallenges  = [];
$globalChallenges = [];
foreach ($allChallenges as $ch_item) {
    if ($activeQuest && $activeQuest['challenge_id'] == $ch_item['id']) continue;
    $dist    = $ch_item['distance_km'];
    $sameReg = !empty($ch_item['region_id']) && (int)$ch_item['region_id'] === (int)$user['region_id'] && empty($ch_item['city_id']);
    if (($dist !== null && (float)$dist <= $localRadius) || $sameReg) {
        $localChallenges[] = $ch_item;
    }
    $globalChallenges[] = $ch_item;
}
if ($gCountry) {
    $globalChallenges = array_values(array_filter($globalChallenges, fn($c) => $c['country_code'] === $gCountry));
}
if ($gRegion) {
    $globalChallenges = array_values(array_filter($globalChallenges, fn($c) => (int)$c['region_id'] === $gRegion));
}
if ($gMaxKm > 0 && $userLat !== null) {
    $globalChallenges = array_values(array_filter($globalChallenges, fn($c) => $c['distance_km'] !== null && (float)$c['distance_km'] <= $gMaxKm));
}
if ($gSort === 'xp') {
    usort($globalChallenges, fn($a, $b) => $b['xp_reward'] - $a['xp_reward']);
} elseif ($gSort === 'steps') {
    usort($globalChallenges, fn($a, $b) => $b['goal_steps'] - $a['goal_steps']);
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <title>Výzvy</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #f3f4f6; color: #1f2937; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); }

        .dark body { background-color: #0a0a0c; color: #d1d5db; }
        .dark .glass { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); }

        .quest-gradient { background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.2) 100%); }
        .mask-gradient { mask-image: linear-gradient(to right, transparent, black); }
        .dark select { color-scheme: dark; }
        select option { background-color: #fff; color: #111827; }
        .dark select option { background-color: #1a1a2e; color: #e5e7eb; }
    </style>
</head>
<body class="antialiased overflow-x-hidden min-h-screen pb-12 transition-colors duration-300">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/10 blur-[120px] rounded-full"></div>
    </div>

    <?php include_once "src/templates/nav.php"; ?>

    <main class="max-w-7xl mx-auto px-6 mt-8">
        
        <div class="mb-10 text-center">
            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white mb-2 italic tracking-tighter uppercase">
                <?= t('challenge_title', 'Výzvy') ?>
            </h1>
        </div>

        <?php if ($msg): ?>
            <div class="mb-8 p-4 rounded-2xl bg-white/50 dark:bg-white/5 border border-yellow-500/30 backdrop-blur-md flex items-center gap-4 shadow-lg">
                <div class="text-2xl">📢</div>
                <p class="font-bold text-gray-800 dark:text-yellow-400"><?= htmlspecialchars($msg) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($view === 'detail' && $detailId > 0): ?>
            <?php 
                $ch = $chM->getChallengeById($detailId); 
                $syncData = null;
                $current_steps = 0;
                if ($ch && $activeQuest && $activeQuest['challenge_id'] == $ch['id']) {
                    $sql_p = "SELECT cs.*, uc.start_steps 
                             FROM challenge_syncs cs 
                             JOIN user_challenges uc ON cs.user_id = uc.user_id AND cs.challenge_id = uc.challenge_id
                             WHERE cs.user_id = ? AND cs.challenge_id = ?";
                    $stmt_p = $conn->prepare($sql_p);
                    $stmt_p->bind_param("ii", $user_id, $detailId);
                    $stmt_p->execute();
                    $syncData = $stmt_p->get_result()->fetch_assoc();
                    
                    $raw_steps = $syncData['steps_count'] ?? 0;
                    $start_steps = $syncData['start_steps'] ?? 0;
                    $current_steps = max(0, $raw_steps - $start_steps);
                }
            ?>
            
            <?php if ($ch): ?>
                <div class="max-w-4xl mx-auto">
                    <div class="glass rounded-[2.5rem] p-8 md:p-12 relative overflow-hidden shadow-xl">
                        
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                            <div>
                                <span class="text-emerald-500 font-bold text-[10px] uppercase tracking-[0.2em] mb-1 block"><?= t('challenge_detail_badge', 'Detail Výzvy') ?></span>
                                <h2 class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white italic tracking-tighter">
                                    <?= htmlspecialchars($ch['title']) ?>
                                </h2>
                                <p class="text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wider">
                                    📍 <?= htmlspecialchars($ch['city_name'] ?: ($ch['region_name'] ?: t('challenge_global', 'Globální'))) ?>
                                </p>
                            </div>
                            <div class="bg-yellow-500/10 border border-yellow-500/20 text-yellow-600 dark:text-yellow-400 px-6 py-3 rounded-2xl text-center">
                                <span class="block text-[10px] uppercase font-bold tracking-widest"><?= t('challenge_reward_badge', 'Odměna') ?></span>
                                <span class="text-2xl font-black italic">+<?= $ch['xp_reward'] ?> XP</span>
                            </div>
                        </div>

                        <div class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed mb-10 font-light">
                            <?= nl2br(htmlspecialchars($ch['description'])) ?>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-10">
                            <div class="bg-gray-100 dark:bg-white/5 p-4 rounded-2xl text-center">
                                <div class="text-xl mb-1">👟</div>
                                <div class="text-[10px] uppercase font-bold text-gray-500 tracking-widest"><?= t('challenge_goal_stat', 'Cíl') ?></div>
                                <div class="font-black text-gray-900 dark:text-white text-lg"><?= number_format($ch['goal_steps'], 0, '.', ' ') ?></div>
                            </div>
                            <div class="bg-gray-100 dark:bg-white/5 p-4 rounded-2xl text-center">
                                <div class="text-xl mb-1">⌛</div>
                                <div class="text-[10px] uppercase font-bold text-gray-500 tracking-widest"><?= t('challenge_limit_stat', 'Limit') ?></div>
                                <div class="font-black text-gray-900 dark:text-white text-lg"><?= $ch['time_limit_hours'] ?> <?= t('challenge_hod', 'hod') ?></div>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-500/10 p-4 rounded-2xl text-center border border-blue-500/20">
                                <div class="text-xl mb-1">🏃</div>
                                <div class="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400 tracking-widest"><?= t('challenge_activity_type', 'Typ aktivity') ?></div>
                                <div class="font-black text-blue-700 dark:text-blue-300 text-lg"><?= Settings::getActivityName((int)($ch['activity_type'] ?? 7)) ?></div>
                            </div>
                            <div class="bg-gray-100 dark:bg-white/5 p-4 rounded-2xl text-center border border-emerald-500/20">
                                <div class="text-xl mb-1">💰</div>
                                <div class="text-[10px] uppercase font-bold text-emerald-500 tracking-widest"><?= t('challenge_gain_stat', 'Zisk') ?></div>
                                <div class="font-black text-emerald-600 dark:text-emerald-400 text-lg"><?= $ch['xp_reward'] ?> XP</div>
                            </div>
                            <div class="bg-gray-100 dark:bg-white/5 p-4 rounded-2xl text-center border border-red-500/20">
                                <div class="text-xl mb-1">⚠️</div>
                                <div class="text-[10px] uppercase font-bold text-red-500 tracking-widest"><?= t('challenge_penalty_stat', 'Penále') ?></div>
                                <div class="font-black text-red-600 dark:text-red-400 text-lg">-<?= $ch['xp_penalty'] ?> XP</div>
                            </div>
                        </div>

                        <?php if ($activeQuest && $activeQuest['challenge_id'] == $ch['id']): ?>
                            <div class="bg-gray-900/5 dark:bg-black/20 p-6 md:p-8 rounded-3xl border border-emerald-500/20 mb-8">
                                <h4 class="text-emerald-600 dark:text-emerald-400 font-black uppercase text-sm tracking-widest mb-4">📈 <?= t('challenge_your_progress', 'Tvůj aktuální progres') ?></h4>
                                
                                <?php 
                                    $percent = ($ch['goal_steps'] > 0) ? ($current_steps / $ch['goal_steps']) * 100 : 0;
                                    $display_percent = min(100, $percent);
                                ?>

                                <div class="relative w-full h-6 bg-gray-200 dark:bg-white/10 rounded-full overflow-hidden mb-2">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 to-green-400 transition-all duration-1000 ease-out flex items-center justify-end pr-2" style="width: <?= $display_percent ?>%;">
                                    </div>
                                </div>
                                <div class="flex justify-between text-xs font-bold uppercase tracking-wider mb-6 text-gray-500">
                                    <span><?= number_format($current_steps, 0, '.', ' ') ?> <?= t('challenge_steps_progress', 'kroků') ?></span>
                                    <span><?= round($percent, 1) ?> %</span>
                                    <span><?= t('challenge_goal_stat', 'Cíl') ?>: <?= number_format($ch['goal_steps'], 0, '.', ' ') ?></span>
                                </div>

                                <div class="grid grid-cols-3 gap-2 mb-6 text-center text-sm">
                                    <div class="bg-white dark:bg-white/5 p-3 rounded-xl">
                                        <span class="block text-gray-400 text-[10px] uppercase font-bold"><?= t('challenge_distance_stat', 'Vzdálenost') ?></span>
                                        <span class="font-bold dark:text-white"><?= number_format($syncData['distance_km'] ?? 0, 2) ?> km</span>
                                    </div>
                                    <div class="bg-white dark:bg-white/5 p-3 rounded-xl">
                                        <span class="block text-gray-400 text-[10px] uppercase font-bold"><?= t('challenge_calories_stat', 'Kalorie') ?></span>
                                        <span class="font-bold dark:text-white"><?= number_format($syncData['calories'] ?? 0, 0) ?> kcal</span>
                                    </div>
                                    <div class="bg-white dark:bg-white/5 p-3 rounded-xl">
                                        <span class="block text-gray-400 text-[10px] uppercase font-bold"><?= t('challenge_last_sync_stat', 'Poslední Sync') ?></span>
                                        <span class="font-bold dark:text-white"><?= isset($syncData['last_sync_ms']) ? date('H:i', (int)($syncData['last_sync_ms']/1000)) : '--:--' ?></span>
                                    </div>
                                </div>

                                <a href="src/fit/check_challenge.php?challenge_id=<?= $ch['id'] ?>" class="block w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 rounded-xl text-center uppercase tracking-widest text-sm transition-all shadow-lg shadow-emerald-500/20">
                                    🔄 <?= t('challenge_update_fit', 'Aktualizovat data z Google Fit') ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="flex gap-4">
                            <?php if (!$activeQuest): ?>
                                <a href="challenge.php?action=start&id=<?= $ch['id'] ?>" class="flex-1 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white font-black py-4 rounded-2xl text-center uppercase tracking-widest text-sm transition-transform hover:scale-[1.02] shadow-xl">
                                    <?= t('challenge_accept_action', 'Přijmout Výzvu') ?>
                                </a>
                            <?php endif; ?>
                            <a href="challenge.php" class="flex-1 bg-gray-200 dark:bg-white/5 hover:bg-gray-300 dark:hover:bg-white/10 text-gray-900 dark:text-white font-bold py-4 rounded-2xl text-center uppercase tracking-widest text-sm transition-colors">
                                <?= t('challenge_back_list', 'Zpět na seznam') ?>
                            </a>
                        </div>

                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            
            <?php if ($activeQuest): ?>
                <div class="mb-16">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="h-px bg-emerald-500/50 flex-grow"></div>
                        <span class="text-emerald-600 dark:text-emerald-400 font-black uppercase tracking-widest text-sm">⚡ <?= t('challenge_currently_active', 'Právě plníš') ?></span>
                        <div class="h-px bg-emerald-500/50 flex-grow"></div>
                    </div>

                    <div class="relative overflow-hidden rounded-[2.5rem] quest-gradient border border-emerald-500/20 p-8 md:p-12 shadow-2xl">
                         <div class="absolute right-0 top-0 h-full w-1/2 opacity-15 pointer-events-none">
                             <img src="https://static.vecteezy.com/system/resources/thumbnails/003/607/543/small/businessman-standing-on-cliff-s-edge-and-looking-at-the-mountain-business-concept-challenge-and-the-goal-vector.jpg" class="object-cover h-full w-full mask-gradient" alt="Background">
                        </div>

                        <div class="relative z-10">
                            <div class="flex flex-wrap items-center gap-3 mb-4">
                                <span class="bg-emerald-500 text-black text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter"><?= t('challenge_active_label', 'Aktivní') ?></span>
                                <span class="text-yellow-600 dark:text-yellow-400 font-bold text-xs uppercase tracking-widest"><?= t('challenge_reward_label', 'Odměna:') ?> <?= $activeQuest['xp_reward'] ?> XP</span>
                            </div>

                            <h3 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white mb-6 italic tracking-tighter">
                                <?= htmlspecialchars($activeQuest['title']) ?>
                            </h3>

                            <div class="flex flex-wrap gap-4 mt-8">
                                <a href="https://matyaskrabica.cz/src/fit/check_challenge.php?challenge_id=<?= $activeQuest['challenge_id'] ?>" class="bg-gray-900 dark:bg-white text-white dark:text-black px-8 py-4 rounded-xl font-black hover:bg-emerald-500 hover:text-white transition-all shadow-lg uppercase text-xs tracking-widest">
                                    🔄 <?= t('challenge_sync_verify', 'Sync & Ověřit') ?>
                                </a>
                                <a href="https://matyaskrabica.cz/challenge.php?view=detail&id=<?= $activeQuest['challenge_id'] ?>" class="bg-white/20 hover:bg-white/30 backdrop-blur-md text-gray-900 dark:text-white border border-white/10 px-8 py-4 rounded-xl font-bold transition-all uppercase text-xs tracking-widest">
                                    <?= t('challenge_show_detail', 'Zobrazit detail') ?>
                                </a>
                                <form method="post" onsubmit="return confirm('<?= htmlspecialchars(t('challenge_give_up_confirm', 'Opravdu to chceš vzdát? Ztratíš XP!'), ENT_QUOTES) ?>')" class="inline-block">
                                    <button type="submit" name="cancel_quest" class="text-red-500 hover:text-red-400 font-bold px-6 py-4 uppercase text-xs tracking-widest transition-colors">
                                        <?= t('challenge_give_up_btn', 'Vzdát to') ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                 <div class="glass rounded-[2rem] p-10 text-center border-dashed border-gray-300 dark:border-white/10 mb-16">
                    <p class="text-gray-500 italic"><?= t('challenge_no_active_info', 'Žádná aktivní výzva. Vyber si nějakou z nabídky níže!') ?></p>
                </div>
            <?php endif; ?>

            <div class="mb-12">
                <div class="flex flex-wrap items-center gap-4 mb-5">
                    <span class="text-2xl">📍</span>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-widest"><?= t('challenge_local_section', 'Výzvy ve tvém okolí') ?></h3>
                    <div class="h-px bg-gray-300 dark:bg-white/10 flex-grow"></div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-6">
                    <span class="text-xs font-black uppercase tracking-wider text-gray-500"><?= t('challenge_radius_label', 'Okruh') ?>:</span>
                    <?php foreach ([10, 30, 50, 100, 150] as $r): ?>
                        <a href="?local_radius=<?= $r ?>&g_country=<?= urlencode($gCountry) ?>&g_region=<?= $gRegion ?>&g_km=<?= $gMaxKm ?>&g_sort=<?= $gSort ?>"
                           class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider border transition-all
                                  <?= $localRadius === $r ? 'bg-yellow-500 text-black border-yellow-500 shadow-lg shadow-yellow-500/20' : 'border-gray-300 dark:border-white/10 text-gray-500 dark:text-gray-400 hover:border-yellow-500/50 hover:text-yellow-600' ?>">
                            <?= $r ?> km
                        </a>
                    <?php endforeach; ?>
                    <?php if ($userLat === null): ?>
                        <span class="text-xs text-orange-500 font-bold ml-1">⚠️ <?= t('challenge_set_city_hint', 'Nastav město v profilu pro GPS filtr') ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($localChallenges)): ?>
                    <p class="text-gray-500 dark:text-gray-500 italic text-sm"><?= sprintf(t('challenge_no_local', 'Žádné výzvy v okruhu %d km od tvého města.'), $localRadius) ?></p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($localChallenges as $ch_item): ?>
                            <div class="glass p-6 rounded-3xl relative group hover:border-yellow-500/30 transition-all flex flex-col h-full">
                                <div class="absolute top-4 right-4 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 text-[10px] font-black px-2 py-1 rounded-md uppercase">
                                    +<?= $ch_item['xp_reward'] ?> XP
                                </div>
                                <?php if ($ch_item['distance_km'] !== null): ?>
                                    <div class="absolute top-4 left-4 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-black px-2 py-1 rounded-md">
                                        <?= $ch_item['distance_km'] ?> km
                                    </div>
                                <?php endif; ?>
                                <h4 class="text-xl font-black text-gray-900 dark:text-white mb-2 italic pr-12 <?= $ch_item['distance_km'] !== null ? 'mt-5' : '' ?>">
                                    <?= htmlspecialchars($ch_item['title']) ?>
                                </h4>
                                <small class="text-gray-400 font-bold uppercase text-[10px] tracking-wider mb-2 block">
                                    📍 <?= htmlspecialchars($ch_item['city_name'] ?: $ch_item['region_name'] ?: $ch_item['country_code'] ?: t('challenge_global', 'Globální')) ?>
                                </small>
                                <small class="text-blue-500 dark:text-blue-400 font-bold uppercase text-[10px] tracking-wider mb-4 block">
                                    🏃 <?= Settings::getActivityName((int)($ch_item['activity_type'] ?? 7)) ?>
                                </small>
                                <div class="mt-auto">
                                    <a href="challenge.php?view=detail&id=<?= $ch_item['id'] ?>" class="block w-full border border-gray-300 dark:border-white/10 hover:border-yellow-500/50 text-gray-600 dark:text-gray-300 hover:text-yellow-600 dark:hover:text-yellow-400 text-center py-3 rounded-xl font-bold uppercase text-[10px] tracking-widest transition-colors">
                                        <?= t('challenge_view_btn', 'Prohlédnout') ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-12">
                <div class="flex flex-wrap items-center gap-4 mb-5">
                    <span class="text-2xl">🌍</span>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-widest"><?= t('challenge_all_section', 'Všechny výzvy') ?></h3>
                    <div class="h-px bg-gray-300 dark:bg-white/10 flex-grow"></div>
                </div>

                <form method="get" class="glass rounded-2xl p-4 mb-6 flex flex-wrap gap-3 items-end">
                    <input type="hidden" name="local_radius" value="<?= $localRadius ?>">

                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('challenge_sort_label', 'Řadit') ?></label>
                        <select name="g_sort" onchange="this.form.submit()" class="bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl px-3 py-2 text-sm font-bold text-gray-800 dark:text-white focus:outline-none focus:border-blue-500">
                            <option value="distance" <?= $gSort === 'distance' ? 'selected' : '' ?>>📍 <?= t('challenge_sort_distance', 'Vzdálenost') ?></option>
                            <option value="xp"       <?= $gSort === 'xp'       ? 'selected' : '' ?>>⚡ <?= t('challenge_sort_xp', 'XP odměna') ?></option>
                            <option value="steps"    <?= $gSort === 'steps'    ? 'selected' : '' ?>>👟 <?= t('challenge_sort_steps', 'Náročnost') ?></option>
                        </select>
                    </div>

                    <?php if (!empty($filterCountries)): ?>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('challenge_filter_country', 'Stát') ?></label>
                        <select name="g_country" onchange="this.form.submit()" class="bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl px-3 py-2 text-sm font-bold text-gray-800 dark:text-white focus:outline-none focus:border-blue-500">
                            <option value=""><?= t('challenge_filter_all', 'Všechny') ?></option>
                            <?php foreach (array_keys($filterCountries) as $cc): ?>
                                <option value="<?= htmlspecialchars($cc) ?>" <?= $gCountry === $cc ? 'selected' : '' ?>><?= htmlspecialchars($cc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($filterRegions)): ?>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('challenge_filter_region', 'Kraj') ?></label>
                        <select name="g_region" onchange="this.form.submit()" class="bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl px-3 py-2 text-sm font-bold text-gray-800 dark:text-white focus:outline-none focus:border-blue-500">
                            <option value="0"><?= t('challenge_filter_all', 'Všechny') ?></option>
                            <?php foreach ($filterRegions as $rid => $rname): ?>
                                <option value="<?= $rid ?>" <?= $gRegion === $rid ? 'selected' : '' ?>><?= htmlspecialchars($rname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($userLat !== null): ?>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('challenge_max_km_label', 'Max. km ode mě') ?></label>
                        <select name="g_km" onchange="this.form.submit()" class="bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl px-3 py-2 text-sm font-bold text-gray-800 dark:text-white focus:outline-none focus:border-blue-500">
                            <option value="0"   <?= $gMaxKm === 0   ? 'selected' : '' ?>><?= t('challenge_no_limit', 'Bez limitu') ?></option>
                            <option value="25"  <?= $gMaxKm === 25  ? 'selected' : '' ?>>25 km</option>
                            <option value="50"  <?= $gMaxKm === 50  ? 'selected' : '' ?>>50 km</option>
                            <option value="100" <?= $gMaxKm === 100 ? 'selected' : '' ?>>100 km</option>
                            <option value="200" <?= $gMaxKm === 200 ? 'selected' : '' ?>>200 km</option>
                            <option value="500" <?= $gMaxKm === 500 ? 'selected' : '' ?>>500 km</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($gCountry || $gRegion || $gMaxKm): ?>
                        <a href="?local_radius=<?= $localRadius ?>" class="text-xs font-bold text-red-500 hover:text-red-400 transition-colors self-end pb-2">✕ <?= t('challenge_clear_filters', 'Zrušit filtry') ?></a>
                    <?php endif; ?>
                </form>

                <?php if (empty($globalChallenges)): ?>
                    <p class="text-gray-500 dark:text-gray-500 italic text-sm"><?= t('challenge_no_match', 'Žádné výzvy neodpovídají filtru.') ?></p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($globalChallenges as $ch_item): ?>
                            <div class="glass p-6 rounded-3xl relative group hover:border-blue-500/30 transition-all flex flex-col h-full">
                                <div class="absolute top-4 right-4 bg-blue-500/10 text-blue-600 dark:text-blue-400 text-[10px] font-black px-2 py-1 rounded-md uppercase">
                                    +<?= $ch_item['xp_reward'] ?> XP
                                </div>
                                <?php if ($ch_item['distance_km'] !== null): ?>
                                    <div class="absolute top-4 left-4 bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-400 text-[10px] font-black px-2 py-1 rounded-md">
                                        <?= $ch_item['distance_km'] ?> km
                                    </div>
                                <?php endif; ?>
                                <h4 class="text-xl font-black text-gray-900 dark:text-white mb-2 italic pr-12 <?= $ch_item['distance_km'] !== null ? 'mt-5' : '' ?>">
                                    <?= htmlspecialchars($ch_item['title']) ?>
                                </h4>
                                <small class="text-gray-400 font-bold uppercase text-[10px] tracking-wider mb-2 block">
                                    📍 <?= htmlspecialchars($ch_item['city_name'] ?: $ch_item['region_name'] ?: $ch_item['country_code'] ?: t('challenge_global', 'Globální')) ?>
                                </small>
                                <small class="text-blue-500 dark:text-blue-400 font-bold uppercase text-[10px] tracking-wider mb-4 block">
                                    🏃 <?= Settings::getActivityName((int)($ch_item['activity_type'] ?? 7)) ?>
                                </small>
                                <div class="mt-auto">
                                    <a href="challenge.php?view=detail&id=<?= $ch_item['id'] ?>" class="block w-full border border-gray-300 dark:border-white/10 hover:border-blue-500/50 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 text-center py-3 rounded-xl font-bold uppercase text-[10px] tracking-widest transition-colors">
                                        <?= t('challenge_view_btn', 'Prohlédnout') ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </main>

    <footer class="max-w-7xl mx-auto px-6 mt-20 text-center">
        <div class="h-[1px] bg-gray-300 dark:bg-white/5 w-full mb-8"></div>
        <p class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-700 tracking-[0.4em]"><?= t('footer_rights', '&copy; 2026 Maturitní práce • Všechna práva vyhrazena') ?></p>
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