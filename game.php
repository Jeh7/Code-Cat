<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
    header("Location: teacher_levels.php");
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

    <div class="content">
        <div class="gameplay_split">
            <div class="game_shell">
                <iframe src="game/index.html" class="game_frame" title="Code Cat game"></iframe>
            </div>

            <div class="page gameplay_side_panel">
                <h2>Game Help</h2>
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
                    <div class="help_card">
                        <strong>Best view</strong>
                        <span>The game area now scales to the current screen so the canvas and help stay visible together more reliably.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
