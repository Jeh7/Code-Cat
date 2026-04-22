<?php
include "db.php";

$error = "";

if (isset($_POST['register'])) {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");

    if ($check) {
        $check->bind_param("s", $username);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error = "Username already exists!";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, email)
                VALUES (?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param("sss", $username, $password, $email);

                if ($stmt->execute()) {
                    $user_id = (int)$stmt->insert_id;
                    $stmt->close();
                    $check->close();
                    header("Location: role.php?id=" . $user_id);
                    exit();
                } else {
                    $error = "Error: " . $stmt->error;
                }

                $stmt->close();
            } else {
                $error = "Registration is temporarily unavailable.";
            }
        }

        $check->close();
    } else {
        $error = "Registration is temporarily unavailable.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
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
            <h2>Register</h2>
            <p class="form_intro">Create your account to access the game and achievements.</p>

            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="Email" required>

            <label for="username">Username</label>
            <input type="text" name="username" id="username" placeholder="Username" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="Password" required>

            <?php if ($error !== ""): ?>
                <div class="form_error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <button name="register">Create Account</button>
            <a class="form_link" href="login.php">Already have an account? Log in.</a>
        </form>
    </div>
</body>
</html>
