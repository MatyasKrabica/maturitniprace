<?php
// Správa uživatelů (dostupné pro rank 3+)
session_start();

require_once 'src/php/ban_check.php';
require_once 'src/php/Database.php';
require_once 'src/classes/UserManager.php';
require_once 'src/php/settings.php';

// Kontrola oprávnění (vyžadován rank 3+)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rank']) || $_SESSION['user_rank'] < 3) {
    header("Location: dashboard.php"); 
    exit;
}

$database = new Database(); 
$conn = $database->getConnection(); 

$edit_mode = false;
$user_to_edit = null;
$msg = "";
$msg_type = ""; 

// Uložení upraveného uživatele
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = intval($_POST['user_id']);
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    $user_rank = intval($_POST['user_rank']);
    $role = intval($_POST['role']); 
    
    $is_banned = isset($_POST['is_banned']) ? 1 : 0;
    $banned_until = !empty($_POST['banned_until']) ? $_POST['banned_until'] : null;

    $country_code = !empty($_POST['country_code']) ? $_POST['country_code'] : null;
    $region_id = !empty($_POST['region_id']) ? intval($_POST['region_id']) : null;
    $city_id = !empty($_POST['city_id']) ? intval($_POST['city_id']) : null;

    $sql = "UPDATE users SET 
        username=?, email=?, first_name=?, last_name=?, 
        user_rank=?, role=?, is_banned=?, banned_until=?,
        country_code=?, region_id=?, city_id=?
        WHERE id=?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) die("Chyba SQL: " . $conn->error);

    $stmt->bind_param("ssssiiisiiii", 
        $username, $email, $first_name, $last_name, 
        $user_rank, $role, $is_banned, $banned_until,
        $country_code, $region_id, $city_id, 
        $id
    );

    if ($stmt->execute()) {
        $msg = "Uživatel aktualizován.";
        $msg_type = "success";
        
        if ($id == $_SESSION['user_id']) {
                $_SESSION['user_rank'] = $user_rank;
                $_SESSION['role'] = $role;
        }
    } else {
        $msg = "Chyba DB: " . $stmt->error;
        $msg_type = "error";
    }
    $stmt->close();
}

