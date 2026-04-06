<?php
// Google Fit OAuth 2.0 – callback (výměna kódu za token)
require_once __DIR__ . '/../php/Database.php';
if (!defined('GOOGLE_CLIENT_ID')) {
    require_once __DIR__ . '/../php/config.php';
}

$client_id     = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$redirect_uri  = 'https://matyaskrabica.cz/src/fit/fit-callback.php';
$token_url     = 'https://accounts.google.com/o/oauth2/token';

$activity_weights = [
    7  => ['name' => 'Chůze',     'weight' => 1.0],
    8  => ['name' => 'Běh',       'weight' => 1.5],
    48 => ['name' => 'Turistika', 'weight' => 1.2],
];

$db   = new Database();
$conn = $db->getConnection();

// Ověření callback parametrů od Google
if (!isset($_GET['code'])) die("Chyba autorizace.");
$user_id = (int)$_GET['state'];

// Výměna autorizačního kódu za access + refresh token
$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $_GET['code'],
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code',
]));
$raw = curl_exec($ch);
curl_close($ch);
$data = json_decode($raw, true);
if (!is_array($data)) die("Chyba komunikace s Google.");
if (isset($data['error'])) die("Chyba: " . $data['error_description']);

$access_token      = $data['access_token'];
$refresh_token     = $data['refresh_token'] ?? null;
$token_expire_time = date('Y-m-d H:i:s', time() + $data['expires_in']);

// Načtení dat z Google Fit za posledních 7 dní
$end_time   = time() * 1000;
$start_time = (time() - (7 * 24 * 60 * 60)) * 1000;

$ch = curl_init('https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "aggregateBy"    => [
        ["dataTypeName" => "com.google.step_count.delta"],
        ["dataTypeName" => "com.google.activity.segment"],
    ],
    "bucketByTime"    => ["durationMillis" => 86400000],
    "startTimeMillis" => $start_time,
    "endTimeMillis"   => $end_time,
]));
$fit_raw  = curl_exec($ch);
curl_close($ch);
$fit_data = json_decode($fit_raw, true);

// Uložení kroků a aktivity do DB
if (isset($fit_data['bucket'])) {
    foreach ($fit_data['bucket'] as $bucket) {
        $date  = date('Y-m-d', (int)($bucket['startTimeMillis'] / 1000));
        $steps = 0;
        if (!empty($bucket['dataset'][0]['point'])) {
            foreach ($bucket['dataset'][0]['point'] as $point) {
                $steps += $point['value'][0]['intVal'] ?? 0;
            }
        }

        if ($steps > 0) {
            $durations = [];
            if (!empty($bucket['dataset'][1]['point'])) {
                foreach ($bucket['dataset'][1]['point'] as $p) {
                    $type             = $p['value'][0]['intVal'] ?? 7;
                    $durations[$type] = ($durations[$type] ?? 0)
                        + ((int)$p['endTimeNanos'] - (int)$p['startTimeNanos']);
                }
            }
            $actId   = 7;
            if (!empty($durations)) { arsort($durations); $actId = (int)array_key_first($durations); }
            $actInfo = $activity_weights[$actId] ?? ['name' => 'Chůze', 'weight' => 1.0];

            $calculated_score = (int)floor(($steps / 100) * $actInfo['weight']);

            $sql  = "INSERT INTO activity_data (user_id, sync_date, activity_type, value_numeric, score)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE value_numeric = VALUES(value_numeric), score = VALUES(score)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'issii', $user_id, $date, $actInfo['name'], $steps, $calculated_score);
            mysqli_stmt_execute($stmt);
        }
    }
}

// Přepočet celkového skóre uživatele ze všech aktivit
$upd = mysqli_prepare($conn,
    "UPDATE users SET total_score = (SELECT COALESCE(SUM(score), 0) FROM activity_data WHERE user_id = ?) WHERE id = ?"
);
mysqli_stmt_bind_param($upd, 'ii', $user_id, $user_id);
mysqli_stmt_execute($upd);

// Uložení tokenu do DB a přesměrování zpět na profil
$update_sql = "UPDATE users SET google_access_token = ?, google_token_expire = ?, fit_status = 1"
    . ($refresh_token ? ", google_refresh_token = ?" : "")
    . " WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_sql);
if ($refresh_token) {
    mysqli_stmt_bind_param($stmt, 'sssi', $access_token, $token_expire_time, $refresh_token, $user_id);
} else {
    mysqli_stmt_bind_param($stmt, 'ssi', $access_token, $token_expire_time, $user_id);
}
mysqli_stmt_execute($stmt);

header("Location: https://matyaskrabica.cz/user_profile.php");
exit();