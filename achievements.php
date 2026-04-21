<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];

// get all achievements + user progress
$sql = "
SELECT a.id, a.title, a.description,
       ua.id AS unlocked
FROM achievements a
LEFT JOIN user_achievements ua 
ON a.id = ua.achievement_id AND ua.user_id = '$user_id'
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Achievements</title>
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

    <div class="panel">
        <h2>Achievements</h2>
        <div class="achievements">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card <?php echo $row['unlocked'] ? 'unlocked' : 'locked'; ?>">
                <h3><?php echo $row['title']; ?></h3>
                <p><?php echo $row['description']; ?></p>
                <span>
                    <?php echo $row['unlocked'] ? "✅ Unlocked" : "🔒 Locked"; ?>
                </span>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</body>