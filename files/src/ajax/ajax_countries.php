<?php
// AJAX endpoint – seznam zemí pro formuláře
require_once __DIR__ . '/../php/Database.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$conn = $db->getConnection();

$res = mysqli_query($conn, "SELECT DISTINCT country_code AS code, country_code AS name FROM regions ORDER BY country_code");
$countries = [];
while ($r = mysqli_fetch_assoc($res)) {
    $countries[] = [
        'code' => $r['code'],
        'name' => ($r['code'] === 'CZ' ? 'Česká republika' : ($r['code'] === 'SK' ? 'Slovensko' : $r['code']))
    ];
}
echo json_encode($countries);