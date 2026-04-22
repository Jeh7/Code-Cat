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
    <title>Choose Gameplay</title>
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
                    <a href="gameplay.php">Gameplay Modes</a>
                    <a href="levels.php">Classroom Levels</a>
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

    <div class="content teacher_content">
        <div class="page teacher_page">
            <div class="page_back_row">
                <a class="secondary_button" href="index.php">Back to Home</a>
            </div>
            <h2>Choose Gameplay</h2>
            <p>Select the mode you want to play.</p>

            <div class="level_grid">
                <div class="level_card">
                    <div class="level_card_header">
                        <h3>Normal Gameplay</h3>
                        <span class="badge">Original</span>
                    </div>
                    <p>Play the built-in exported Code Cat game exactly as provided in the original project.</p>
                    <div class="table_actions">
                        <a class="secondary_button" href="game.php">Open Normal Game</a>
                    </div>
                </div>

                <div class="level_card">
                    <div class="level_card_header">
                        <h3>Classroom Gameplay</h3>
                        <span class="badge">Teacher Levels</span>
                    </div>
                    <p>Play teacher-created classroom puzzles and track your progress by class and level.</p>
                    <div class="table_actions">
                        <a class="secondary_button" href="levels.php">Open Classroom Levels</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
