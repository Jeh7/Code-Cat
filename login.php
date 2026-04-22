<?php
session_start();
include "db.php";

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['id'] = $user['id'];
                $_SESSION['user'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['reg_date'] = $user['register_date'];
                $_SESSION['role'] = $user['role'];
                $stmt->close();
                header("Location: index.php");
                exit();
            } else {
                $error = "Wrong password";
            }
        } else {
            $error = "User not found";
        }

        $stmt->close();
    } else {
        $error = "Login is temporarily unavailable.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
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
        <form method="POST" class="login_box">
            <h2>Login</h2>
            <p class="form_intro">Sign in to continue your Code Cat progress.</p>

            <label for="username">Username</label>
            <input type="text" name="username" id="username" required>

            <label for="pass">Password</label>
            <input type="password" name="password" required id="pass">

            <?php if ($error !== ""): ?>
                <div class="form_error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <button name="login">Log In</button>
            <a class="form_link" href="register.php">Don't have an account? Register here.</a>
        </form>
    </div>
</body>
</html>
