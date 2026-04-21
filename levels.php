<?php
session_start();
include "db.php";

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] === 'teacher') {
    header("Location: teacher_levels.php");
    exit();
}

if ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'na') {
    echo "Access denied";
    exit();
}

$student_id = $_SESSION['id'];

$levels = $conn->query("
SELECT l.id,
       l.title,
       l.description,
       l.instructions,
       l.difficulty,
       c.name AS classroom_name,
       u.username AS teacher_name,
       p.status AS progress_status,
       p.attempts,
       p.last_played_at,
       p.completed_at
FROM classroom_members cm
INNER JOIN classrooms c ON c.id = cm.classroom_id
INNER JOIN teacher_levels l ON l.classroom_id = c.id
INNER JOIN users u ON u.id = l.teacher_id
LEFT JOIN student_level_progress p
    ON p.level_id = l.id AND p.student_id = cm.student_id
WHERE cm.student_id = '$student_id'
  AND l.status = 'published'
ORDER BY c.name ASC, l.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Classroom Levels</title>
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

    <div class="content teacher_content">
        <div class="page teacher_page">
            <h2>Classroom Levels</h2>
            <p>These are the published levels assigned to the classrooms you are enrolled in.</p>

            <div class="level_grid">
                <?php if ($levels && $levels->num_rows > 0): ?>
                    <?php while ($level = $levels->fetch_assoc()): ?>
                    <div class="level_card">
                        <div class="level_card_header">
                            <h3><?= htmlspecialchars($level['title']) ?></h3>
                            <span class="badge"><?= htmlspecialchars($level['difficulty']) ?></span>
                        </div>
                        <p><?= htmlspecialchars($level['description']) ?></p>
                        <div class="level_meta">
                            <span><strong>Classroom:</strong> <?= htmlspecialchars($level['classroom_name']) ?></span>
                            <span><strong>Teacher:</strong> <?= htmlspecialchars($level['teacher_name']) ?></span>
                            <span><strong>Status:</strong> <?= htmlspecialchars($level['progress_status'] ?? 'not_started') ?></span>
                            <span><strong>Attempts:</strong> <?= (int)($level['attempts'] ?? 0) ?></span>
                        </div>
                        <div class="instruction_box">
                            <?= nl2br(htmlspecialchars($level['instructions'])) ?>
                        </div>
                        <div class="table_actions">
                            <a class="secondary_button" href="play_level.php?id=<?= (int)$level['id'] ?>">
                                <?php
                                $status = $level['progress_status'] ?? 'not_started';
                                if ($status === 'completed') {
                                    echo 'View completion';
                                } elseif ($status === 'not_started') {
                                    echo 'Open level';
                                } else {
                                    echo 'Continue level';
                                }
                                ?>
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty_state">
                        <strong>No classroom levels are assigned to you yet.</strong>
                        <span>Your teacher needs to add you to a classroom and publish levels for that class.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
