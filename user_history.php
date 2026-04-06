<?php
// Historie výzev konkrétního uživatele
session_start();
require_once __DIR__ . '/src/php/Database.php';
require_once __DIR__ . '/src/classes/ChallengeManager.php';

// Načtení ID uživatele z URL
$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) die("Uživatel nenalezen.");

$db = new Database();
$cm = new ChallengeManager($db);

// Načtení historie výzěv a jména uživatele
$history = $cm->getUserChallengeHistory($userId);

$conn = $db->getConnection();
$uRes = $conn->query("SELECT username FROM users WHERE id = $userId");
$userData = $uRes->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Historie - <?= htmlspecialchars($userData['username'] ?? 'Neznámý') ?></title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; }
        .completed { color: green; font-weight: bold; }
        .failed { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Historie výzev: <?= htmlspecialchars($userData['username'] ?? 'Neznámý') ?></h1>
    <a href="leaderboard.php">← Zpět na žebříček</a>

    <table>
        <thead>
            <tr>
                <th>Výzva</th>
                <th>Stav</th>
                <th>Výsledek XP</th>
                <th>Datum ukončení</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($history)): ?>
                <tr><td colspan="4">Tento uživatel zatím nemá historii výzev.</td></tr>
            <?php else: ?>
                <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td>
                        <?php if ($row['status'] == 'completed'): ?>
                            <span class="completed">Splněno</span>
                        <?php else: ?>
                            <span class="failed">Nesplněno</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            if ($row['status'] == 'completed') {
                                echo "<span style='color:green'>+" . $row['xp_reward'] . " XP</span>";
                            } else {
                                echo "<span style='color:red'>-" . $row['xp_penalty'] . " XP (Penalizace)</span>";
                            }
                        ?>
                    </td>
                    <td><?= date('d.m.Y H:i', strtotime($row['expires_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>