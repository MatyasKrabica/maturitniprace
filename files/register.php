<?php
// Registrace nového uživatele
session_start();
require_once 'src/php/Database.php';
require_once 'src/php/settings.php';
require_once 'src/classes/UserManager.php';
require_once 'src/classes/ActionLogManager.php';
require_once 'src/php/locales.php';

date_default_timezone_set('Europe/Prague');

$locale = getLocale();

$db = new Database();
$userManager = new UserManager($db);
$logM = new ActionLogManager($db);
$ip = Settings::getUserIp();

$errors = [];
$old = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'country_code' => '',
    'region_id' => '',
    'city_id' => ''
];

// Zpracování registračního formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    
    // Uložení starých hodnot pro předvyplnění formuláře při chybě
    $old = [
        'username' => trim($data['username'] ?? ''),
        'email' => trim($data['email'] ?? ''),
        'first_name' => trim($data['first_name'] ?? ''),
        'last_name' => trim($data['last_name'] ?? ''),
        'country_code' => trim($data['country_code'] ?? ''),
        'region_id' => isset($data['region_id']) && $data['region_id'] !== '' ? (int)$data['region_id'] : null,
        'city_id' => isset($data['city_id']) && $data['city_id'] !== '' ? (int)$data['city_id'] : null
    ];

    // Pokušení o registraci
    $userId = $userManager->register($data, $errors);

    if ($userId) {
        $logM->logAction((int)$userId, 'REGISTER', "Registrace nového uživatele. IP: $ip");
        
        $_SESSION['user_id'] = $userId;
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <title><?= t('register_title', 'Registrace') ?> | <?= t('nav_home', 'Systém') ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <style>
        select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1rem; }
        .dark select { color-scheme: dark; }
        select option { background-color: #fff; color: #111827; }
        .dark select option { background-color: #1a1a2e; color: #e5e7eb; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-[#0a0a0c] text-gray-900 dark:text-gray-200 min-h-screen py-12 px-4 transition-colors duration-300">

    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white"><?= t('register_title', 'Vytvořit účet') ?></h1>
            <p class="text-gray-500 dark:text-gray-400 mt-3"><?= t('register_subtitle', 'Staňte se součástí naší komunity a začněte plnět výzvy.') ?></p>
        </div>

        <div class="bg-white dark:bg-white/5 backdrop-blur-xl rounded-3xl border border-gray-200 dark:border-white/10 shadow-2xl overflow-hidden">
            
            <?php if (!empty($errors)): ?>
                <div class="p-6 pb-0">
                    <div class="bg-red-500/10 border border-red-500/20 text-red-500 px-4 py-3 rounded-2xl text-sm">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm" autocomplete="off" class="p-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><?= t('register_username', 'Uživatelské jméno') ?></label>
                        <input name="username" required value="<?= htmlspecialchars($old['username']) ?>"
                               class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-blue-500 outline-none transition-all placeholder-gray-400"
                               placeholder="např. jmeno123">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><?= t('register_email', 'Emailová adresa') ?></label>
                        <input name="email" type="email" required value="<?= htmlspecialchars($old['email']) ?>"
                               class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-blue-500 outline-none transition-all placeholder-gray-400"
                               placeholder="jmeno@email.cz">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-100 dark:border-white/5 pt-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><?= t('register_password', 'Heslo') ?></label>
                        <input name="password" type="password" required
                               class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-blue-500 outline-none transition-all"
                               placeholder="••••••••">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><?= t('register_password_confirm', 'Potvrzení hesla') ?></label>
                        <input name="password_confirm" type="password" required
                               class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-blue-500 outline-none transition-all"
                               placeholder="••••••••">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-100 dark:border-white/5 pt-6">
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><?= t('register_first_name', 'Jméno') ?></label>
                        <input name="first_name" required value="<?= htmlspecialchars($old['first_name']) ?>"
                               class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-blue-500 outline-none transition-all"
                               placeholder="Jméno">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300"><?= t('register_last_name', 'Příjmení') ?></label>
                        <input name="last_name" required value="<?= htmlspecialchars($old['last_name']) ?>"
                               class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 focus:border-blue-500 outline-none transition-all"
                               placeholder="Přijmeni">
                    </div>
                </div>

                <div class="space-y-4 border-t border-gray-100 dark:border-white/5 pt-6">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-blue-500 mb-2"><?= t('register_location', 'Působiště') ?></h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1"><?= t('register_country', 'Země') ?></label>
                            <select id="countrySelect" name="country_code" required
                                    class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:border-blue-500 outline-none transition-all cursor-pointer">
                                <option value=""><?= t('register_select_country','-- Vyber zemi --') ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1"><?= t('register_region', 'Kraj') ?></label>
                            <select id="regionSelect" name="region_id" required
                                    class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:border-blue-500 outline-none transition-all cursor-pointer disabled:opacity-50">
                                <option value=""><?= t('register_select_region','-- Vyber kraj --') ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1"><?= t('register_city', 'Město') ?></label>
                            <select id="citySelect" name="city_id" required
                                    class="w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:border-blue-500 outline-none transition-all cursor-pointer disabled:opacity-50">
                                <option value=""><?= t('register_select_city','-- Vyber město --') ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-600/20 transition-all active:scale-[0.98]">
                        <?= t('register_btn', 'Dokončit registraci') ?>
                    </button>
                </div>
            </form>

            <div class="bg-gray-50 dark:bg-white/[0.02] p-6 text-center border-t border-gray-100 dark:border-white/5">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <?= t('register_have_account', 'Již máte vytvořený účet?') ?>
                    <a href="login.php" class="text-blue-500 hover:text-blue-600 font-bold ml-1 transition-colors"><?= t('register_login_link', 'Přihlaste se') ?></a>
                </p>
            </div>
        </div>
    </div>

    <script>
    async function fetchJsonSafe(url) {
        const res = await fetch(url);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const text = await res.text();
        try { return JSON.parse(text); } catch (e) { throw new Error("Invalid JSON"); }
    }

    function fillSelect(select, data, valueKey, labelKey) {
        const placeholder = select.querySelector('option[value=""]')?.textContent || "-- Vyber --";
        select.innerHTML = `<option value="">${placeholder}</option>`;
        data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row[valueKey];
            opt.textContent = row[labelKey];
            select.appendChild(opt);
        });
        select.disabled = data.length === 0;
    }

    async function loadCountries() {
        try {
            const data = await fetchJsonSafe('src/ajax/ajax_countries.php');
            const countrySelect = document.getElementById('countrySelect');
            fillSelect(countrySelect, data, 'code', 'name');
            const oldCountryCode = "<?= $old['country_code'] ?>";
            if (oldCountryCode) {
                countrySelect.value = oldCountryCode;
                loadRegions(oldCountryCode, "<?= $old['region_id'] ?>");
            }
        } catch (e) { console.error('Chyba zemí', e); }
    }

    async function loadRegions(countryCode, oldRegionId = null) {
        const regionSelect = document.getElementById('regionSelect');
        const citySelect = document.getElementById('citySelect');
        regionSelect.innerHTML = '<option value=""><?= addslashes(t('register_select_region','-- Vyber kraj --')) ?></option>';
        citySelect.innerHTML = '<option value=""><?= addslashes(t('register_select_city','-- Vyber město --')) ?></option>';
        if (!countryCode) return;
        try {
            const data = await fetchJsonSafe('src/ajax/ajax_regions.php?country_code=' + encodeURIComponent(countryCode));
            fillSelect(regionSelect, data, 'id', 'name');
            if (oldRegionId) {
                regionSelect.value = oldRegionId;
                loadCities(oldRegionId, countryCode, "<?= $old['city_id'] ?>");
            }
        } catch (e) { console.error('Chyba krajů', e); }
    }

    async function loadCities(regionId, countryCode, oldCityId = null) {
        const citySelect = document.getElementById('citySelect');
        citySelect.innerHTML = '<option value=""><?= addslashes(t('register_select_city','-- Vyber město --')) ?></option>';
        if (!regionId || !countryCode) return;
        try {
            const data = await fetchJsonSafe('src/ajax/ajax_cities.php?region_id=' + encodeURIComponent(regionId) + '&country_code=' + encodeURIComponent(countryCode));
            fillSelect(citySelect, data, 'id', 'name');
            if (oldCityId) { citySelect.value = oldCityId; }
        } catch (e) { console.error('Chyba měst', e); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const countrySelect = document.getElementById('countrySelect');
        const regionSelect = document.getElementById('regionSelect');
        loadCountries();
        countrySelect.addEventListener('change', () => loadRegions(countrySelect.value));
        regionSelect.addEventListener('change', () => loadCities(regionSelect.value, countrySelect.value));

        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    });
    </script>
</body>
</html>