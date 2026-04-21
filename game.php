<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Game</title>
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

    <div class="content">
        <div class="game_shell">
            <iframe src="game/index.html" class="game_frame" title="Code Cat game"></iframe>
        </div>

        <div class="page">
            <h2>Help?</h2>
            <div class="help_grid">
                <div class="help_card">
                    <strong>Move</strong>
                    <span>Use W, A, S, and D inside the game.</span>
                </div>
                <div class="help_card">
                    <strong>Retry</strong>
                    <span>Press R to reset the current puzzle.</span>
                </div>
                <div class="help_card">
                    <strong>Goal</strong>
                    <span>Arrange actions and reach the finish while avoiding hazards.</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
