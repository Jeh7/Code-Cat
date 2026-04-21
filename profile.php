<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="tab">
        <a href="index.php">
            <img src="img\logo.png" class="logo">
        </a>
        <div class="nav-buttons">
            <div class="profile" onclick="toggleMenu()">
                <img src="img\default-pfp.png" class="profile-img">
                <div id="dropdown" class="dropdown">
                    <a href="profile.php">Profile</a>
                    <a href="achievements.php">Achievements</a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'teacher'): ?>
                        <a href="teacher_levels.php">Teacher Dashboard</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'student' || $_SESSION['role'] == 'na')): ?>
                        <a href="gameplay.php">Gameplay Modes</a>
                        <a href="levels.php">Classroom Levels</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                        <a href="reports.php">User Reports</a>
                    <?php endif; ?>
                    <a href="logout.php">Logout</a>
                </div>
                <script>
                function toggleMenu() {
                    var menu = document.getElementById("dropdown");
                    menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
                }
                </script>
            </div>
        </div>
    </div>

    <div class="login_body">
        <div class="user_profile">
            <h2>Your Profile</h2>
            <div class="profile_field"><strong>Username</strong><span><?= htmlspecialchars($_SESSION['user']) ?></span></div>
            <div class="profile_field"><strong>Role</strong><span><?= htmlspecialchars($_SESSION['role']) ?></span></div>
            <div class="profile_field"><strong>Email</strong><span><?= htmlspecialchars($_SESSION['email']) ?></span></div>
            <div class="profile_field"><strong>Date Created</strong><span><?= htmlspecialchars($_SESSION['reg_date']) ?></span></div>
            <a class="secondary_link" href="logout.php">Log out</a>
        </div>
    </div>
</body>
</html>
