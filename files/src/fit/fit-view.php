<?php
// Zobrazení dat z Google Fit pro přihlášeného uživatele
require_once __DIR__ . '/../php/Database.php';
require_once __DIR__ . '/../php/locales.php';

$locale = getLocale();

$user_id = $_SESSION['user_id']; 

if ($user_id === 0) {
    die("Musíte být přihlášen, abyste viděl svá data.");
}

$db = new Database();
$conn = $db->getConnection(); 
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title>Přehled Google Fit Aktivity a Skóre</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

    <h2>🏃 Přehled Aktivity - Uživatel ID: <?php echo $user_id; ?></h2>
    
    <?php
    $sql = "
        SELECT 
            sync_date, 
            activity_type, 
            value_numeric, 
            score,
            (timestamp_end - timestamp_start) / 60000 AS duration_minutes 
        FROM activity_data 
        WHERE user_id = ? 
        AND sync_date >= DATE(NOW() - INTERVAL 7 DAY) 
        ORDER BY sync_date DESC, timestamp_end DESC
    ";
    
    $stmt = $db->executeQuery($sql, 'i', [$user_id]);

    if ($stmt && mysqli_stmt_bind_result($stmt, $sync_date, $activity_type, $value_numeric, $score, $duration_minutes)) {
        
        echo "<table>";
        echo "<tr><th>Datum</th><th>Typ Aktivity</th><th>Doba trvání (min)</th><th>Počet (Kroků/Údaj)</th><th>Skóre</th></tr>";
        
        $total_score_7_days = 0;

        while (mysqli_stmt_fetch($stmt)) {
            $total_score_7_days += $score;
            $duration_minutes = number_format($duration_minutes, 1);
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sync_date) . "</td>";
            echo "<td>" . htmlspecialchars($activity_type) . "</td>";
            echo "<td>" . htmlspecialchars($duration_minutes) . "</td>";
            echo "<td>" . number_format($value_numeric, 0, ',', ' ') . "</td>";
            echo "<td><strong>" . $score . "</strong></td>";
            echo "</tr>";
        }
        
        mysqli_stmt_close($stmt);

        echo "</table>";
        
        echo "<h3>Celkové Skóre za posledních 7 dní: **" . $total_score_7_days . "** bodů</h3>";
        
    } else {
        echo "<p>Zatím nemáte žádná uložená data aktivit z Google Fit.</p>";
    }
    
    ?>

</body>
</html>