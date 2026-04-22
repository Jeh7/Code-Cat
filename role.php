<?php
include "db.php";

$id = (int)($_GET["id"] ?? 0);
$role = "";

if (isset($_POST['student'])) {
    $role = "student";
}

if (isset($_POST['teacher'])) {
    $role = "teacher";
}

if (isset($_POST['na'])) {
    $role = "na";
}

if ($id > 0 && $role !== "") {
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $role, $id);
        $updated = $stmt->execute();
        $stmt->close();
    } else {
        $updated = false;
    }

    if ($updated) {
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
            <div class="page_back_row is-tight">
                <a class="secondary_button" href="register.php">Back to Register</a>
            </div>
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
