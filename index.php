<?php

// Úvodní stránka aplikace
session_start();
require_once 'src/classes/UserManager.php';
require_once 'src/php/Database.php';
require_once 'src/php/settings.php';
require_once 'src/php/locales.php';

$locale = getLocale();

$db = new Database();
$conn = $db->getConnection();
$userManager = new UserManager($db);

$user = null;
$avatarUrl = '';

if (!empty($_SESSION['user_id'])) {
    $user = $userManager->getUserById((int)$_SESSION['user_id']);
    $avatarUrl = $userManager->getAvatar($user);
}

$statUsers = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$statChallenges = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM challenges"))['c'];
$statCompleted = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM user_challenges WHERE status='completed'"))['c'];

$challengeRows = [];
$crResult = mysqli_query($conn, "SELECT c.*, ci.name AS city_name, r.name AS region_name FROM challenges c LEFT JOIN cities ci ON c.city_id = ci.id LEFT JOIN regions r ON c.region_id = r.id ORDER BY c.created_at DESC LIMIT 6");
if ($crResult) { while ($cr = mysqli_fetch_assoc($crResult)) $challengeRows[] = $cr; }

$primaryHref = !empty($_SESSION['user_id']) ? 'challenge.php' : 'register.php';
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maturitní práce | Matyáš Krabica</title>
    <meta name="description" content="Propoj Google Fit s herními prvky. Plň výzvy, sbírej XP a posouvej svůj rank. Gamifikace pohybu pro každého.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Inter','system-ui','sans-serif'], serif: ['Instrument Serif','serif'] },
                colors: { brand: {50:'#f0fdf4',100:'#dcfce7',200:'#bbf7d0',300:'#86efac',400:'#4ade80',500:'#22c55e',600:'#16a34a',700:'#15803d',800:'#166534',900:'#14532d'} }
            }
        }
    }
    </script>
    <style>
        :root{--bg:#fafbf7;--ink:#0f172a;--muted:#475569;--card:rgba(255,255,255,0.82);--border:rgba(15,23,42,0.08)}
        .glass{background:var(--card);backdrop-filter:blur(16px) saturate(180%);border:1px solid var(--border)}
        .serif-italic{font-family:'Instrument Serif',serif;font-style:italic}
        .gradient-text{background:linear-gradient(135deg,#15803d 0%,#22c55e 50%,#4ade80 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
        .hero-glow{background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(34,197,94,0.12),transparent 70%),radial-gradient(ellipse 60% 50% at 80% 20%,rgba(56,189,248,0.08),transparent 60%)}
        .card-hover{transition:transform .3s cubic-bezier(.4,0,.2,1),box-shadow .3s ease}
        .card-hover:hover{transform:translateY(-4px);box-shadow:0 20px 40px -12px rgba(0,0,0,0.08)}
        [data-animate]{opacity:0;transform:translateY(24px);transition:opacity .6s ease,transform .6s ease}
        [data-animate].visible{opacity:1;transform:translateY(0)}
        .step-line{position:relative}
        .step-line::after{content:'';position:absolute;left:28px;top:64px;bottom:-8px;width:2px;background:linear-gradient(to bottom,#22c55e,transparent)}
        .step-line:last-child::after{display:none}
    </style>
</head>
<body class="antialiased bg-[var(--bg)] text-[var(--ink)] overflow-x-hidden font-sans">

<div class="pointer-events-none fixed inset-0 -z-10 hero-glow"></div>
<div class="pointer-events-none fixed inset-0 -z-10">
    <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[50rem] h-[50rem] rounded-full bg-brand-500/10 blur-[120px]"></div>
    <div class="absolute top-[60vh] right-[-14rem] w-[30rem] h-[30rem] rounded-full bg-amber-400/[0.06] blur-[100px]"></div>
</div>

<nav id="navbar" class="sticky top-0 z-50 border-b border-[var(--border)] bg-[rgba(250,251,247,0.8)] backdrop-blur-xl">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="#" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-2xl bg-brand-600 flex items-center justify-center shadow-lg shadow-brand-600/20 group-hover:shadow-brand-600/40 transition-shadow">
                <i class="fa-solid fa-person-running text-white text-lg"></i>
            </div>
            <div>
                <div class="font-black text-sm tracking-tight leading-none">Maturitní</div>
                <div class="text-[10px] font-semibold text-brand-600 tracking-wider uppercase leading-none mt-0.5">Práce</div>
            </div>
        </a>
        <div class="hidden md:flex items-center gap-1">
            <a href="#features" class="px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-[var(--muted)] hover:text-[var(--ink)] hover:bg-black/[0.03] rounded-xl transition-all">Funkce</a>
            <a href="#jak-to-funguje" class="px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-[var(--muted)] hover:text-[var(--ink)] hover:bg-black/[0.03] rounded-xl transition-all">Jak to funguje</a>
            <a href="#challenges" class="px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-[var(--muted)] hover:text-[var(--ink)] hover:bg-black/[0.03] rounded-xl transition-all">Výzvy</a>
            <a href="#faq" class="px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-[var(--muted)] hover:text-[var(--ink)] hover:bg-black/[0.03] rounded-xl transition-all">FAQ</a>
            <div class="h-6 w-px bg-black/10 mx-3"></div>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="flex items-center gap-3 pl-2 pr-4 py-1.5 rounded-full bg-white border border-black/[0.06] hover:border-brand-300 transition-all shadow-sm">
                    <div class="w-8 h-8 rounded-full overflow-hidden border border-black/[0.08] bg-white"><img src="<?= $avatarUrl ?>" class="w-full h-full object-cover" alt="Avatar"></div>
                    <span class="text-sm font-bold"><?= htmlspecialchars($user['username']) ?></span>
                </a>
            <?php else: ?>
                <a href="login.php" class="px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-[var(--muted)] hover:text-[var(--ink)] rounded-xl transition-all">Přihlásit se</a>
                <a href="register.php" class="ml-1 px-5 py-2.5 rounded-xl bg-brand-600 text-white text-[11px] font-bold uppercase tracking-widest hover:bg-brand-700 transition-colors shadow-md shadow-brand-600/20">Registrace</a>
            <?php endif; ?>
        </div>
        <button id="menu-btn" class="md:hidden p-2.5 rounded-xl border border-black/[0.08] bg-white/70 hover:bg-white transition-colors"><i class="fa-solid fa-bars text-lg"></i></button>
    </div>
    <div id="mobile-menu" class="hidden md:hidden border-t border-black/[0.06] bg-[rgba(250,251,247,0.95)] backdrop-blur-xl px-6 py-6">
        <div class="flex flex-col gap-4">
            <a href="#features" class="mobile-link text-sm font-semibold text-[var(--muted)] hover:text-[var(--ink)]">Funkce</a>
            <a href="#jak-to-funguje" class="mobile-link text-sm font-semibold text-[var(--muted)] hover:text-[var(--ink)]">Jak to funguje</a>
            <a href="#challenges" class="mobile-link text-sm font-semibold text-[var(--muted)] hover:text-[var(--ink)]">Výzvy</a>
            <a href="#faq" class="mobile-link text-sm font-semibold text-[var(--muted)] hover:text-[var(--ink)]">FAQ</a>
            <hr class="border-black/[0.06]">
            <?php if ($isLoggedIn): ?>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden border border-black/[0.08] bg-white"><img src="<?= $avatarUrl ?>" class="w-full h-full object-cover" alt="Avatar"></div>
                    <span class="font-bold"><?= htmlspecialchars($user['username']) ?></span>
                </div>
                <a href="dashboard.php" class="text-brand-600 font-semibold">Dashboard</a>
                <a href="src/php/logout.php" class="text-red-500 font-semibold">Odhlásit se</a>
            <?php else: ?>
                <a href="login.php" class="text-sm font-semibold">Přihlásit se</a>
                <a href="register.php" class="w-full text-center py-3 rounded-xl bg-brand-600 text-white font-bold text-sm">Registrace</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<header class="relative overflow-hidden">
    <div class="max-w-6xl mx-auto px-6 pt-16 md:pt-24 pb-20 md:pb-28">
        <div class="max-w-3xl mx-auto text-center">
            <div class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full border border-brand-200 bg-brand-50/80 backdrop-blur-sm" data-animate>
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-brand-500"></span>
                </span>
                <span class="text-[12px] font-bold text-brand-700 uppercase tracking-wider">Gamifikace pohybu pro každého</span>
            </div>

            <h1 class="mt-8 text-5xl md:text-7xl font-black tracking-tight leading-[1.05]" data-animate>
                Pohyb, který stojí za<span class="serif-italic gradient-text"> každý krok</span>
            </h1>

            <p class="mt-6 text-lg md:text-xl text-[var(--muted)] leading-relaxed max-w-2xl mx-auto" data-animate>
                Propoj Google Fit, přijmi výzvu a sleduj svůj progres. Sbírej XP, posouvej svůj rank a staň se dobyvatelem svých tras. Lokální i globální výzvy na jednom místě.
            </p>

            <div class="mt-10 flex flex-wrap justify-center gap-4" data-animate>
                <a href="<?= $primaryHref ?>" class="group inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-brand-600 text-white text-sm font-bold uppercase tracking-wider shadow-xl shadow-brand-600/25 hover:bg-brand-700 hover:shadow-brand-700/30 transition-all hover:scale-[1.02]">
                    <i class="fa-solid fa-bolt"></i>
                    <?= $isLoggedIn ? 'Prohlédnout výzvy' : 'Začni zdarma' ?>
                    <i class="fa-solid fa-arrow-right text-xs opacity-60 group-hover:translate-x-1 transition-transform"></i>
                </a>
                <a href="#jak-to-funguje" class="inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-white/80 border border-black/[0.08] text-sm font-bold uppercase tracking-wider text-[var(--ink)] hover:bg-white transition-all shadow-sm">
                    <i class="fa-solid fa-circle-play text-brand-600"></i>
                    Jak to funguje
                </a>
            </div>
        </div>

        <div class="mt-16 md:mt-20 max-w-3xl mx-auto" data-animate>
            <div class="glass rounded-3xl p-2">
                <div class="grid grid-cols-3 divide-x divide-black/[0.06]">
                    <div class="text-center py-5 px-4">
                        <div class="text-3xl md:text-4xl font-black gradient-text"><?= $statUsers ?>+</div>
                        <div class="mt-1 text-[11px] md:text-xs font-bold uppercase tracking-wider text-[var(--muted)]">Registrovaných</div>
                    </div>
                    <div class="text-center py-5 px-4">
                        <div class="text-3xl md:text-4xl font-black gradient-text"><?= $statChallenges ?></div>
                        <div class="mt-1 text-[11px] md:text-xs font-bold uppercase tracking-wider text-[var(--muted)]">Výzev v systému</div>
                    </div>
                    <div class="text-center py-5 px-4">
                        <div class="text-3xl md:text-4xl font-black gradient-text"><?= $statCompleted ?></div>
                        <div class="mt-1 text-[11px] md:text-xs font-bold uppercase tracking-wider text-[var(--muted)]">Splněných výzev</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-12 max-w-4xl mx-auto" data-animate>
            <div class="glass rounded-[2rem] p-3 shadow-2xl shadow-black/[0.06]">
                <div class="rounded-[1.5rem] overflow-hidden border border-black/[0.06] bg-white">
                    <img src="src/images/dashboard-landing.png" alt="Preview dashboardu" class="w-full h-auto object-cover">
                </div>
            </div>
        </div>
    </div>
</header>

<section id="features" class="max-w-6xl mx-auto px-6 pb-24">
    <div class="text-center mb-16" data-animate>
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-brand-50 border border-brand-100 text-[11px] font-bold uppercase tracking-wider text-brand-700">
            <i class="fa-solid fa-sparkles"></i> Funkce platformy
        </div>
        <h2 class="mt-5 text-3xl md:text-5xl font-black tracking-tight">Všechno, co potřebuješ k pohybu</h2>
        <p class="mt-4 text-lg text-[var(--muted)] max-w-2xl mx-auto">Jeden systém, který propojí tvoje sportovní data s motivujícími výzvami.</p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
        $features = [
            ['icon'=>'fa-brands fa-google','bg'=>'bg-brand-100','color'=>'text-brand-700','title'=>'Google Fit integrace','desc'=>'Automatický sběr dat z Google Fit. Kroky a aktivita se synchronizují každé 4 hodiny přes bezpečné OAuth 2.0 připojení.'],
            ['icon'=>'fa-solid fa-fire','bg'=>'bg-amber-100','color'=>'text-amber-600','title'=>'XP a levely','desc'=>'Za splnění výzev získáváš XP body. Tvůj rank roste s každou aktivitou. 11 levelů od začátečníka po veterána.'],
            ['icon'=>'fa-solid fa-map-location-dot','bg'=>'bg-sky-100','color'=>'text-sky-600','title'=>'Lokální výzvy','desc'=>'Výzvy navázané na tvoje město nebo kraj. Soutěž s lidmi ve tvém okolí a objevuj lokální trasy.'],
            ['icon'=>'fa-solid fa-globe','bg'=>'bg-purple-100','color'=>'text-purple-600','title'=>'Globální výzvy','desc'=>'Výzvy dostupné pro každého v systému. Bez ohledu na lokaci, stačí mít chuť se hýbat.'],
            ['icon'=>'fa-solid fa-skull-crossbones','bg'=>'bg-red-100','color'=>'text-red-500','title'=>'Penále za nesplnění','desc'=>'Výzvy mají časový limit. Nesplníš cíl včas? Přijdeš o XP. To je ten "event" feeling.'],
            ['icon'=>'fa-solid fa-chart-line','bg'=>'bg-emerald-100','color'=>'text-emerald-600','title'=>'Přehledný dashboard','desc'=>'Aktivní výzva, progres, historie a statistiky. Vše na jedné stránce, navrženo pro rychlý přehled.'],
        ];
        foreach ($features as $f): ?>
        <div class="glass rounded-3xl p-8 card-hover" data-animate>
            <div class="w-14 h-14 rounded-2xl <?= $f['bg'] ?> flex items-center justify-center">
                <i class="<?= $f['icon'] ?> <?= $f['color'] ?> text-2xl"></i>
            </div>
            <h3 class="mt-6 text-xl font-black"><?= $f['title'] ?></h3>
            <p class="mt-3 text-sm text-[var(--muted)] leading-relaxed"><?= $f['desc'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section id="jak-to-funguje" class="relative">
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-brand-50/50 to-transparent pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-6 py-24">
        <div class="text-center mb-16" data-animate>
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-brand-100 text-[11px] font-bold uppercase tracking-wider text-brand-700">
                <i class="fa-solid fa-route"></i> Jak to funguje
            </div>
            <h2 class="mt-5 text-3xl md:text-5xl font-black tracking-tight">Tři jednoduché kroky</h2>
            <p class="mt-4 text-lg text-[var(--muted)] max-w-xl mx-auto">Od registrace po první splněnou výzvu. Celý proces je automatický.</p>
        </div>

        <div class="max-w-2xl mx-auto space-y-4">
            <?php
            $steps = [
                ['num'=>'1','color'=>'bg-brand-600 shadow-brand-600/20','title'=>'Propoj Google Fit','desc'=>'Zaregistruj se a jedním kliknutím propoj svůj Google Fit účet. Systém automaticky stáhne data o tvých krocích přes bezpečné OAuth 2.0 připojení.','tags'=>[['Automatická synchronizace','bg-brand-50 text-brand-700'],['Každé 4 hodiny','bg-brand-50 text-brand-700']]],
                ['num'=>'2','color'=>'bg-amber-500 shadow-amber-500/20','title'=>'Vyber si výzvu','desc'=>'Prohlédni si dostupné výzvy. Každá má jasný cíl (počet kroků), časový limit a odměnu v XP. Vyber tu, co ti sedí.','tags'=>[['Lokální výzvy','bg-amber-50 text-amber-700'],['Globální výzvy','bg-amber-50 text-amber-700']]],
                ['num'=>'3','color'=>'bg-sky-500 shadow-sky-500/20','title'=>'Plň cíle a sbírej XP','desc'=>'Choď, běhej, lezej po kopcích. Tvoje kroky se automaticky počítají. Splň cíl včas a získej XP odměnu!','tags'=>[['+XP odměna','bg-sky-50 text-sky-700'],['-XP penále','bg-red-50 text-red-600']]],
            ];
            foreach ($steps as $i => $s): ?>
            <div class="<?= $i < count($steps)-1 ? 'step-line' : '' ?> flex items-start gap-6 glass rounded-3xl p-8 card-hover" data-animate>
                <div class="shrink-0 w-14 h-14 rounded-2xl <?= $s['color'] ?> text-white flex items-center justify-center text-xl font-black shadow-lg"><?= $s['num'] ?></div>
                <div>
                    <h3 class="text-xl font-black"><?= $s['title'] ?></h3>
                    <p class="mt-2 text-sm text-[var(--muted)] leading-relaxed"><?= $s['desc'] ?></p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <?php foreach ($s['tags'] as $t): ?>
                        <span class="px-3 py-1 rounded-lg <?= $t[1] ?> text-[11px] font-bold"><?= $t[0] ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-12 max-w-2xl mx-auto" data-animate>
            <div class="glass rounded-3xl p-8 text-center">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-brand-600 text-white flex items-center justify-center shadow-lg shadow-brand-600/20">
                    <i class="fa-solid fa-person-walking text-2xl"></i>
                </div>
                <h3 class="mt-5 text-2xl font-black">Připraven vyrazit?</h3>
                <p class="mt-2 text-[var(--muted)]">Stačí se zaregistrovat, propojit Google Fit a vybrat první výzvu.</p>
                <a href="<?= $primaryHref ?>" class="mt-6 inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-brand-600 text-white text-sm font-bold uppercase tracking-wider shadow-xl shadow-brand-600/25 hover:bg-brand-700 transition-all">
                    <i class="fa-solid fa-arrow-right"></i>
                    <?= $isLoggedIn ? 'Vybrat výzvu' : 'Začít teď' ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($challengeRows)): ?>
<section id="challenges" class="max-w-6xl mx-auto px-6 pb-24">
    <div class="text-center mb-16" data-animate>
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-50 border border-amber-100 text-[11px] font-bold uppercase tracking-wider text-amber-700">
            <i class="fa-solid fa-trophy"></i> Aktivní výzvy
        </div>
        <h2 class="mt-5 text-3xl md:text-5xl font-black tracking-tight">Vyber si svou výzvu</h2>
        <p class="mt-4 text-lg text-[var(--muted)] max-w-2xl mx-auto">Každá výzva má jasný cíl, časový limit a odměnu. Vyber tu pravou a ukaž, co v tobě je.</p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
        $diffColors = [
            0 => ['bg'=>'bg-brand-50','border'=>'border-brand-200','text'=>'text-brand-700','label'=>'Snadná','icon'=>'fa-feather'],
            1 => ['bg'=>'bg-amber-50','border'=>'border-amber-200','text'=>'text-amber-700','label'=>'Střední','icon'=>'fa-fire'],
            2 => ['bg'=>'bg-red-50','border'=>'border-red-200','text'=>'text-red-600','label'=>'Těžká','icon'=>'fa-skull'],
        ];
        foreach ($challengeRows as $ch):
            $diff = min((int)($ch['difficulty_rank'] ?? 0), 2);
            $dc = $diffColors[$diff];
            $location = $ch['city_name'] ?? ($ch['region_name'] ?? 'Globální');
        ?>
        <div class="glass rounded-3xl p-7 card-hover" data-animate>
            <div class="flex items-center justify-between">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg <?= $dc['bg'] ?> border <?= $dc['border'] ?> <?= $dc['text'] ?> text-[11px] font-bold">
                    <i class="fa-solid <?= $dc['icon'] ?>"></i> <?= $dc['label'] ?>
                </span>
                <span class="text-[11px] font-bold text-[var(--muted)] uppercase tracking-wider">
                    <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($location) ?>
                </span>
            </div>
            <h3 class="mt-5 text-xl font-black"><?= htmlspecialchars($ch['title']) ?></h3>
            <?php if (!empty($ch['description'])): ?>
                <p class="mt-2 text-sm text-[var(--muted)] leading-relaxed line-clamp-2"><?= htmlspecialchars($ch['description']) ?></p>
            <?php endif; ?>
            <div class="mt-5 grid grid-cols-3 gap-3">
                <div class="text-center p-2 rounded-xl bg-white/60 border border-black/[0.04]">
                    <div class="text-lg font-black"><?= number_format((int)$ch['goal_steps'], 0, ',', ' ') ?></div>
                    <div class="text-[10px] font-bold text-[var(--muted)] uppercase">Kroků</div>
                </div>
                <div class="text-center p-2 rounded-xl bg-white/60 border border-black/[0.04]">
                    <div class="text-lg font-black text-brand-600">+<?= (int)$ch['xp_reward'] ?></div>
                    <div class="text-[10px] font-bold text-[var(--muted)] uppercase">XP</div>
                </div>
                <div class="text-center p-2 rounded-xl bg-white/60 border border-black/[0.04]">
                    <div class="text-lg font-black text-red-500">-<?= (int)$ch['xp_penalty'] ?></div>
                    <div class="text-[10px] font-bold text-[var(--muted)] uppercase">Penále</div>
                </div>
            </div>
            <?php if (!empty($ch['time_limit_hours'])): ?>
            <div class="mt-4 flex items-center gap-2 text-[12px] font-semibold text-[var(--muted)]">
                <i class="fa-regular fa-clock"></i> Časový limit: <?= (int)$ch['time_limit_hours'] ?> hodin
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($isLoggedIn): ?>
    <div class="mt-10 text-center" data-animate>
        <a href="challenge.php" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white border border-black/[0.08] text-sm font-bold uppercase tracking-wider hover:bg-brand-50 hover:border-brand-200 transition-all shadow-sm">
            <i class="fa-solid fa-compass text-brand-600"></i> Zobrazit všechny výzvy <i class="fa-solid fa-arrow-right text-xs text-[var(--muted)]"></i>
        </a>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="relative">
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-sky-50/30 to-transparent pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-6 py-24">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div data-animate>
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-sky-50 border border-sky-100 text-[11px] font-bold uppercase tracking-wider text-sky-700">
                    <i class="fa-solid fa-users"></i> Pro koho je to
                </div>
                <h2 class="mt-5 text-3xl md:text-4xl font-black tracking-tight">Pro každého, kdo chce mít důvod jít ven.</h2>
                <p class="mt-4 text-[var(--muted)] leading-relaxed">Nezáleží jestli běháš maratony nebo chodíš se psem. Systém motivuje na každé úrovni.</p>
                <div class="mt-8 space-y-4">
                    <?php
                    $audiences = [
                        ['icon'=>'fa-solid fa-child-reaching','bg'=>'bg-brand-100','color'=>'text-brand-700','title'=>'Jakýkoliv věk','desc'=>'Od studentů po seniory. Výzvy mají různou obtížnost.'],
                        ['icon'=>'fa-solid fa-shoe-prints','bg'=>'bg-amber-100','color'=>'text-amber-700','title'=>'Jakákoliv aktivita','desc'=>'Chůze, běh, turistika. Počítá se každý krok z Google Fit.'],
                        ['icon'=>'fa-solid fa-signal','bg'=>'bg-sky-100','color'=>'text-sky-700','title'=>'Jakákoliv úroveň','desc'=>'Začátečník i pokročilý. XP systém s 11 levely roste s tebou.'],
                    ];
                    foreach ($audiences as $a): ?>
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-12 h-12 rounded-2xl <?= $a['bg'] ?> flex items-center justify-center">
                            <i class="<?= $a['icon'] ?> <?= $a['color'] ?> text-lg"></i>
                        </div>
                        <div>
                            <div class="font-bold"><?= $a['title'] ?></div>
                            <div class="text-sm text-[var(--muted)] mt-1"><?= $a['desc'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div data-animate>
                <div class="glass rounded-3xl p-8">
                    <h3 class="text-2xl font-black">Rank systém</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Za XP body postupuješ v levelech. Čím víc se hýbeš, tím výš jsi.</p>
                    <div class="mt-6 space-y-3">
                        <?php
                        $ranks = [
                            ['level'=>'1-3','name'=>'Začátečník','xp'=>'0 - 250 XP','color'=>'bg-slate-300','w'=>'w-1/4'],
                            ['level'=>'4-6','name'=>'Pokročilý','xp'=>'500 - 2 000 XP','color'=>'bg-brand-400','w'=>'w-2/4'],
                            ['level'=>'7-9','name'=>'Expert','xp'=>'3 500 - 7 500 XP','color'=>'bg-amber-400','w'=>'w-3/4'],
                            ['level'=>'10-11','name'=>'Veterán','xp'=>'10 000+ XP','color'=>'bg-brand-600','w'=>'w-full'],
                        ];
                        foreach ($ranks as $r): ?>
                        <div class="p-4 rounded-2xl bg-white/60 border border-black/[0.04]">
                            <div class="flex items-center justify-between mb-2">
                                <div><span class="font-black text-sm">Level <?= $r['level'] ?></span><span class="text-[var(--muted)] text-sm ml-2"><?= $r['name'] ?></span></div>
                                <span class="text-[11px] font-bold text-[var(--muted)]"><?= $r['xp'] ?></span>
                            </div>
                            <div class="h-2 rounded-full bg-black/[0.04]"><div class="h-full rounded-full <?= $r['color'] ?> <?= $r['w'] ?>"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-24">
    <div class="glass rounded-3xl p-8 md:p-12" data-animate>
        <div class="text-center mb-10">
            <h2 class="text-2xl md:text-3xl font-black tracking-tight">Postaveno na moderních technologiích</h2>
            <p class="mt-2 text-[var(--muted)]">Maturitní práce Matyáše Krabici</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php
            $techs = [
                ['icon'=>'fa-brands fa-php','color'=>'text-indigo-600','name'=>'PHP 8.1','desc'=>'Backend + OOP'],
                ['icon'=>'fa-solid fa-database','color'=>'text-sky-600','name'=>'MySQL','desc'=>'Relační databáze'],
                ['icon'=>'fa-brands fa-google','color'=>'text-red-500','name'=>'Google Fit API','desc'=>'OAuth 2.0'],
                ['icon'=>'fa-solid fa-wind','color'=>'text-cyan-500','name'=>'Tailwind CSS','desc'=>'Moderní UI'],
            ];
            foreach ($techs as $t): ?>
            <div class="text-center p-5 rounded-2xl bg-white/60 border border-black/[0.04] card-hover">
                <i class="<?= $t['icon'] ?> text-4xl <?= $t['color'] ?>"></i>
                <div class="mt-3 font-bold text-sm"><?= $t['name'] ?></div>
                <div class="text-[11px] text-[var(--muted)]"><?= $t['desc'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="faq" class="max-w-6xl mx-auto px-6 pb-24">
    <div class="text-center mb-16" data-animate>
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-purple-50 border border-purple-100 text-[11px] font-bold uppercase tracking-wider text-purple-700">
            <i class="fa-solid fa-circle-question"></i> FAQ
        </div>
        <h2 class="mt-5 text-3xl md:text-5xl font-black tracking-tight">Časté dotazy</h2>
        <p class="mt-4 text-lg text-[var(--muted)] max-w-xl mx-auto">Odpovědi na vše, co tě může zajímat.</p>
    </div>
    <div class="max-w-3xl mx-auto space-y-3">
        <?php
        $faqs = [
            ['Co jsou XP a jak funguje rank systém?', 'XP (Experience Points) jsou body za splněné výzvy. Čím víc XP máš, tím vyšší je tvůj level a rank. Systém má 11 levelů, od začátečníka (0 XP) až po veterána (10 000+ XP). Za nesplnění výzvy ti může být XP odečteno.'],
            ['Jak se propojí Google Fit s aplikací?', 'Po registraci klikneš na "Propojit Google Fit" ve svém profilu. Budeš přesměrován na Google, kde povolíš přístup. Systém pak automaticky synchronizuje tvoje kroky každé 4 hodiny přes bezpečný OAuth 2.0 protokol.'],
            ['Co znamenají lokální a globální výzvy?', 'Lokální výzvy jsou navázané na konkrétní město nebo kraj. Zobrazí se ti jen ty odpovídající tvému bydlišti. Globální výzvy jsou dostupné pro všechny uživatele.'],
            ['Co když výzvu nesplním v časovém limitu?', 'Některé výzvy mají XP penalizaci. Pokud nesplníš cíl včas, přijdeš o předem daný počet XP. To je motivační prvek, který dává systému "závodní" feeling.'],
            ['Jaké aktivity se počítají?', 'Počítají se všechny aktivity z Google Fit: chůze, běh, turistika. Data se měří primárně v krocích. Různé typy aktivit mají různé váhové koeficienty pro výpočet skóre.'],
            ['Je aplikace zdarma?', 'Aktuálně ano – aplikace je zcela zdarma a registrace není nijak zpoplatněna. Nicméně aktuálně se pracuje na integraci PayPal API, které v budoucnu omezí přístup k některým prémiálním výzvám pro platící uživatele.'],
            ['Musím být přihlášený pro zobrazení výzev?', 'Ano. Výzvy a progres jsou navázané na tvůj účet, proto je přihlášení nutné. Registrace je rychlá a jednoduchá.'],
        ];
        foreach ($faqs as $faq): ?>
        <div class="glass rounded-2xl overflow-hidden" data-animate>
            <button class="faq-btn w-full flex items-center justify-between gap-4 p-5 text-left hover:bg-black/[0.01] transition-colors" aria-expanded="false">
                <span class="font-bold text-[15px]"><?= htmlspecialchars($faq[0]) ?></span>
                <i class="fa-solid fa-plus text-[var(--muted)] shrink-0 transition-transform duration-300"></i>
            </button>
            <div class="faq-panel hidden px-5 pb-5">
                <p class="text-sm text-[var(--muted)] leading-relaxed"><?= htmlspecialchars($faq[1]) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-24">
    <div class="relative rounded-[2.5rem] overflow-hidden" data-animate>
        <div class="absolute inset-0 bg-gradient-to-br from-brand-700 via-brand-600 to-emerald-500"></div>
        <div class="relative px-8 py-16 md:py-20 text-center text-white">
            <h2 class="text-3xl md:text-5xl font-black tracking-tight">Začni svou cestu ještě dnes</h2>
            <p class="mt-4 text-lg text-white/80 max-w-xl mx-auto">Zaregistruj se, propoj Google Fit a přijmi svou první výzvu. Tvoje kroky mají hodnotu.</p>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a href="<?= $primaryHref ?>" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white text-brand-700 text-sm font-bold uppercase tracking-wider hover:bg-brand-50 transition-all shadow-xl">
                    <i class="fa-solid fa-bolt"></i>
                    <?= $isLoggedIn ? 'Jít na výzvy' : 'Registrace zdarma' ?>
                </a>
                <a href="#jak-to-funguje" class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/10 border border-white/20 text-white text-sm font-bold uppercase tracking-wider hover:bg-white/20 transition-all">
                    <i class="fa-solid fa-circle-info"></i> Zjistit více
                </a>
            </div>
        </div>
    </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-16">
    <div class="glass rounded-3xl p-8 md:p-12" data-animate>
        <div class="flex flex-col lg:flex-row gap-10 items-start lg:items-center justify-between">
            <div class="max-w-xl">
                <div class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted)]">Autor projektu</div>
                <h2 class="mt-3 text-3xl font-black tracking-tight">Matyáš Krabica</h2>
                <p class="mt-3 text-sm text-[var(--muted)] leading-relaxed">
                    Student a vývojář webových aplikací se zaměřením na moderní UX, čistý design a praktické řešení. Tento projekt je maturitní práce zaměřená na gamifikaci pohybu pomocí Google Fit API.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-xl bg-brand-50 border border-brand-100 text-[11px] font-bold text-brand-700"><i class="fa-solid fa-code mr-1"></i>Web dev</span>
                    <span class="px-4 py-2 rounded-xl bg-amber-50 border border-amber-100 text-[11px] font-bold text-amber-700"><i class="fa-solid fa-palette mr-1"></i>UX design</span>
                </div>
            </div>
            <div class="w-full lg:max-w-sm">
                <div class="rounded-2xl bg-white/60 border border-black/[0.06] p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-brand-600 text-white flex items-center justify-center"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted)]">Kontakt</div>
                            <div class="font-black">info@matyaskrabica.cz</div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="mailto:info@matyaskrabica.cz" class="w-full text-center py-3 rounded-xl bg-brand-600 text-white text-sm font-bold hover:bg-brand-700 transition-colors"><i class="fa-solid fa-paper-plane mr-2"></i>Napiš mi</a>
                        <a href="https://github.com/MatyasKrabica/maturitniprace" class="w-full text-center py-3 rounded-xl bg-white border border-black/[0.08] text-sm font-bold hover:bg-brand-50 transition-colors"><i class="fa-brands fa-github mr-2"></i>Github</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="max-w-6xl mx-auto px-6 pb-12">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4 border-t border-black/[0.06] pt-8">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-brand-600 flex items-center justify-center"><i class="fa-solid fa-person-running text-white text-sm"></i></div>
            <span class="text-sm font-bold text-[var(--muted)]">© 2026 Maturitní práce</span>
        </div>
        <div class="flex gap-6 text-[11px] font-bold uppercase tracking-wider text-[var(--muted)]">
            <a href="#" class="hover:text-[var(--ink)] transition-colors">Github</a>
            <a href="#" class="hover:text-[var(--ink)] transition-colors">Linkedin</a>
            <a href="mailto:info@matyaskrabica.cz" class="hover:text-[var(--ink)] transition-colors">Kontakt</a>
        </div>
    </div>
</footer>

<script>
const menuBtn = document.getElementById('menu-btn');
const mobileMenu = document.getElementById('mobile-menu');
const nav = document.getElementById('navbar');

menuBtn?.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
mobileMenu?.addEventListener('click', (e) => { if (e.target.closest('a')) mobileMenu.classList.add('hidden'); });

function getScrollOffset() { return Math.ceil((nav?.getBoundingClientRect()?.height ?? 0) + 12); }
document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#"]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href === '#') return;
    e.preventDefault();
    const el = document.getElementById(href.slice(1));
    if (el) window.scrollTo({ top: window.scrollY + el.getBoundingClientRect().top - getScrollOffset(), behavior: 'smooth' });
});

document.querySelectorAll('.faq-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const panel = btn.parentElement.querySelector('.faq-panel');
        const icon = btn.querySelector('i');
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', !expanded);
        panel?.classList.toggle('hidden', expanded);
        icon?.classList.toggle('fa-plus', expanded);
        icon?.classList.toggle('fa-minus', !expanded);
    });
});

const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));

window.addEventListener('scroll', () => {
    nav?.classList.toggle('shadow-md', window.scrollY > 10);
    nav?.classList.toggle('shadow-black/[0.04]', window.scrollY > 10);
});
</script>
</body>
</html>
