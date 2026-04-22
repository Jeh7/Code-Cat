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
        <div class="auth_shell">
            <section class="auth_aside">
                <span class="auth_eyebrow">Final Setup</span>
                <h1>Choose the experience you need.</h1>
                <p>Your role controls the screens you see after sign-in. You can keep the product behavior simple by starting with the closest match.</p>
                <ul class="auth_feature_list">
                    <li><strong>Student:</strong> play classroom levels and track progress.</li>
                    <li><strong>Teacher:</strong> manage classrooms, students, and level publishing.</li>
                    <li><strong>No role:</strong> explore the app before committing to a classroom flow.</li>
                </ul>
            </section>

            <form method="post" class="login_box auth_panel role_box">
                <div class="page_back_row is-tight">
                    <a class="secondary_button" href="register.php">Back to Register</a>
                </div>
                <h2>Choose your role</h2>
                <p class="form_intro">Pick the option that best matches what you want to do first.</p>
                <div class="role_choice_grid">
                    <button class="primary_button role_choice" name="student">
                        <span>Continue as Student</span>
                        <small>Play classroom levels and review your progress.</small>
                    </button>
                    <button class="primary_button role_choice" name="teacher">
                        <span>Continue as Teacher</span>
                        <small>Open classroom dashboards and create learning content.</small>
                    </button>
                    <button class="secondary_button role_choice" name="na">
                        <span>Continue without role</span>
                        <small>Access the app first and decide on a classroom role later.</small>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
