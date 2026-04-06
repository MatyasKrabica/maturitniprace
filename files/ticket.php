<?php
// Systém support ticketů
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'src/php/Database.php';
require_once 'src/classes/TicketManager.php';
require_once 'src/classes/UserManager.php';
require_once 'src/php/settings.php';
require_once 'src/php/locales.php';

$locale = getLocale();

// Kontrola přihlášení
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$ticketManager = new TicketManager($db);
$userManager = new UserManager($db);

$currentUserId = (int)$_SESSION['user_id'];
$user = $userManager->getUserById($currentUserId);
$currentUsername = $user['username'];

$isAdmin = (isset($_SESSION['user_rank']) && $_SESSION['user_rank'] > 1);
$isAdminOrSupport = $isAdmin;

$errors = [];
$message = '';

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

$action = $_GET['action'] ?? 'list';
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$categories = $ticketManager->getCategories();
$otherCategoryId = $ticketManager->getOtherCategoryId();

// Překlad názvu kategorie z DB hodnoty na lokalizovaný string
function translateTicketCategory(string $name): string {
    $map = [
        'Technický problém'  => t('ticket_cat_technical',  'Technický problém'),
        'Návrh na zlepšení'  => t('ticket_cat_suggestion', 'Návrh na zlepšení'),
        'Vlastní důvod'      => t('ticket_cat_custom',     'Vlastní důvod'),
    ];
    return $map[$name] ?? $name;
}

// Admin akce: uzavření, znovuotevření, smazání ticketu
if ($isAdminOrSupport && $ticketId > 0 && isset($_GET['action'])) {
    if ($action === 'close') {
        if ($ticketManager->closeTicket($ticketId)) {
            $message = "Ticket #$ticketId byl uzavřen.";
            $action = 'open';
        } else {
            $errors[] = "Chyba při uzavírání ticketu.";
        }
    } elseif ($action === 'reopen') {
        if ($ticketManager->reopenTicket($ticketId)) {
            $message = "Ticket #$ticketId byl znovu otevřen.";
            $action = 'open';
        } else {
            $errors[] = "Chyba při otevírání ticketu.";
        }
    } elseif ($action === 'delete') {
        if ($ticketManager->deleteTicket($ticketId)) {
            header("Location: ticket.php?msg=Ticket%20byl%20smazán");
            exit;
        } else {
            $errors[] = "Chyba při mazání ticketu.";
        }
    }
    if ($isAdminOrSupport && isset($_GET['delete_ans_id'])) {
    $ansId = (int)$_GET['delete_ans_id'];
    if ($ticketManager->deleteAnswer($ansId)) {
        header("Location: ticket.php?action=open&id=$ticketId&msg=Odpověď byla smazána.");
        exit;
    } else {
        $errors[] = "Chyba při mazání odpovědi.";
    }
}
}

// Odeslání odpovědi na ticket
if ($action === 'open' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_text']) && $ticketId > 0) {
    $ticketInfo = $ticketManager->getTicketById($ticketId);

    if ($ticketInfo) {
        $isAuthor = ($currentUsername === $ticketInfo['username']);
        $canAnswer = $isAdminOrSupport || $isAuthor;

        $isClosed = ((int)$ticketInfo['status'] === TicketManager::STATUS_CLOSED);
        if ($isClosed && !$isAdminOrSupport) {
            $canAnswer = false;
            $errors[] = t('error_ticket_closed_reply', 'Nemůžete odpovídat na uzavřený ticket.');
        }

        $text = $_POST['answer_text'] ?? '';
        $isEmpty = trim(strip_tags($text)) === '' || $text === '<p><br></p>';

        if ($canAnswer && !$isEmpty) {
            $success = $ticketManager->addAnswer($ticketId, $currentUsername, $text, $isAdminOrSupport);
            if ($success) {
                header("Location: ticket.php?action=open&id=$ticketId&msg=Odpověď%20byla%20odeslána.");
                exit;
            } else {
                $errors[] = t('error_ticket_send_answer', 'Nepodařilo se odeslat odpověď.');
            }
        } elseif ($isEmpty) {
            $errors[] = t('error_answer_empty', 'Text odpovědi nesmí být prázdný.');
        }
    } else {
        $errors[] = t('error_ticket_not_exists', 'Ticket neexistuje.');
    }
}

