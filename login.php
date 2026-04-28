<?php
session_start();
include "db.php";
include "flash.php";
include "achievement_helpers.php";

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
                achievement_unlock_by_title($conn, (int)$user['id'], 'First Login');
                $stmt->close();
                flash_add('success', 'Logged in successfully.');
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
        <div class="auth_shell">
            <?= render_flash_messages() ?>
            <section class="auth_aside">
                <span class="auth_eyebrow">Code Cat</span>
                <h1>Pick up where you left off.</h1>
                <p>Sign in to continue puzzle progress, revisit classroom levels, and keep your achievements in one place.</p>
                <ul class="auth_feature_list">
                    <li>Return to gameplay without extra setup.</li>
                    <li>Access teacher dashboards or student levels based on your role.</li>
                    <li>Keep progress and account details tied to one profile.</li>
                </ul>
                <div class="auth_aside_note">
                    <strong>New here?</strong>
                    <p>Create an account first, then choose whether you are joining as a student, teacher, or general user.</p>
                </div>
            </section>

            <form method="POST" class="login_box auth_panel">
                <h2>Welcome back</h2>
                <p class="form_intro">Use your username and password to continue.</p>

                <div class="form_stack">
                    <div class="field_group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" autocomplete="username" required>
                    </div>

                    <div class="field_group">
                        <label for="pass">Password</label>
                        <input type="password" name="password" required id="pass" autocomplete="current-password">
                    </div>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="form_error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="form_actions">
                    <button class="primary_button" name="login">Log In</button>
                    <span class="form_footer">Need an account? <a class="form_link" href="register.php">Register here</a>.</span>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
