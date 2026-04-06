<?php
// Žebříček hráčů (Top 100 podle XP, s filtry)
session_start();

require_once __DIR__ . '/src/php/Database.php';
require_once __DIR__ . '/src/classes/UserManager.php';
require_once __DIR__ . '/src/php/settings.php';
require_once __DIR__ . '/src/php/locales.php';
require_once __DIR__ . '/src/php/ban_check.php';
require_once __DIR__ . '/src/classes/AppSettings.php';

$locale = getLocale();
$db   = new Database();
$conn = $db->getConnection();
$userManager    = new UserManager($db);
$appSettings    = new AppSettings($conn);

// Stav sezóny
$seasonClosed   = $appSettings->get('leaderboard_closed', '0') === '1';
$seasonLabel    = $appSettings->get('leaderboard_season_label', '');
$seasonClosedAt = $appSettings->get('leaderboard_closed_at', '');
$snapshot       = json_decode($appSettings->get('leaderboard_snapshot', '[]'), true) ?: [];

// Filtry z URL (jen v otevřeném režimu)
$filterLevel  = isset($_GET['level'])     && $_GET['level']     !== '' ? (int)$_GET['level']     : null;
$filterRegion = isset($_GET['region_id']) && $_GET['region_id'] !== '' ? (int)$_GET['region_id'] : null;
$filterCity   = isset($_GET['city_id'])   && $_GET['city_id']   !== '' ? (int)$_GET['city_id']   : null;
$filterSearch = trim($_GET['search'] ?? '');

$players = [];
$regions = [];
$cities  = [];

if (!$seasonClosed) {
    // Sestavení SQL dotazu s filtry
    $sql = "SELECT u.id, u.username, u.xp, u.total_score, u.profile_image, u.role, u.user_rank,
                      u.region_id, u.city_id,
                      r.name AS region_name, c.name AS city_name
               FROM users u
               LEFT JOIN cities   c ON u.city_id   = c.id
               LEFT JOIN regions  r ON u.region_id = r.id
               WHERE u.is_banned = 0";
    $params = [];
    $types  = '';

    if ($filterLevel !== null) { $sql .= " AND u.role = ?";         $params[] = $filterLevel;              $types .= 'i'; }
    if ($filterRegion !== null){ $sql .= " AND u.region_id = ?";    $params[] = $filterRegion;             $types .= 'i'; }
    if ($filterCity !== null)  { $sql .= " AND u.city_id = ?";      $params[] = $filterCity;               $types .= 'i'; }
    if ($filterSearch !== '')  { $sql .= " AND u.username LIKE ?";  $params[] = '%'.$filterSearch.'%';     $types .= 's'; }

    $sql .= " ORDER BY u.total_score DESC, u.xp DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Načtení krajů pro filtr
    $resR = $conn->query("SELECT id, name FROM regions ORDER BY name");
    while ($r = $resR->fetch_assoc()) $regions[] = $r;

    // Načtení měst pro filtr
    if ($filterRegion) {
        $stmtC = $conn->prepare("SELECT id, name FROM cities WHERE region_id = ? ORDER BY name");
        $stmtC->bind_param("i", $filterRegion); $stmtC->execute();
        $resC = $stmtC->get_result();
    } else {
        $resC = $conn->query("SELECT id, name FROM cities ORDER BY name LIMIT 200");
    }
    while ($r = $resC->fetch_assoc()) $cities[] = $r;
}

