<?php
include "db.php";

$error = "";

if (isset($_POST['register'])) {
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->query("SELECT * FROM users WHERE username='$username'");
    
    if ($check->num_rows > 0) {
        $error = "Username already exists!";
    } else {
        $sql = "INSERT INTO users (username, password, email) 
                VALUES ('$username', '$password', '$email')";
        
        if ($conn->query($sql)) {
            $user_id = $conn->insert_id;
            header("Location: role.php?id=" . $user_id);
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
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
        <form method="POST" class="login_box">
            <h2>Register</h2>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <?= $error ?>
            <button name="register">Register</button>
            <a href="login.php">Already have an account?</a>
        </form>
    </div>
</body>
