<?php
include "db.php";

$id = $_GET["id"];
$sql = "";

if (isset($_POST['student'])) {
    $sql = "UPDATE users SET role='student' WHERE id=$id";
}

if (isset($_POST['teacher'])) {
    $sql = "UPDATE users SET role='teacher' WHERE id=$id";
}

if (isset($_POST['na'])) {
    $sql = "UPDATE users SET role='na' WHERE id=$id";
}

if (!empty($sql)) {
    if ($conn->query($sql)) {
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="tab">
        <a href="index.php">
            <img src="img\logo.png" class="logo">
        </a>
    </div>

    <div class="login_body">
        <form method="post" class="login_box">
            <h2>One More Thing...</h2>
            Sign up as?
            <button name="student">As a Student</button>
            <button name="teacher">As a Teacher</button>
            <button name="na">Not Applicable</button>
        </form>
    </div>
</body>