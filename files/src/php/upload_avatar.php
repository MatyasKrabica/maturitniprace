<?php
// Nahrání a uložení avataru uživatele
session_start();
require_once 'Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: https://matyaskrabica.cz/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {

    $userId = $_SESSION['user_id'];
    $file = $_FILES['avatar'];
    $db = new Database();
    $conn = $db->getConnection();

    $uploadDir = __DIR__ . '/../uploads/avatar/';
    $defaultAvatar = '1.png';

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: https://matyaskrabica.cz/user_profile.php?error=upload_error");
        exit();
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        header("Location: https://matyaskrabica.cz/user_profile.php?error=too_large");
        exit();
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);

    if (!in_array($detectedType, $allowedTypes)) {
        header("Location: https://matyaskrabica.cz/user_profile.php?error=invalid_type");
        exit();
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = "avatar_" . $userId . "_" . time() . "." . $extension;
    $targetPath = $uploadDir . $newFileName;

    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();

    if ($userData && !empty($userData['profile_image'])) {

        $oldAvatar = basename($userData['profile_image']);

        if ($oldAvatar !== $defaultAvatar) {
            $oldFilePath = $uploadDir . $oldAvatar;

            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {

        $updateStmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newFileName, $userId);

        if ($updateStmt->execute()) {
            header("Location: https://matyaskrabica.cz/user_profile.php?success=avatar_updated");
        } else {
            header("Location: https://matyaskrabica.cz/user_profile.php?error=db_error");
        }

    } else {
        header("Location: https://matyaskrabica.cz/user_profile.php?error=move_failed");
    }

} else {
    header("Location: https://matyaskrabica.cz/user_profile.php");
}

exit();
