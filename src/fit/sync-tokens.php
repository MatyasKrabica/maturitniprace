<?php
// Synchronizace kroků z Google Fit pro všechny uživatele
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../php/Database.php';
require_once __DIR__ . '/../classes/ChallengeManager.php';
if (!defined('GOOGLE_CLIENT_ID')) {
    require_once __DIR__ . '/../php/config.php';
}

$client_id     = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$token_url     = 'https://accounts.google.com/o/oauth2/token';
$fit_api_url   = 'https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate';

const ACTIVITY_WEIGHTS = [
    7  => ['name' => 'Chůze',     'weight' => 1.0, 'stride_km' => 0.000762, 'kcal_per_step' => 0.040],
    8  => ['name' => 'Běh',       'weight' => 1.5, 'stride_km' => 0.001400, 'kcal_per_step' => 0.065],
    48 => ['name' => 'Turistika', 'weight' => 1.2, 'stride_km' => 0.000813, 'kcal_per_step' => 0.050],
];

$db = new Database();
$challengeManager = new ChallengeManager($db);
synchronizovat_uzivatele($db, $challengeManager, $client_id, $client_secret, $token_url, $fit_api_url);

function synchronizovat_uzivatele(Database $db, ChallengeManager $cM, $client_id, $client_secret, $token_url, $fit_api_url) {
    echo "[" . date('Y-m-d H:i:s') . "] Spouštění synchronizace...\n";

    $stmt = $db->getConnection()->prepare(
        "SELECT id, google_refresh_token FROM users WHERE fit_status = 1 AND google_refresh_token IS NOT NULL"
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $count = 0;
    while ($user = $result->fetch_assoc()) {
        $user_id    = $user['id'];
        $token_data = obnovit_access_token($user['google_refresh_token'], $client_id, $client_secret, $token_url);

        if ($token_data) {
            $new_access_token = $token_data['access_token'];
            $expire_date      = date('Y-m-d H:i:s', time() + ($token_data['expires_in'] ?? 3600));

            $upd = $db->getConnection()->prepare("UPDATE users SET google_access_token = ?, google_token_expire = ? WHERE id = ?");
            $upd->bind_param("ssi", $new_access_token, $expire_date, $user_id);
            $upd->execute();

            nacist_a_zpracovat_data($db, $cM, $user_id, $new_access_token, $fit_api_url);
            echo "[" . date('Y-m-d H:i:s') . "] OK user_id=$user_id\n";
            $count++;
        } else {
            $db->getConnection()->query("UPDATE users SET fit_status = 0 WHERE id = $user_id");
            echo "[" . date('Y-m-d H:i:s') . "] FAIL user_id=$user_id (refresh token neplatny, fit_status=0)\n";
        }
    }
    echo "[" . date('Y-m-d H:i:s') . "] Hotovo. Synchronizovano: $count uzivatel(u).\n";
}

function obnovit_access_token($refresh_token, $client_id, $client_secret, $token_url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'refresh_token' => $refresh_token,
        'grant_type'    => 'refresh_token',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return (is_array($data) && !empty($data['access_token'])) ? $data : null;
}

function get_dominant_activity(array $bucket): int {
    $durations = [];
    if (!empty($bucket['dataset'][1]['point'])) {
        foreach ($bucket['dataset'][1]['point'] as $p) {
            $type              = $p['value'][0]['intVal'] ?? 7;
            $durations[$type]  = ($durations[$type] ?? 0)
                + ((int)$p['endTimeNanos'] - (int)$p['startTimeNanos']);
        }
    }
    if (empty($durations)) return 7;
    arsort($durations);
    return (int)array_key_first($durations);
}

function nacist_a_zpracovat_data(Database $db, ChallengeManager $cM, $user_id, $access_token, $api_url) {
    $now        = (int)round(microtime(true) * 1000);
    $headers    = ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json'];
    $aggregate  = [
        ["dataTypeName" => "com.google.step_count.delta"],
        ["dataTypeName" => "com.google.activity.segment"],
    ];

    $startToday = (int)round(strtotime('today midnight') * 1000);
    $data_today = curl_api_call($api_url, $headers, [
        "aggregateBy"    => $aggregate,
        "bucketByTime"   => ["durationMillis" => 86400000],
        "startTimeMillis" => $startToday,
        "endTimeMillis"   => $now,
    ]);

    if ($data_today === null) {
        echo "[" . date('Y-m-d H:i:s') . "] SKIP user_id=$user_id: API nedostupna\n";
        return;
    }
    if (!empty($data_today['__http_error'])) {
        $code = $data_today['__http_error'];
        echo "[" . date('Y-m-d H:i:s') . "] SKIP user_id=$user_id: HTTP $code\n";
        if ($code === 401 || $code === 403) {
            $db->getConnection()->query("UPDATE users SET fit_status = 0 WHERE id = $user_id");
        }
        return;
    }

    if (isset($data_today['bucket'])) {
        foreach ($data_today['bucket'] as $bucket) {
            $stepsVal = 0;
            if (!empty($bucket['dataset'][0]['point'])) {
                foreach ($bucket['dataset'][0]['point'] as $p) {
                    $stepsVal += $p['value'][0]['intVal'] ?? 0;
                }
            }
            if ($stepsVal > 0) {
                $actId   = get_dominant_activity($bucket);
                $actInfo = ACTIVITY_WEIGHTS[$actId] ?? ['name' => 'Chůze', 'weight' => 1.0];
                $date    = date('Y-m-d', (int)($bucket['startTimeMillis'] / 1000));
                save_activity($db, $user_id, $bucket['startTimeMillis'], $bucket['endTimeMillis'], $actInfo['name'], $stepsVal, $actInfo['weight'], $date);
            }
        }
    }

    $stmt_active = $db->getConnection()->prepare(
        "SELECT uc.id as uc_id, uc.challenge_id, uc.started_at, uc.start_steps, uc.expires_at,
                c.goal_steps, c.xp_reward, c.xp_penalty, c.activity_type
         FROM user_challenges uc
         JOIN challenges c ON uc.challenge_id = c.id
         WHERE uc.user_id = ? AND uc.status = 'active'
         LIMIT 1"
    );
    $stmt_active->bind_param("i", $user_id);
    $stmt_active->execute();
    $active = $stmt_active->get_result()->fetch_assoc();

    if (!$active) return;

    $challengeStartMs    = (int)round(strtotime($active['started_at']) * 1000);
    $challengeActType    = (int)$active['activity_type'];
    $data_challenge      = curl_api_call($api_url, $headers, [
        "aggregateBy"          => [["dataTypeName" => "com.google.step_count.delta"]],
        "bucketByActivityType" => ["minDurationMillis" => 1000],
        "startTimeMillis"      => $challengeStartMs,
        "endTimeMillis"        => $now,
    ]);

    if ($data_challenge === null || !empty($data_challenge['__http_error'])) {
        echo "[" . date('Y-m-d H:i:s') . "] SKIP user_id=$user_id: chyba API pri nacitani vyzvy\n";
        return;
    }

    $totalStepsSinceStart = 0;
    if (isset($data_challenge['bucket'])) {
        foreach ($data_challenge['bucket'] as $b) {
            if ((int)($b['activity'] ?? -1) !== $challengeActType) continue;
            if (!empty($b['dataset'][0]['point'])) {
                foreach ($b['dataset'][0]['point'] as $p) {
                    $totalStepsSinceStart += $p['value'][0]['intVal'] ?? 0;
                }
            }
        }
    }

    $startSteps = $active['start_steps'];
    if ($startSteps === null) {
        $startSteps = $totalStepsSinceStart;
        $upd = $db->getConnection()->prepare("UPDATE user_challenges SET start_steps = ? WHERE id = ?");
        $upd->bind_param("ii", $startSteps, (int)$active['uc_id']);
        $upd->execute();
    } else {
        $startSteps = (int)$startSteps;
    }

    $progress = max(0, $totalStepsSinceStart - $startSteps);
    $uid      = (int)$user_id;
    $cid      = (int)$active['challenge_id'];
    $actWeight = ACTIVITY_WEIGHTS[$challengeActType] ?? ACTIVITY_WEIGHTS[7];
    $db_dist   = round($progress * $actWeight['stride_km'], 2);
    $db_cal    = round($progress * $actWeight['kcal_per_step'], 2);

    $ins = $db->getConnection()->prepare(
        "INSERT INTO challenge_syncs (user_id, challenge_id, steps_count, distance_km, calories, start_sync_ms, last_sync_ms)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           steps_count = VALUES(steps_count),
           distance_km = VALUES(distance_km),
           calories    = VALUES(calories),
           last_sync_ms = VALUES(last_sync_ms)"
    );
    $ins->bind_param("iiidddd", $uid, $cid, $progress, $db_dist, $db_cal, $challengeStartMs, $now);
    $ins->execute();

    if ($progress >= (int)$active['goal_steps']) {
        $cM->completeChallenge($uid, (int)$active['uc_id'], (int)$active['xp_reward']);
        echo "[" . date('Y-m-d H:i:s') . "] user_id=$uid: vyzva {$active['uc_id']} SPLNENA (+{$active['xp_reward']} XP)\n";
    } elseif (time() > strtotime($active['expires_at'])) {
        $cM->cancelChallenge($uid, (int)$active['uc_id'], (int)$active['xp_penalty']);
        echo "[" . date('Y-m-d H:i:s') . "] user_id=$uid: vyzva {$active['uc_id']} EXPIROVANA (-{$active['xp_penalty']} XP)\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] user_id=$uid: progress=$progress/{$active['goal_steps']} kroku\n";
    }
}

function save_activity($db, $uid, $start, $end, $type, $val, $weight, $date) {
    $score = (int)floor(($val / 100) * $weight);
    $s_str = (string)$start;
    $e_str = (string)$end;

    // Uložení aktivity do activity_data
    $stmt  = $db->getConnection()->prepare(
        "INSERT INTO activity_data (user_id, timestamp_start, timestamp_end, activity_type, value_numeric, score, sync_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           value_numeric   = VALUES(value_numeric),
           score           = VALUES(score),
           timestamp_start = VALUES(timestamp_start),
           timestamp_end   = VALUES(timestamp_end)"
    );
    $stmt->bind_param("isssiis", $uid, $s_str, $e_str, $type, $val, $score, $date);
    $stmt->execute();

    // Přepočet celkového skóre uživatele a aktualizace levelu
    $upd = $db->getConnection()->prepare(
        "UPDATE users
         SET total_score = (SELECT COALESCE(SUM(score), 0) FROM activity_data WHERE user_id = ?)
         WHERE id = ?"
    );
    $upd->bind_param("ii", $uid, $uid);
    $upd->execute();
}

function curl_api_call($url, $headers, $payload): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp     = curl_exec($ch);
    $errno    = curl_errno($ch);
    $errMsg   = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno !== 0 || $resp === false) {
        error_log("[Fit Sync] cURL chyba ($errno): $errMsg");
        return null;
    }
    if ($httpCode === 401 || $httpCode === 403) {
        return ['__http_error' => $httpCode];
    }
    if ($httpCode === 429) {
        sleep(2);
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("[Fit Sync] HTTP $httpCode pro $url");
        return null;
    }
    return json_decode($resp, true) ?? [];
}