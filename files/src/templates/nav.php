<?php
require_once __DIR__ . '/../php/locales.php';
$_nav_locale = getLocale();
if (!empty($_SESSION['user_id'])) {
    $isAdmin = (isset($_SESSION['user_rank']) && $_SESSION['user_rank'] > 1);
} else {
    $isAdmin = 0;
}
?>

<nav class="sticky top-0 z-50 glass border-b border-gray-200 dark:border-white/5 px-6 py-4 mb-10 transition-colors duration-300">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-10">
            <div class="flex items-center gap-2 tracking-tighter group cursor-pointer">
                <a href="https://matyaskrabica.cz/dashboard.php">
                    <span class="text-xl font-extrabold text-gray-900 dark:text-white tracking-widest uppercase"><?= t('nav_title', 'MATURITNÍ PRÁCE') ?></span>
                </a>
            </div>

            <div class="hidden lg:flex items-center gap-1 text-sm font-semibold text-gray-600 dark:text-gray-300">
                <a href="https://matyaskrabica.cz/index.php" class="px-4 py-2 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition"><?= t('nav_home', 'Domů') ?></a>
                <a href="https://matyaskrabica.cz/dashboard.php" class="px-4 py-2 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition"><?= t('nav_dashboard', 'Dashboard') ?></a>
                
                <a href="https://matyaskrabica.cz/challenge.php" class="px-4 py-2 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition"><?= t('nav_challenges', 'Výzvy') ?></a>
                <a href="https://matyaskrabica.cz/user_profile.php" class="px-4 py-2 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition"><?= t('nav_profile', 'Profil') ?></a>
                <a href="https://matyaskrabica.cz/ticket.php" class="px-4 py-2 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition"><?= t('nav_support', 'Podpora') ?></a>
                
                <?php if ($isAdmin): ?>
                    <div class="h-4 w-[1px] bg-gray-200 dark:bg-white/10 mx-2"></div>
                    <a href="https://matyaskrabica.cz/admin.php" class="px-4 py-1.5 text-red-500 hover:bg-red-500/10 border border-red-500/20 rounded-xl transition text-[11px] uppercase tracking-widest font-black"><?= t('nav_admin', 'Admin Panel') ?></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-6">
            <button onclick="toggleTheme()" class="p-2.5 rounded-xl bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 hover:bg-yellow-500/20 transition-all group shadow-sm dark:shadow-none">
                <svg id="sun-icon" class="w-5 h-5 text-gray-600 group-hover:text-yellow-500 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                </svg>
                <svg id="moon-icon" class="w-5 h-5 text-yellow-500 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>       
            </button>

            <a href="?lang=<?= $_nav_locale === 'cs' ? 'en' : 'cs' ?>" class="hidden md:flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-white/10 px-3 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-white/5 transition-all uppercase tracking-widest">
                <?= $_nav_locale === 'cs' ? '🇬🇧 EN' : '🇨🇿 CS' ?>
            </a>

            <a href="https://matyaskrabica.cz/src/php/logout.php" onclick="return confirm('<?= t('nav_logout_confirm', 'Opravdu se odhlásit?') ?>')" class="hidden md:block text-sm font-bold text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-500 transition px-2 uppercase tracking-tighter"><?= t('nav_logout', 'Odhlásit') ?></a>

            <button id="mobile-menu-btn" class="lg:hidden p-2.5 rounded-xl bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </button>
        </div>
    </div>

    <div id="mobile-menu" class="hidden lg:hidden mt-4 pt-4 border-t border-gray-100 dark:border-white/5 transition-all duration-300">
        <div class="flex flex-col gap-2">
            <a href="https://matyaskrabica.cz/index.php" class="px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl font-semibold transition"><?= t('nav_home', 'Domů') ?></a>
            <a href="https://matyaskrabica.cz/dashboard.php" class="px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl font-semibold transition"><?= t('nav_dashboard', 'Dashboard') ?></a>
            
            <a href="https://matyaskrabica.cz/challenge.php" class="px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl font-semibold transition"><?= t('nav_challenges', 'Výzvy') ?></a>
            <a href="https://matyaskrabica.cz/user_profile.php" class="px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl font-semibold transition"><?= t('nav_profile', 'Profil') ?></a>
            <a href="https://matyaskrabica.cz/ticket.php" class="px-4 py-3 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl font-semibold transition"><?= t('nav_support', 'Podpora') ?></a>
            
            <?php if ($isAdmin): ?>
                <div class="h-[1px] w-full bg-gray-100 dark:bg-white/5 my-2"></div>
                <a href="https://matyaskrabica.cz/admin.php" class="px-4 py-3 text-red-500 font-black uppercase tracking-widest text-[11px]"><?= t('nav_admin', 'Admin Panel') ?></a>
            <?php endif; ?>

            <div class="h-[1px] w-full bg-gray-100 dark:bg-white/5 my-2"></div>

            <a href="?lang=<?= $_nav_locale === 'cs' ? 'en' : 'cs' ?>" class="px-4 py-3 flex items-center gap-2 text-sm font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition-all uppercase tracking-widest">
                <?= $_nav_locale === 'cs' ? '🇬🇧 EN' : '🇨🇿 CS' ?>
            </a>

            <div class="h-[1px] w-full bg-gray-100 dark:bg-white/5 my-2"></div>
            
            <a href="https://matyaskrabica.cz/src/php/logout.php" onclick="return confirm('<?= t('nav_logout_confirm', 'Opravdu se odhlásit?') ?>')" class="px-4 py-3 text-red-400 font-bold uppercase tracking-tighter text-sm"><?= t('nav_logout_mobile', 'Odhlásit se') ?></a>
        </div>
    </div>
</nav>

<script>
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });
</script>