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
    <title>Choose Role</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="tab">
        <a href="index.php">
            <img src="img\logo.png" class="logo">
        </a>
    </div>

    <div class="login_body">
        <form method="post" class="login_box role_box">
            <h2>One More Thing...</h2>
            <p class="form_intro">Choose the role that best matches how you will use Code Cat.</p>
            <div class="role_actions">
                <button name="student">Continue as Student</button>
                <button name="teacher">Continue as Teacher</button>
                <button name="na">Continue without role</button>
            </div>
        </form>
    </div>
</body>
</html>