if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id == $_SESSION['user_id']) {
        $msg = "Nemůžete smazat svůj vlastní účet!";
        $msg_type = "error";
    } else {
        $check = $conn->query("SELECT user_rank FROM users WHERE id=$del_id")->fetch_assoc();
        if ($check && $check['user_rank'] >= 4 && $_SESSION['user_rank'] < 4) {
             $msg = "Nemáte oprávnění smazat Majitele.";
             $msg_type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute()) {
                $msg = "Uživatel smazán.";
                $msg_type = "success";
            } else {
                $msg = "Chyba: " . $conn->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user_to_edit = $res->fetch_assoc();
        $edit_mode = true;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Správa uživatelů</title>
    <style>
        body { font-family: sans-serif; background-color: #eee; color: #000; padding: 20px; margin: 0; }
        .container { background: #fff; border: 1px solid #555; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h2, h3, h4 { margin-top: 0; border-bottom: 2px solid #555; padding-bottom: 10px; color: #333; }
        hr { border: 0; border-top: 1px solid #ccc; margin: 20px 0; }
        .form-row { display: flex; margin-bottom: 10px; }
        .form-col { flex: 1; padding-right: 15px; }
        .form-col:last-child { padding-right: 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        input[type="text"], input[type="email"], select, input[type="datetime-local"] { width: 100%; padding: 5px; border: 1px solid #555; background: #fff; box-sizing: border-box; border-radius: 0; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #555; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #999; text-align: left; vertical-align: middle; }
        th { background-color: #555; color: #fff; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .btn { display: inline-block; padding: 6px 12px; text-decoration: none; color: #fff; font-size: 13px; margin-right: 5px; border: none; cursor: pointer; border-radius: 0; }
        .btn-back { background-color: #555; margin-bottom: 15px; }
        .btn-back:hover { background-color: #333; }
        .btn-edit { background-color: #0056b3; }
        .btn-edit:hover { background-color: #003d80; }
        .btn-del { background-color: #444; }
        .btn-del:hover { background-color: #222; }
        .alert { padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; }
        .alert.success { background-color: #dff0d8; color: #2b542c; border-color: #d6e9c6; }
        .alert.error { background-color: #e2e2e2; color: #333; border-color: #ccc; }
        .badge { display: inline-block; padding: 2px 5px; font-size: 11px; color: #fff; font-weight: bold; border-radius: 0; }
        .rank-0 { background-color: #777; }
        .rank-1 { background-color: #5bc0de; }
        .rank-2 { background-color: #28a745; }
        .rank-3 { background-color: #0056b3; }
        .rank-4 { background-color: #333; }
        .edit-box { background: #f0f0f0; padding: 15px; border: 2px solid #0056b3; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn btn-back">← Zpět na web</a>
    <h2>Správa uživatelů</h2>

    <?php if ($msg): ?>
        <div class="alert <?php echo $msg_type; ?>"> <?php echo $msg; ?> </div>
    <?php endif; ?>

    <?php if ($edit_mode && $user_to_edit): ?>
    <div class="edit-box">
        <h3>Upravit uživatele: <?php echo htmlspecialchars($user_to_edit['username']); ?></h3>
        <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo $user_to_edit['id']; ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <label>Username:</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user_to_edit['username']); ?>" required>
                </div>
                <div class="form-col">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Jméno:</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_to_edit['first_name']); ?>">
                </div>
                <div class="form-col">
                    <label>Příjmení:</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_to_edit['last_name']); ?>">
                </div>
            </div>
            
            <hr>
            
            <div class="form-row">
                <div class="form-col">
                    <label>Oprávnění (Rank):</label>
                    <select name="user_rank">
                        <?php foreach (Settings::RANKS as $val => $name): ?>
                            <option value="<?= $val ?>" <?= ($user_to_edit['user_rank'] == $val) ? 'selected' : '' ?>>
                                <?= $val ?> - <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-col">
                    <label>Úroveň (Role/Level):</label>
                    <select name="role">
                        <?php foreach (Settings::ROLES as $val => $name): ?>
                            <option value="<?= $val ?>" <?= ($user_to_edit['role'] == $val) ? 'selected' : '' ?>>
                                <?= $val ?> - <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>

            <h4>Adresa</h4>
            <div class="form-row">
                <div class="form-col">
                    <label>Země:</label>
                    <select name="country_code" id="countrySelect">
                        <option value="">-- Načítám --</option>
                    </select>
                </div>
                <div class="form-col">
                    <label>Kraj:</label>
                    <select name="region_id" id="regionSelect">
                        <option value="">-- Vyberte zemi --</option>
                    </select>
                </div>
                <div class="form-col">
                    <label>Město:</label>
                    <select name="city_id" id="citySelect">
                        <option value="">-- Vyberte kraj --</option>
                    </select>
                </div>
            </div>

            <hr>

            <div style="background: #e2e2e2; padding: 10px; border: 1px solid #999;">
                <label>
                    <input type="checkbox" name="is_banned" <?php echo ($user_to_edit['is_banned'] == 1) ? 'checked' : ''; ?> style="width:auto;">
                    <strong>ZABANOVÁN</strong>
                </label>
                <div style="margin-top: 10px;">
                    <label>Ban vyprší:</label>
                    <input type="datetime-local" name="banned_until" value="<?php echo $user_to_edit['banned_until'] ? date('Y-m-d\TH:i', strtotime($user_to_edit['banned_until'])) : ''; ?>">
                </div>
            </div>

            <br>
            <button type="submit" name="update_user" class="btn btn-edit">Uložit změny</button>
            <a href="user_management.php" class="btn btn-back">Zrušit</a>
        </form>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Uživatel</th>
                <th>Level / Rank</th>
                <th>Lokace</th>
                <th>Stav</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT u.*, r.name as region_name, c.name as city_name 
                    FROM users u
                    LEFT JOIN regions r ON u.region_id = r.id
                    LEFT JOIN cities c ON u.city_id = c.id
                    ORDER BY u.user_rank DESC, u.id DESC";
            $result = mysqli_query($conn, $sql);

            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rankName = Settings::getRankName($row['user_rank']);
                    $roleName = Settings::getRoleName($row['role']);
                    $rankClass = "rank-" . $row['user_rank'];
                    $banStatus = ($row['is_banned'] == 1) 
                        ? "<span style='color:black;font-weight:bold;background:#ccc;padding:2px;'>BANNED</span>" 
                        : "<span style='color:green;'>Aktivní</span>";

                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>" . htmlspecialchars($row['username']) . "<br><small style='color:#666;'>" . htmlspecialchars($row['email']) . "</small></td>";
                    echo "<td><strong>$roleName</strong> <small>(Lvl {$row['role']})</small><br><span class='badge $rankClass'>$rankName</span></td>";
                    echo "<td>" . htmlspecialchars($row['country_code'] ?? '-') . " / " . htmlspecialchars($row['city_name'] ?? '-') . "</td>";
                    echo "<td>$banStatus</td>";
                    echo "<td>
                        <a href='user_management.php?edit={$row['id']}' class='btn btn-edit'>Upravit</a>
                        <a href='user_management.php?delete={$row['id']}' class='btn btn-del' onclick='return confirm(\"Smazat?\")'>Smazat</a>
                    </td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<script>
async function fetchJsonSafe(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error("Non-JSON:", text);
        throw new Error("Invalid JSON");
    }
}

function fillSelect(select, data, valueKey, labelKey) {
    select.innerHTML = '<option value="">-- Vyber --</option>';
    data.forEach(row => {
        const opt = document.createElement('option');
        opt.value = row[valueKey];
        opt.textContent = row[labelKey];
        select.appendChild(opt);
    });
    select.disabled = data.length === 0;
}

const savedCountry = "<?php echo $user_to_edit['country_code'] ?? ''; ?>";
const savedRegion = "<?php echo $user_to_edit['region_id'] ?? ''; ?>";
const savedCity = "<?php echo $user_to_edit['city_id'] ?? ''; ?>";

async function loadCountries() {
    try {
        const data = await fetchJsonSafe('src/ajax/ajax_countries.php');
        const countrySelect = document.getElementById('countrySelect');
        fillSelect(countrySelect, data, 'code', 'name');
        
        if (savedCountry) {
            countrySelect.value = savedCountry;
            loadRegions(savedCountry, savedRegion);
        }

    } catch (e) {
        console.error('Err countries', e);
    }
}

async function loadRegions(countryCode, oldRegionId = null) {
    const regionSelect = document.getElementById('regionSelect');
    const citySelect = document.getElementById('citySelect');
    regionSelect.innerHTML = '<option value="">-- Vyber kraj --</option>';
    citySelect.innerHTML = '<option value="">-- Vyber město --</option>';
    
    if (!countryCode) return;

    try {
        const data = await fetchJsonSafe('src/ajax/ajax_regions.php?country_code=' + encodeURIComponent(countryCode));
        fillSelect(regionSelect, data, 'id', 'name');
        
        if (oldRegionId) {
            regionSelect.value = oldRegionId;
            loadCities(oldRegionId, countryCode, savedCity);
        }
    } catch (e) {
        console.error('Err regions', e);
    }
}

async function loadCities(regionId, countryCode, oldCityId = null) {
    const citySelect = document.getElementById('citySelect');
    citySelect.innerHTML = '<option value="">-- Vyber město --</option>';
    
    if (!regionId || !countryCode) return;

    try {
        const data = await fetchJsonSafe(
            'src/ajax/ajax_cities.php?region_id=' + encodeURIComponent(regionId) +
            '&country_code=' + encodeURIComponent(countryCode)
        );
        fillSelect(citySelect, data, 'id', 'name');
        
        if (oldCityId) {
            citySelect.value = oldCityId;
        }
    } catch (e) {
        console.error('Err cities', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const countrySelect = document.getElementById('countrySelect');
    const regionSelect = document.getElementById('regionSelect');

    if (countrySelect) {
        loadCountries();

        countrySelect.addEventListener('change', () => {
            loadRegions(countrySelect.value);
        });

        regionSelect.addEventListener('change', () => {
            loadCities(regionSelect.value, countrySelect.value);
        });
    }
});
</script>

</body>
</html>