<?php
include "db.php";
include "flash.php";

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
        <div class="auth_shell">
            <?= render_flash_messages() ?>
            <section class="auth_aside">
                <span class="auth_eyebrow">Start Here</span>
                <h1>Create your Code Cat account.</h1>
                <p>Registration takes a minute. After this step, you will choose how you plan to use the app.</p>
                <ul class="auth_feature_list">
                    <li>Students can access classroom levels and gameplay modes.</li>
                    <li>Teachers can create classrooms and manage level progress.</li>
                    <li>General users can explore without selecting a classroom role yet.</li>
                </ul>
                <div class="auth_aside_note">
                    <strong>Next step after registration</strong>
                    <p>You will be asked to choose a role before signing in for the first time.</p>
                </div>
            </section>

            <form method="POST" class="login_box auth_panel">
                <h2>Create account</h2>
                <p class="form_intro">Use an email you can recognize later and a username you want to keep.</p>

                <div class="form_stack">
                    <div class="field_group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" autocomplete="email" placeholder="name@example.com" required>
                    </div>

                    <div class="field_group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" autocomplete="username" placeholder="Choose a username" required>
                    </div>

                    <div class="field_group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" autocomplete="new-password" placeholder="Create a password" required>
                        <span class="field_help">You can choose your role on the next screen.</span>
                    </div>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="form_error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="form_actions">
                    <button class="primary_button" name="register">Create Account</button>
                    <span class="form_footer">Already registered? <a class="form_link" href="login.php">Log in here</a>.</span>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
