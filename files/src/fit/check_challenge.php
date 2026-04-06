<?php
session_start();
require_once __DIR__ . '/../php/Database.php';

$u_id = $_SESSION['user_id'] ?? 0;
$c_id = (int)($_GET['challenge_id'] ?? 0);
$is_init = isset($_GET['init']); 
$debug_mode = isset($_GET['debug']); 

if (!$u_id || !$c_id) die("Chybí parametry.");

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT u.google_refresh_token, uc.id as uc_id, uc.started_at, uc.start_steps, 
               c.goal_steps, c.activity_type, c.xp_reward 
        FROM users u 
        JOIN user_challenges uc ON u.id = uc.user_id
        JOIN challenges c ON uc.challenge_id = c.id
        WHERE u.id = ? AND uc.challenge_id = ? AND uc.status = 'active' 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $u_id, $c_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die("Žádná aktivní výzva nenalezena.");

$user_challenge_id = $data['uc_id']; 

$s_ms = (float)(strtotime($data['started_at']) * 1000);
$e_ms = (float)(time() * 1000);

if (!defined('GOOGLE_CLIENT_ID')) {
    require_once __DIR__ . '/../php/config.php';
}
$client_id = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$ch = curl_init('https://accounts.google.com/o/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $client_id, 'client_secret' => $client_secret,
    'refresh_token' => $data['google_refresh_token'], 'grant_type' => 'refresh_token'
]));
$t_raw = curl_exec($ch);
curl_close($ch);
$t_res = json_decode($t_raw, true);
$atk = is_array($t_res) ? ($t_res['access_token'] ?? null) : null;

if (!$atk) die("Chyba autorizace Google.");

$ch = curl_init('https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $atk, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "aggregateBy"          => [["dataTypeName" => "com.google.step_count.delta"]],
    "bucketByActivityType" => ["minDurationMillis" => 1000],
    "startTimeMillis"      => $s_ms,
    "endTimeMillis"        => $e_ms,
]));
$fit_raw = curl_exec($ch);
curl_close($ch);
$fit_res = json_decode($fit_raw, true);

$challenge_act_type = (int)$data['activity_type'];
$google_raw_steps   = 0;
if (isset($fit_res['bucket'])) {
    foreach ($fit_res['bucket'] as $b) {
        if ((int)($b['activity'] ?? -1) !== $challenge_act_type) continue;
        if (!empty($b['dataset'][0]['point'])) {
            foreach ($b['dataset'][0]['point'] as $p) {
                $google_raw_steps += $p['value'][0]['intVal'] ?? 0;
            }
        }
    }
}

$start_steps = (int)$data['start_steps'];
if ($is_init || $start_steps == 0) {
    $start_steps = $google_raw_steps;
    $upd_start = $conn->prepare("UPDATE user_challenges SET start_steps = ? WHERE id = ?");
    $upd_start->bind_param("ii", $start_steps, $user_challenge_id);
    $upd_start->execute();
}

$current_progress = max(0, $google_raw_steps - $start_steps);

if ($debug_mode) {
    echo "Raw: $google_raw_steps | Start: $start_steps | Progres: $current_progress / " . $data['goal_steps'];
    exit();
}

$db_dist = round($current_progress * 0.000762, 2);
$db_cal = round($current_progress * 0.04, 2);

$ins = $conn->prepare(
    "INSERT INTO challenge_syncs (user_id, challenge_id, steps_count, distance_km, calories, start_sync_ms, last_sync_ms)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       steps_count  = VALUES(steps_count),
       distance_km  = VALUES(distance_km),
       calories     = VALUES(calories),
       last_sync_ms = VALUES(last_sync_ms)"
);
$ins->bind_param("iiidddd", $u_id, $c_id, $google_raw_steps, $db_dist, $db_cal, $s_ms, $e_ms);
$ins->execute();

if ($current_progress >= (int)$data['goal_steps']) {
    $upd = $conn->prepare("UPDATE user_challenges SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $upd->bind_param("i", $user_challenge_id);
    $upd->execute();
    
    $xp_reward = (int)$data['xp_reward'];
    $upd_xp = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
    $upd_xp->bind_param("ii", $xp_reward, $u_id);
    $upd_xp->execute();

    require_once __DIR__ . '/../classes/UserManager.php';
    $uM = new UserManager($db);
    $uM->refreshUserRole($u_id); 

    header("Location: https://matyaskrabica.cz/challenge.php?msg=completed&id=$c_id");
} else {
    header("Location: https://matyaskrabica.cz/challenge.php?view=detail&id=$c_id&sync=ok");
}
exit();