<?php
session_start();
include "db.php";

if (!isset($_SESSION['id'])) {
    echo "Not logged in";
    exit();
}

$user_id = (int)($_SESSION['id'] ?? 0);
$achievement_id = (int)($_GET['id'] ?? 0);

$check = $conn->prepare("
    SELECT id
    FROM user_achievements
    WHERE user_id = ? AND achievement_id = ?
    LIMIT 1
");

if ($check) {
    $check->bind_param("ii", $user_id, $achievement_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result && $check_result->num_rows == 0) {
        $insert = $conn->prepare("
            INSERT INTO user_achievements (user_id, achievement_id)
            VALUES (?, ?)
        ");
        if ($insert) {
            $insert->bind_param("ii", $user_id, $achievement_id);
            $insert->execute();
            $insert->close();
        }
    }

    $check->close();
}
?>
