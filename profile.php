<?php
session_start();
include "db.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
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
            <?= "<strong>Username:</strong> " . $_SESSION['user'] ?><br>
            <?= "<strong>Role:</strong> " . $_SESSION["role"] ?><br>
            <?= "<strong>Email:</strong> " . $_SESSION["email"] ?><br>
            <?= "<strong>Date Created:</strong> " . $_SESSION["reg_date"] ?><br>
            <a href="logout.php">Logout</a>
        </div>
    </div>    
</body>
</html>