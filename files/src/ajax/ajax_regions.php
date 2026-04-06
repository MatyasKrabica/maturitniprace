<?php
// AJAX endpoint – seznam krajů pro zvolenou zemi
require_once __DIR__ . '/../php/Database.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$conn = $db->getConnection();

// Kontrola vstupního parametru
$country = isset($_GET['country_code']) ? $_GET['country_code'] : '';
if ($country === '') {
    echo json_encode([]);
    exit;
}

// Dotaz na kraje pro zvolenou zemi
$stmt = mysqli_prepare($conn, "SELECT id, name FROM regions WHERE country_code = ? ORDER BY name");
mysqli_stmt_bind_param($stmt, 's', $country);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$out = [];
while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
echo json_encode($out);