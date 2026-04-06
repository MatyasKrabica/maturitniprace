<?php
// Historie výzev konkrétního uživatele
session_start();
require_once __DIR__ . '/src/php/Database.php';
require_once __DIR__ . '/src/classes/ChallengeManager.php';
require_once __DIR__ . '/src/php/settings.php';
require_once __DIR__ . '/src/php/locales.php';

$locale = getLocale();

// Načtení ID uživatele z URL
$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) die(t('history_user_not_found', 'Uživatel nenalezen.'));

$db = new Database();
$cm = new ChallengeManager($db);

// Načtení historie výzev a jména uživatele
$history = $cm->getUserChallengeHistory($userId);

$conn = $db->getConnection();
$uStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$userData = $uStmt->get_result()->fetch_assoc();

if (!$userData) die(t('history_user_not_found', 'Uživatel nenalezen.'));
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('history_title', 'Historie výzev') ?> – <?= htmlspecialchars($userData['username']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        .glass { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0,0,0,0.05); }
        .dark body { background-color: #0a0a0c; color: #d1d5db; }
        .dark .glass { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="antialiased min-h-screen pb-16 transition-colors duration-300">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-500/10 blur-[120px] rounded-full"></div>
    </div>

    <main class="max-w-4xl mx-auto px-6 pt-12">

        <a href="leaderboard.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-900 dark:hover:text-white mb-8 text-xs font-bold uppercase tracking-widest transition-colors">
            <?= t('history_back_leaderboard', '← Zpět na žebříček') ?>
        </a>

        <div class="glass rounded-[2.5rem] p-8 md:p-12 shadow-sm dark:shadow-none">
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white italic tracking-tight">
                    <?= t('history_title', 'Historie výzev') ?>:
                    <span class="text-yellow-600 dark:text-yellow-400"><?= htmlspecialchars($userData['username']) ?></span>
                </h1>
            </div>

            <?php if (empty($history)): ?>
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">📋</div>
                    <p class="text-gray-500 italic"><?= t('history_no_history', 'Tento uživatel zatím nemá historii výzev.') ?></p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/5 text-[10px] uppercase tracking-widest text-gray-400">
                                <th class="py-4 px-4 font-bold"><?= t('history_challenge_col', 'Výzva') ?></th>
                                <th class="py-4 px-4 font-bold"><?= t('history_status_col', 'Stav') ?></th>
                                <th class="py-4 px-4 font-bold"><?= t('history_xp_col', 'Výsledek XP') ?></th>
                                <th class="py-4 px-4 font-bold"><?= t('history_date_col', 'Datum ukončení') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
                            <?php foreach ($history as $row): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                                <td class="py-4 px-4 font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($row['title']) ?></td>
                                <td class="py-4 px-4">
                                    <?php if ($row['status'] == 'completed'): ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                                            <?= t('challenge_completed', 'Splněno') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-red-500/20 text-red-600 dark:text-red-400">
                                            <?= t('challenge_failed', 'Nesplněno') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 font-bold">
                                    <?php if ($row['status'] == 'completed'): ?>
                                        <span class="text-emerald-600 dark:text-emerald-400">+<?= (int)$row['xp_reward'] ?> XP</span>
                                    <?php else: ?>
                                        <span class="text-red-500">-<?= (int)$row['xp_penalty'] ?> XP
                                            <span class="text-gray-400 font-normal text-[10px] ml-1">(<?= t('history_penalty_label', 'Penalizace') ?>)</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 text-gray-500"><?= date('d.m.Y H:i', strtotime($row['expires_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <footer class="max-w-4xl mx-auto px-6 mt-16 text-center pb-8">
        <div class="h-[1px] bg-gray-300 dark:bg-white/5 w-full mb-8"></div>
        <p class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-700 tracking-[0.4em]"><?= t('footer_rights', '© 2026 Maturitní práce • Všechna práva vyhrazena') ?></p>
    </footer>

    <script>
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
</body>
</html>