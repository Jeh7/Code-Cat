<?php
session_start();
include "db.php";

if (!isset($_SESSION['id'])) {
    echo "Not logged in";
    exit();
}

$user_id = $_SESSION['id'];
$achievement_id = $_GET['id'];

$check = $conn->query("SELECT * FROM user_achievements
                       WHERE user_id='$user_id' AND achievement_id='$achievement_id'");

if ($check->num_rows == 0) {
    $conn->query("INSERT INTO user_achievements (user_id, achievement_id)
                  VALUES ('$user_id', '$achievement_id')");
}
?>