// Vytvoření nového ticketu
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = $_POST['ticket_text'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $ticketName = trim($_POST['ticket_name'] ?? '');
    $customReason = trim($_POST['custom_reason'] ?? '');

    if ($categoryId === $otherCategoryId) {
        $ticketName = $customReason;
    }

    $isEmpty = trim(strip_tags($text)) === '' || $text === '<p><br></p>';

    if ($ticketName === '' || $isEmpty || $categoryId <= 0) {
        $errors[] = t('error_ticket_fill_all', 'Vyplňte název, text ticketu a vyberte kategorii.');
    } else {
        $newId = $ticketManager->createTicket($currentUsername, $ticketName, $text, $categoryId, TicketManager::STATUS_OPEN);
        if ($newId) {
            header("Location: ticket.php?action=open&id=$newId&msg=Ticket%20vytvořen");
            exit;
        } else {
            $errors[] = t('error_ticket_create', 'Chyba při vytváření ticketu do databáze.');
        }
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">

<head>
<meta charset="utf-8">
<title>Maturitní práce | <?= t('ticket_title', 'Podpora') ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    darkMode: 'class',
}
</script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<style>
body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f3f4f6; color: #1f2937; }
.glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(0, 0, 0, 0.05); }
.dark body { background-color: #0a0a0c; color: #d1d5db; }
.dark .glass { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); }