// Aktuálně přihlášený uživatel (pro zvýraznění)
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Helper: URL avataru
function getAvatarUrl(array $row): string {
    $default = "https://ui-avatars.com/api/?name=" . urlencode($row['username']) . "&background=random&color=fff&size=64";
    if (!empty($row['profile_image'])) {
        $path = __DIR__ . '/src/uploads/avatar/' . basename($row['profile_image']);
        if (file_exists($path)) return '/src/uploads/avatar/' . rawurlencode($row['profile_image']);
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('leaderboard_title', 'Žebříček') ?> | Maturitní práce</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #f3f4f6; color: #1f2937; }
        .glass { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.05); }
        .dark body { background-color: #0a0a0c; color: #d1d5db; }
        .dark .glass { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); }
        .dark select { color-scheme: dark; }
        select option { background-color: #fff; color: #111827; }
        .dark select option { background-color: #1a1a2e; color: #e5e7eb; }
        .rank-1 { background: linear-gradient(135deg, rgba(234,179,8,0.15) 0%, rgba(161,122,5,0.25) 100%); border-color: rgba(234,179,8,0.4); }
        .rank-2 { background: linear-gradient(135deg, rgba(148,163,184,0.15) 0%, rgba(100,116,139,0.25) 100%); border-color: rgba(148,163,184,0.4); }
        .rank-3 { background: linear-gradient(135deg, rgba(180,120,60,0.15) 0%, rgba(140,90,40,0.25) 100%); border-color: rgba(180,120,60,0.4); }
    </style>
</head>
<body class="antialiased overflow-x-hidden min-h-screen pb-16 transition-colors duration-300">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/10 blur-[120px] rounded-full"></div>
    </div>

    <?php include_once "src/templates/nav.php"; ?>

    <main class="max-w-5xl mx-auto px-6">

        <!-- Nadpis -->
        <div class="mb-8">
            <p class="text-[10px] uppercase font-bold tracking-[0.3em] text-yellow-600 dark:text-yellow-500 mb-2">
                <?= $seasonClosed ? t('leaderboard_season_closed_label', 'Uzavřená sezóna') : t('leaderboard_subtitle', 'Top hráčů podle XP') ?>
            </p>
            <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter italic uppercase">
                🏆 <?= $seasonClosed ? t('leaderboard_title_closed', 'Vyhlášení vítězů') : t('leaderboard_title', 'Žebříček') ?>
            </h1>
            <?php if ($seasonClosed && $seasonLabel): ?>
            <p class="text-sm text-gray-500 mt-2 font-medium"><?= htmlspecialchars($seasonLabel) ?><?= $seasonClosedAt ? ' &bull; ' . date('d.m.Y', strtotime($seasonClosedAt)) : '' ?></p>
            <?php endif; ?>
        </div>

        <?php if ($seasonClosed): ?>
        <!-- =================================================== -->
        <!-- UZAVŘENÁ SEZÓNA – Podium vyhlášení -->
        <!-- =================================================== -->
        <?php if (empty($snapshot)): ?>
            <div class="glass rounded-3xl p-16 text-center shadow-sm dark:shadow-none">
                <div class="text-6xl mb-4">🏆</div>
                <p class="text-gray-500"><?= t('leaderboard_no_snapshot', 'Vítězové nebyli dosud uloženi.') ?></p>
            </div>
        <?php else: ?>
        <?php
            $podiumOrder   = [1, 0, 2];
            $podiumMedals  = ['🥈', '🥇', '🥉'];
            $podiumRanks   = [2, 1, 3];
            $podiumClasses = ['rank-2', 'rank-1', 'rank-3'];
            $podiumSize    = ['md:mt-6', '', 'md:mt-6'];
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
            <?php foreach ($podiumOrder as $i => $pi):
                if (!isset($snapshot[$pi])) continue;
                $p = $snapshot[$pi];
                $levelIdx = Settings::calculateLevelFromScore((int)($p['total_score'] ?? 0));
            ?>
            <div class="glass border rounded-3xl p-8 text-center shadow-sm dark:shadow-none <?= $podiumClasses[$i] ?> <?= $podiumSize[$i] ?> transition-transform hover:-translate-y-1 duration-200">
                <div class="text-5xl mb-4"><?= $podiumMedals[$i] ?></div>
                <div class="text-[10px] uppercase font-black tracking-[0.3em] text-gray-500 mb-3">#<?= $podiumRanks[$i] ?></div>
                <img src="<?= getAvatarUrl($p) ?>" alt="avatar"
                     class="w-16 h-16 rounded-full mx-auto mb-4 border-4 border-white/20 object-cover shadow-xl">
                <h3 class="text-xl font-black text-gray-900 dark:text-white tracking-tight italic mb-1">
                    <?= htmlspecialchars($p['username']) ?>
                    <?php if ((int)$p['id'] === $currentUserId): ?>
                        <span class="text-yellow-500 text-xs ml-1"><?= t('leaderboard_you', '(Ty)') ?></span>
                    <?php endif; ?>
                </h3>
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-4">
                    Level <?= Settings::getRoleName($levelIdx) ?>
                </p>
                <p class="text-2xl font-black text-yellow-600 dark:text-yellow-400 italic">
                    ★ <?= number_format((int)($p['total_score'] ?? 0), 0, ',', ' ') ?>
                    <span class="text-xs font-bold not-italic text-gray-400 ml-1"><?= t('score_label', 'skóre') ?></span>
                </p>
                <?php if (!empty($p['xp'])): ?>
                <p class="text-xs text-gray-400 mt-1">⚡ <?= number_format($p['xp'], 0, ',', ' ') ?> XP bonus</p>
                <?php endif; ?>
                <?php if (!empty($p['city_name'])): ?>
                <p class="text-xs text-gray-400 mt-2">📍 <?= htmlspecialchars($p['city_name']) ?></p>
                <?php endif; ?>
                <a href="user_history.php?id=<?= $p['id'] ?>"
                   class="mt-4 inline-block text-[10px] font-bold uppercase tracking-widest text-gray-500 hover:text-yellow-500 transition-colors">
                    <?= t('leaderboard_history_link', 'Historie výzev') ?> →
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Banner s textem o uzavření -->
        <div class="glass rounded-3xl p-6 text-center border border-yellow-500/20 shadow-sm dark:shadow-none">
            <p class="text-sm text-gray-500">
                🔒 <?= t('leaderboard_season_closed_info', 'Sezóna je uzavřena. Nové výsledky se zobrazí po spuštění nové sezóny.') ?>
            </p>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- =================================================== -->
        <!-- OTEVŘENÁ SEZÓNA – Filtry + Tabulka -->
        <!-- =================================================== -->

        <!-- Filtry -->
        <form method="GET" class="glass rounded-3xl p-6 mb-8 shadow-sm dark:shadow-none">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('leaderboard_filter_search', 'Hledat hráče') ?></label>
                    <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Username..."
                           class="px-4 py-2.5 rounded-xl bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-sm font-medium outline-none focus:border-yellow-500 transition-colors">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('leaderboard_filter_level', 'Úroveň') ?></label>
                    <select name="level" class="px-4 py-2.5 rounded-xl bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-sm font-medium outline-none focus:border-yellow-500 transition-colors cursor-pointer">
                        <option value=""><?= t('leaderboard_filter_all_levels', 'Všechny úrovně') ?></option>
                        <?php foreach (Settings::ROLES as $idx => $name): ?>
                            <option value="<?= $idx ?>" <?= $filterLevel === $idx ? 'selected' : '' ?>>Level <?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('leaderboard_filter_region', 'Kraj') ?></label>
                    <select name="region_id" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-sm font-medium outline-none focus:border-yellow-500 transition-colors cursor-pointer">
                        <option value=""><?= t('leaderboard_filter_all_regions', 'Všechny kraje') ?></option>
                        <?php foreach ($regions as $reg): ?>
                            <option value="<?= $reg['id'] ?>" <?= $filterRegion === (int)$reg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($reg['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-500"><?= t('leaderboard_filter_city', 'Město') ?></label>
                    <select name="city_id" class="px-4 py-2.5 rounded-xl bg-white dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-sm font-medium outline-none focus:border-yellow-500 transition-colors cursor-pointer <?= empty($cities) ? 'opacity-50' : '' ?>" <?= empty($cities) ? 'disabled' : '' ?>>
                        <option value=""><?= t('leaderboard_filter_all_cities', 'Všechna města') ?></option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= $city['id'] ?>" <?= $filterCity === (int)$city['id'] ? 'selected' : '' ?>><?= htmlspecialchars($city['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-between mt-5">
                <p class="text-[11px] text-gray-400 font-medium"><?= count($players) ?> <?= t('leaderboard_results', 'hráčů nalezeno') ?></p>
                <div class="flex gap-3">
                    <?php if ($filterLevel !== null || $filterRegion || $filterCity || $filterSearch): ?>
                        <a href="leaderboard.php" class="px-4 py-2 rounded-xl text-xs font-bold text-red-500 hover:bg-red-500/10 border border-red-500/20 transition-all"><?= t('leaderboard_clear_filters', 'Zrušit filtry') ?></a>
                    <?php endif; ?>
                    <button type="submit" class="px-6 py-2 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-black text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-yellow-500/20"><?= t('leaderboard_filter_btn', 'Filtrovat') ?></button>
                </div>
            </div>
        </form>

        <?php if (empty($players)): ?>
            <div class="glass rounded-3xl p-16 text-center shadow-sm dark:shadow-none">
                <div class="text-6xl mb-6">�️</div>
                <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-2 italic"><?= t('leaderboard_empty', 'Žádní hráči nenalezeni') ?></h3>
                <p class="text-gray-500 text-sm"><?= t('leaderboard_empty_sub', 'Zkus změnit filtry.') ?></p>
            </div>
        <?php else: ?>
        <div class="glass rounded-3xl overflow-hidden shadow-sm dark:shadow-none">

            <!-- Záhlaví tabulky -->
            <div class="grid grid-cols-12 gap-2 px-6 py-4 border-b border-gray-100 dark:border-white/5 text-[10px] uppercase font-black tracking-widest text-gray-400">
                <div class="col-span-1 text-center">#</div>
                <div class="col-span-5"><?= t('leaderboard_player_col', 'Hráč') ?></div>
                <div class="col-span-2 hidden sm:block"><?= t('leaderboard_level_col', 'Level') ?></div>
                <div class="col-span-2 hidden md:block"><?= t('leaderboard_location_col', 'Lokalita') ?></div>
                <div class="col-span-2 text-right"><?= t('score_label', 'Skóre') ?></div>
            </div>

            <!-- Řádky hráčů -->
            <?php
            $rank = 1;
            foreach ($players as $p):
                $levelIdx  = Settings::calculateLevelFromScore((int)($p['total_score'] ?? 0));
                $levelName = Settings::getRoleName($levelIdx);
                $isMe      = (int)$p['id'] === $currentUserId;
            ?>
            <div class="grid grid-cols-12 gap-2 items-center px-6 py-4 border-b border-gray-100 dark:border-white/5 last:border-0
                        hover:bg-white dark:hover:bg-white/[0.02] transition-colors
                        <?= $isMe ? 'bg-yellow-500/5 dark:bg-yellow-500/5' : '' ?>">

                <!-- Pořadí -->
                <div class="col-span-1 text-center">
                    <span class="text-sm font-black text-gray-400 dark:text-gray-600"><?= $rank ?></span>
                </div>

                <!-- Hráč -->
                <div class="col-span-5 flex items-center gap-3 min-w-0">
                    <img src="<?= getAvatarUrl($p) ?>" alt="avatar"
                         class="w-9 h-9 rounded-full border-2 border-white/20 object-cover flex-shrink-0 shadow-sm">
                    <div class="min-w-0">
                        <a href="user_history.php?id=<?= $p['id'] ?>"
                           class="font-black text-gray-900 dark:text-white hover:text-yellow-500 dark:hover:text-yellow-400 transition-colors truncate block text-sm <?= $isMe ? 'text-yellow-600 dark:text-yellow-400' : '' ?>">
                            <?= htmlspecialchars($p['username']) ?>
                            <?php if ($isMe): ?>
                                <span class="text-[10px] font-bold bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 px-2 py-0.5 rounded-full ml-1"><?= t('leaderboard_you', 'Ty') ?></span>
                            <?php endif; ?>
                        </a>
                        <span class="text-[10px] text-gray-400 sm:hidden">Level <?= $levelName ?></span>
                    </div>
                </div>

                <!-- Level badge -->
                <div class="col-span-2 hidden sm:block">
                    <span class="inline-block px-3 py-1 rounded-xl bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/5 text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase">
                        Lvl <?= $levelName ?>
                    </span>
                </div>

                <!-- Lokalita -->
                <div class="col-span-2 hidden md:block">
                    <?php if (!empty($p['city_name'])): ?>
                        <span class="text-xs text-gray-500 dark:text-gray-500">📍 <?= htmlspecialchars($p['city_name']) ?></span>
                    <?php elseif (!empty($p['region_name'])): ?>
                        <span class="text-xs text-gray-500 dark:text-gray-500">📍 <?= htmlspecialchars($p['region_name']) ?></span>
                    <?php else: ?>
                        <span class="text-gray-300 dark:text-gray-700 text-xs">—</span>
                    <?php endif; ?>
                </div>

                <!-- Skóre -->
                <div class="col-span-2 text-right">
                    <span class="font-black text-yellow-600 dark:text-yellow-400 text-sm italic">
                        <?= number_format((int)($p['total_score'] ?? 0), 0, ',', ' ') ?>
                    </span>
                    <span class="text-[10px] text-gray-400 font-bold ml-1"><?= t('score_label', 'skóre') ?></span>
                </div>
            </div>
            <?php $rank++; endforeach; ?>

        </div>
        <?php endif; // konec !empty($players) ?>

        <?php endif; // konec otev\u0159en\u00e1 sez\u00f3na (else) ?>

    </main>

    <footer class="max-w-5xl mx-auto px-6 mt-16 text-center">
        <div class="h-[1px] bg-gray-300 dark:bg-white/5 w-full mb-8"></div>
        <p class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-700 tracking-[0.4em]"><?= t('footer_rights', '© 2026 Maturitní práce • Všechna práva vyhrazena') ?></p>
    </footer>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                document.getElementById('sun-icon')?.classList.add('hidden');
                document.getElementById('moon-icon')?.classList.remove('hidden');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('moon-icon')?.classList.add('hidden');
                document.getElementById('sun-icon')?.classList.remove('hidden');
            }
        }
        (function() {
            const theme = localStorage.getItem('theme');
            const html  = document.documentElement;
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
