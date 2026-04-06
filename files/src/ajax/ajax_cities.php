<?php
// AJAX endpoint – seznam měst pro zvolený kraj a zemi
require_once __DIR__ . '/../php/Database.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$conn = $db->getConnection();

// Kontrola vstupních parametrů
$region_id = isset($_GET['region_id']) ? (int)$_GET['region_id'] : 0;
$country = isset($_GET['country_code']) ? $_GET['country_code'] : '';

if ($region_id <= 0 || $country === '') {
    echo json_encode([]);
    exit;
}

// Dotaz na města
$stmt = mysqli_prepare($conn, "SELECT id, name FROM cities WHERE region_id = ? AND country_code = ? ORDER BY name");
mysqli_stmt_bind_param($stmt, 'is', $region_id, $country);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$out = [];
while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
echo json_encode($out);