.dark .ql-toolbar { background: #1f2937; border-color: rgba(255,255,255,0.1) !important; color: white; }
.dark .ql-container { border-color: rgba(255,255,255,0.1) !important; color: white; }
.dark .ql-stroke { stroke: #d1d5db !important; }
.dark .ql-fill { fill: #d1d5db !important; }
.dark .ql-picker { color: #d1d5db !important; }
.ql-editor { min-height: 150px; }
.ql-editor img { max-width: 100%; }

.prose {
    word-break: break-word; 
    overflow-wrap: break-word; 
    word-wrap: break-word;
    white-space: normal !important; 
}

.prose p, .prose span, .prose a {
    word-break: break-all !important; 
    max-width: 100%;
    display: block; 
}

.prose pre {
    white-space: pre-wrap !important;
    word-break: break-all !important;
    overflow-x: auto;
}

</style>
</head>

<body class="antialiased overflow-x-hidden min-h-screen pb-12 transition-colors duration-300">

<div class="fixed top-0 left-1/2 -translate-x-1/2 w-full h-full -z-10 opacity-30 pointer-events-none">
<div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-red-500/10 blur-[120px] rounded-full"></div>
<div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-yellow-500/10 blur-[120px] rounded-full"></div>
</div>

<?php include_once "src/templates/nav.php"; ?>

<main class="max-w-7xl mx-auto px-6 pt-6">

<?php if (!empty($message)): ?>
<div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-3">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="mb-6 p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 font-bold">
<ul class="list-disc list-inside">
<?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?>
</ul>
</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<div class="glass rounded-[2.5rem] p-8 md:p-12 shadow-sm dark:shadow-none">
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
<div>
<h1 class="text-3xl font-black text-gray-900 dark:text-white italic tracking-tight mb-2"><?= t('ticket_my_tickets', 'Moje Tickety') ?></h1>
<p class="text-gray-500 text-sm"><?= t('ticket_overview_text', 'Přehled vaší komunikace s podporou.') ?></p>
</div>
<a href="ticket.php?action=create" class="bg-yellow-500 hover:bg-yellow-600 text-black px-6 py-3 rounded-xl font-black transition-all shadow-lg shadow-yellow-500/20 uppercase tracking-widest text-xs flex items-center gap-2">
<span><?= t('ticket_new_ticket_btn_label', '+ Nový Ticket') ?></span>
</a>
</div>

<?php
$tickets = $isAdminOrSupport ? $ticketManager->getAllTickets() : $ticketManager->getUserTickets($currentUsername);
?>

<?php if (empty($tickets)): ?>
<div class="text-center py-12">
<div class="w-20 h-20 bg-gray-100 dark:bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">🎫</div>
<h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?= t('ticket_no_tickets', 'Žádné tickety') ?></h3>
<p class="text-gray-500"><?= t('ticket_no_tickets_text', 'Zatím jste nevytvořili žádný požadavek na podporu.') ?></p>
</div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="border-b border-gray-200 dark:border-white/5 text-[10px] uppercase tracking-widest text-gray-400 dark:text-gray-500">
<th class="py-4 px-4 font-bold"><?= t('ticket_id_col', 'ID') ?></th>
<th class="py-4 px-4 font-bold w-1/3"><?= t('ticket_subject_col', 'Předmět') ?></th>
<th class="py-4 px-4 font-bold"><?= t('ticket_author_col', 'Autor') ?></th>
<th class="py-4 px-4 font-bold"><?= t('ticket_category_col', 'Kategorie') ?></th>
<th class="py-4 px-4 font-bold"><?= t('ticket_status_col', 'Status') ?></th>
<th class="py-4 px-4 font-bold text-right"><?= t('ticket_actions_col', 'Akce') ?></th>
</tr>
</thead>
<tbody class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
<?php foreach ($tickets as $t): ?>
<tr class="group hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
<td class="py-4 px-4 font-mono text-gray-500">#<?= htmlspecialchars($t['id']) ?></td>
<td class="py-4 px-4 font-bold text-gray-900 dark:text-white">
<a href="ticket.php?action=open&id=<?= $t['id'] ?>" class="hover:text-yellow-500 transition-colors">
<?= htmlspecialchars($t['name']) ?>
</a>
</td>
<td class="py-4 px-4 text-gray-600 dark:text-gray-400">
<div class="flex items-center gap-3">
    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-200 dark:bg-white/10 border border-gray-300 dark:border-white/10 flex-shrink-0">
        <img src="<?= $userManager->getUserAvatarTicket($t['username']); ?>" 
             class="w-full h-full object-cover" alt="Avatar">
    </div>
    <span class="font-medium"><?= htmlspecialchars($t['username']) ?></span>
</div>
</td>
<td class="py-4 px-4 text-gray-500"><?= htmlspecialchars(translateTicketCategory($t['category_name'] ?? '-')) ?></td>
<td class="py-4 px-4">
<?php
$statusColors = [
    0 => 'bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
1 => 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
2 => 'bg-yellow-500/20 text-yellow-600 dark:text-yellow-400',
3 => 'bg-blue-500/20 text-blue-600 dark:text-blue-400'
];
$badges = $statusColors[$t['status']] ?? 'bg-gray-200 text-gray-600';
?>
<span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $badges ?>">
<?= t($t['status_text'], $t['status_text']) ?>
</span>
</td>
<td class="py-4 px-4 text-right">
<a href="ticket.php?action=open&id=<?= $t['id'] ?>" class="text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold text-xs uppercase tracking-wider"><?= t('ticket_open_link', 'Otevřít') ?> &rarr;</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

<?php elseif ($action === 'create'): ?>
<div class="max-w-3xl mx-auto">
<a href="ticket.php?action=list" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-900 dark:hover:text-white mb-6 text-xs font-bold uppercase tracking-widest transition-colors">
<?= t('ticket_back_list', '← Zpět na seznam') ?>
</a>

<div class="glass rounded-[2.5rem] p-8 md:p-12 shadow-xl">
<h2 class="text-3xl font-black text-gray-900 dark:text-white italic tracking-tight mb-8"><?= t('ticket_create_form_title', 'Vytvořit Ticket') ?></h2>

<form method="post" onsubmit="return submitQuillForm('ticketQuillEditor', 'ticket_text')" class="space-y-6">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="space-y-2">
<label class="text-[10px] uppercase font-bold text-gray-500 tracking-widest"><?= t('ticket_category_label', 'Kategorie') ?></label>
<select id="categorySelect" name="category_id" required class="w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-yellow-500 transition-colors text-gray-900 dark:text-white">
<option value=""><?= t('ticket_select_category', '-- Vyberte kategorii --') ?></option>
<?php foreach ($categories as $cat): ?>
<option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div id="customReasonContainer" class="hidden space-y-2">
<label class="text-[10px] uppercase font-bold text-gray-500 tracking-widest"><?= t('ticket_spec_label', 'Specifikace') ?></label>
<input type="text" id="customReasonInput" name="custom_reason" placeholder="<?= htmlspecialchars(t('ticket_spec_placeholder', 'Uveďte důvod...'), ENT_QUOTES) ?>" class="w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-yellow-500 transition-colors text-gray-900 dark:text-white">
</div>
</div>

<div class="space-y-2" id="ticketNameLabel">
<label class="text-[10px] uppercase font-bold text-gray-500 tracking-widest"><?= t('ticket_subject', 'Předmět') ?></label>
<input type="text" id="ticketNameInput" name="ticket_name" required class="w-full bg-gray-50 dark:bg-[#121214] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-yellow-500 transition-colors text-gray-900 dark:text-white font-bold" placeholder="<?= htmlspecialchars(t('ticket_subject_placeholder', 'Stručný popis problému'), ENT_QUOTES) ?>">
</div>

<div class="space-y-2">
<label class="text-[10px] uppercase font-bold text-gray-500 tracking-widest"><?= t('ticket_message', 'Zpráva') ?></label>
<div class="bg-white dark:bg-[#121214] rounded-xl overflow-hidden border border-gray-200 dark:border-white/10">
<div id="ticketQuillEditor" class="text-gray-900 dark:text-gray-200"></div>
</div>
<textarea name="ticket_text" id="ticket_text_hidden" style="display:none;"></textarea>
</div>

<div class="pt-4">
<button type="submit" class="w-full bg-gray-900 dark:bg-white text-white dark:text-black py-4 rounded-xl font-black hover:bg-yellow-500 transition-all shadow-xl uppercase text-sm tracking-widest">
<?= t('ticket_submit_btn', 'Odeslat Ticket') ?>
</button>
</div>
</form>
</div>
</div>

<?php elseif ($action === 'open' && $ticketId > 0): ?>
<?php
$info = $ticketManager->getTicketById($ticketId);

if (!$info): ?>
    <div class="glass p-12 text-center rounded-3xl"><?= t('ticket_not_found', 'Ticket nenalezen.') ?></div>
    <?php else:
    $isAuthor = ($currentUsername === $info['username']);

if (!$isAuthor && !$isAdminOrSupport): ?>
    <div class="glass p-12 text-center rounded-3xl text-red-500 font-bold"><?= t('ticket_no_permission', 'Nemáte oprávnění.') ?></div>
    <?php else:
    $answers = $ticketManager->getTicketAnswers($ticketId);
$isClosed = ((int)$info['status'] === TicketManager::STATUS_CLOSED);
$catName = htmlspecialchars(translateTicketCategory($info['category_name'] ?? t('ticket_cat_unknown', 'Neznámá')));
if ((int)$info['category_id'] === $otherCategoryId) $catName = t('ticket_cat_custom', 'Vlastní důvod');
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

<div class="lg:col-span-8 space-y-6">
<a href="ticket.php?action=list" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-900 dark:hover:text-white text-xs font-bold uppercase tracking-widest transition-colors">
<?= t('ticket_back_list', '← Zpět na seznam') ?>
</a>

<div class="glass rounded-[2.5rem] p-8 relative overflow-hidden">
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
<div>
<div class="flex items-center gap-3 mb-2">
<span class="text-xs font-mono text-gray-400">#<?= htmlspecialchars($info['id']) ?></span>
<span class="bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"><?= $catName ?></span>
</div>
<h1 class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white italic tracking-tight">
<?= htmlspecialchars($info['name']) ?>
</h1>
</div>
<div class="text-right">
<?php
$statusClass = match($info['status']) {
    1 => 'bg-emerald-500 text-black',
    2 => 'bg-yellow-500 text-black',
    3 => 'bg-blue-500 text-white',
    default => 'bg-gray-500 text-white',
};
?>
<span class="<?= $statusClass ?> px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg">
<?= t('ticket_status_prefix', 'Stav:') ?> <?= t($info['status_text'], $info['status_text']) ?>
</span>
</div>
</div>

<div class="space-y-8 mt-10">
<div class="flex gap-4">
<div class="flex-shrink-0">
    <div class="w-12 h-12 rounded-full overflow-hidden ring-2 ring-yellow-500/50 bg-gray-200 flex items-center justify-center">
        <img src="<?= $userManager->getUserAvatarTicket($info['username']); ?>" 
             class="w-full h-full object-cover" alt="Avatar">
    </div>
</div>
<div class="flex-grow">
<div class="flex items-center gap-2 mb-1">
<span class="font-bold text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($info['username']) ?></span>
<span class="text-[10px] font-bold uppercase text-gray-400 bg-gray-100 dark:bg-white/5 px-2 py-0.5 rounded"><?= t('ticket_author_badge', 'Autor') ?></span>
<span class="text-gray-400 text-xs ml-auto"><?= date('d.m.Y H:i', $info['time']) ?></span>
</div>
<div class="bg-white dark:bg-white/5 p-6 rounded-2xl rounded-tl-none border border-gray-100 dark:border-white/5 text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none break-words overflow-hidden">
    <?= $info['initial_message'] ?>
</div>
</div>
</div>

<?php foreach ($answers as $ans):
$isAdminAns = (int)$ans['admin'] === 1;
?>
<div class="flex gap-4 <?= $isAdminAns ? 'flex-row-reverse' : '' ?>">
<div class="flex-shrink-0">
    <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-200 border-2 <?= $isAdminAns ? 'border-emerald-500' : 'border-gray-300 dark:border-white/10' ?>">
        <img src="<?= $userManager->getUserAvatarTicket($ans['username']); ?>" 
             class="w-full h-full object-cover" alt="Avatar">
    </div>
</div>
<div class="flex-grow <?= $isAdminAns ? 'text-right' : '' ?>">
<div class="flex items-center gap-2 mb-1 <?= $isAdminAns ? 'flex-row-reverse' : '' ?>">
<span class="font-bold text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($ans['username']) ?></span>
<?php if($isAdminAns): ?>
<span class="text-[10px] font-bold uppercase text-emerald-600 bg-emerald-500/10 px-2 py-0.5 rounded"><?= t('ticket_support_badge', 'Support') ?></span>
<?php endif; ?>

<span class="text-gray-400 text-xs <?= $isAdminAns ? 'mr-auto' : 'ml-auto' ?> flex items-center gap-2">
    <?= date('d.m.Y H:i', $ans['time']) ?>
    
    <?php if ($isAdminOrSupport): ?>
        <a href="ticket.php?action=open&id=<?= $ticketId ?>&delete_ans_id=<?= $ans['id'] ?>" 
           onclick="return confirm('<?= htmlspecialchars(t('ticket_confirm_delete_answer', 'Opravdu smazat tuto odpověď?'), ENT_QUOTES) ?>')"  
           class="text-red-500 hover:text-red-700 transition-colors" title="Smazat odpověď">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
        </a>
    <?php endif; ?>
</span>

</div>
<div class="inline-block text-left bg-white dark:bg-white/5 p-6 rounded-2xl <?= $isAdminAns ? 'rounded-tr-none border-emerald-500/20 bg-emerald-500/5' : 'rounded-tl-none border-gray-100 dark:border-white/5' ?> border text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-full break-words overflow-hidden">
    <?= $ans['text'] ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if (!$isClosed || $isAdminOrSupport): ?>
<div class="mt-12 pt-8 border-t border-gray-200 dark:border-white/10">
<h3 class="text-sm font-bold uppercase text-gray-400 tracking-widest mb-4"><?= t('ticket_reply_section', 'Odpovědět') ?></h3>
<form method="post" action="ticket.php?action=open&id=<?= htmlspecialchars($info['id']) ?>" onsubmit="return submitQuillForm('answerQuillEditor', 'answer_text')">
<div class="bg-white dark:bg-[#121214] rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 mb-4">
<div id="answerQuillEditor" class="text-gray-900 dark:text-gray-200"></div>
</div>
<textarea name="answer_text" id="answer_text_hidden" style="display:none;"></textarea>
<button type="submit" class="bg-gray-900 dark:bg-white text-white dark:text-black px-8 py-3 rounded-xl font-black hover:bg-yellow-500 transition-all shadow-lg uppercase text-xs tracking-widest">
<?= t('ticket_send_reply_btn', 'Odeslat odpověď') ?>
</button>
</form>
</div>
<?php else: ?>
<div class="mt-8 p-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl text-center font-bold text-sm uppercase tracking-wider">
<?= t('ticket_closed_notice', 'Ticket je uzavřen') ?>
</div>
<?php endif; ?>
</div>
</div>

<div class="lg:col-span-4 space-y-6">
<?php if ($isAdminOrSupport): ?>
<div class="glass rounded-[2rem] p-6 border-l-4 border-l-yellow-500">
<h3 class="font-black text-gray-900 dark:text-white uppercase text-xs tracking-widest mb-4"><?= t('ticket_admin_panel', 'Administrace') ?></h3>
<div class="flex flex-col gap-2">
<?php if (!$isClosed): ?>
<a href="ticket.php?action=close&id=<?= $info['id'] ?>" onclick="return confirm('<?= htmlspecialchars(t('ticket_confirm_close', 'Opravdu uzavřít?'), ENT_QUOTES) ?>')" class="w-full bg-yellow-500 hover:bg-yellow-600 text-black py-3 rounded-xl font-bold text-xs uppercase tracking-wider text-center transition">
<?= t('ticket_close_action', 'Uzavřít Ticket') ?>
</a>
<?php else: ?>
<a href="ticket.php?action=reopen&id=<?= $info['id'] ?>" onclick="return confirm('<?= htmlspecialchars(t('ticket_confirm_reopen', 'Opravdu znovu otevřít?'), ENT_QUOTES) ?>')" class="w-full bg-emerald-500 hover:bg-emerald-600 text-black py-3 rounded-xl font-bold text-xs uppercase tracking-wider text-center transition">
<?= t('ticket_reopen_action', 'Znovu otevřít') ?>
</a>
<?php endif; ?>
<a href="ticket.php?action=delete&id=<?= $info['id'] ?>" onclick="return confirm('<?= htmlspecialchars(t('ticket_confirm_delete', 'Opravdu SMAZAT ticket?'), ENT_QUOTES) ?>')" class="w-full bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white py-3 rounded-xl font-bold text-xs uppercase tracking-wider text-center transition">
<?= t('ticket_delete_action', 'Smazat Ticket') ?>
</a>
</div>
</div>
<?php endif; ?>

<div class="glass rounded-[2rem] p-6">
<h3 class="font-black text-gray-900 dark:text-white uppercase text-xs tracking-widest mb-4"><?= t('ticket_info_panel', 'Informace') ?></h3>
<div class="space-y-4 text-sm">
<div class="flex justify-between border-b border-gray-100 dark:border-white/5 pb-2">
<span class="text-gray-500"><?= t('ticket_created_at', 'Vytvořeno') ?></span>
<span class="font-bold text-gray-900 dark:text-white"><?= date('d. m. Y', $info['time']) ?></span>
</div>
<div class="flex justify-between border-b border-gray-100 dark:border-white/5 pb-2">
<span class="text-gray-500"><?= t('ticket_time_at', 'Čas') ?></span>
<span class="font-bold text-gray-900 dark:text-white"><?= date('H:i', $info['time']) ?></span>
</div>
<div class="flex justify-between">
<span class="text-gray-500"><?= t('ticket_answers_count', 'Odpovědí') ?></span>
<span class="font-bold text-gray-900 dark:text-white"><?= count($answers) ?></span>
</div>
</div>
</div>
</div>

</div>
<?php endif;
endif; ?>
<?php endif; ?>

</main>

<footer class="max-w-7xl mx-auto px-6 mt-20 text-center pb-8">
<div class="h-[1px] bg-gray-300 dark:bg-white/5 w-full mb-8"></div>
<p class="text-[10px] uppercase font-bold text-gray-400 dark:text-gray-700 tracking-[0.4em]"><?= t('footer_rights', '&copy; 2026 Maturitní práce • Všechna práva vyhrazena') ?></p>
</footer>

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

function submitQuillForm(editorId, textareaName) {
    const quill = window[editorId];
    if (!quill) return true;
    const textarea = document.getElementById(textareaName + '_hidden');
    if (!textarea) return true;

    const html = quill.root.innerHTML;
    const emptyContent = ['<p><br></p>', '<p></p>', ''];

    if (emptyContent.includes(html.trim())) {
        alert('Obsah zprávy nesmí být prázdný.');
        return false;
    }
    textarea.value = html;
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const quillConfig = {
        modules: { toolbar: [ [{ 'header': [1, 2, false] }], ['bold', 'italic', 'underline'], ['link', 'image', 'code-block'], [{ 'list': 'ordered'}, { 'list': 'bullet' }] ] },
        placeholder: 'Napište zprávu...',
        theme: 'snow'
    };

    if (document.getElementById('ticketQuillEditor')) {
        window.ticketQuillEditor = new Quill('#ticketQuillEditor', quillConfig);
    }
    if (document.getElementById('answerQuillEditor')) {
        window.answerQuillEditor = new Quill('#answerQuillEditor', quillConfig);
    }

    const catSelect = document.getElementById('categorySelect');
    if(catSelect) {
        const customReasonContainer = document.getElementById('customReasonContainer');
        const customReasonInput = document.getElementById('customReasonInput');
        const ticketNameLabel = document.getElementById('ticketNameLabel');
        const ticketNameInput = document.getElementById('ticketNameInput');
        const OTHER_ID = <?= $otherCategoryId ?>;

        function toggleCustom() {
            if (parseInt(catSelect.value) === OTHER_ID) {
                ticketNameLabel.style.display = 'none';
ticketNameInput.removeAttribute('required');
ticketNameInput.value = '';
customReasonContainer.classList.remove('hidden');
customReasonInput.setAttribute('required', 'required');
            } else {
                ticketNameLabel.style.display = 'block';
ticketNameInput.setAttribute('required', 'required');
customReasonContainer.classList.add('hidden');
customReasonInput.removeAttribute('required');
            }
        }
        catSelect.addEventListener('change', toggleCustom);
        toggleCustom();
    }
});
</script>
</body>
</html>
