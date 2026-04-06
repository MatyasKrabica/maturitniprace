<?php
// Google Fit OAuth 2.0 – přesměrování na souhlas
session_start();
if (!defined('GOOGLE_CLIENT_ID')) {
    require_once __DIR__ . '/../php/config.php';
}

$client_id = GOOGLE_CLIENT_ID;
$redirect_uri = 'https://matyaskrabica.cz/src/fit/fit-callback.php';

$local_user_id = $_SESSION['user_id'] ?? 0;

if ($local_user_id == 0) {
    die("Chyba: Uživatel není přihlášen. Přihlaste se prosím do aplikace.");
}

$scopes = [
    'https://www.googleapis.com/auth/fitness.activity.read',
    'https://www.googleapis.com/auth/fitness.body.read',
    'https://www.googleapis.com/auth/fitness.location.read',
    'https://www.googleapis.com/auth/userinfo.profile',
    'openid'
];

$auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => implode(' ', $scopes),
    'access_type'   => 'offline',
    'prompt'        => 'consent',
    'state'         => $local_user_id
]);

header('Location: ' . $auth_url);
exit;