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
$level_id = (int)($_GET['id'] ?? 0);

$level_result = $conn->query("
SELECT l.*,
       c.name AS classroom_name,
       u.username AS teacher_name,
       p.status AS progress_status,
       p.attempts,
       p.last_played_at,
       p.completed_at
FROM teacher_levels l
INNER JOIN classrooms c ON c.id = l.classroom_id
INNER JOIN classroom_members cm ON cm.classroom_id = c.id
INNER JOIN users u ON u.id = l.teacher_id
LEFT JOIN student_level_progress p ON p.level_id = l.id AND p.student_id = cm.student_id
WHERE l.id = '$level_id'
  AND l.status = 'published'
  AND cm.student_id = '$student_id'
LIMIT 1
");

if (!$level_result || $level_result->num_rows === 0) {
    echo "Level not found";
    exit();
}

$level = $level_result->fetch_assoc();
$is_completed = ($level['progress_status'] ?? 'not_started') === 'completed';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Play Level</title>
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
            <div class="level_card_header">
                <div>
                    <h2><?= htmlspecialchars($level['title']) ?></h2>
                    <p><?= htmlspecialchars($level['description']) ?></p>
                </div>
                <span class="badge"><?= htmlspecialchars($level['difficulty']) ?></span>
            </div>

            <div class="level_meta">
                <span><strong>Classroom:</strong> <?= htmlspecialchars($level['classroom_name']) ?></span>
                <span><strong>Teacher:</strong> <?= htmlspecialchars($level['teacher_name']) ?></span>
                <span><strong>Progress:</strong> <?= htmlspecialchars($level['progress_status'] ?? 'not_started') ?></span>
            </div>

            <?php if ($is_completed): ?>
                <div class="empty_state">
                    <strong>Level completed.</strong>
                    <span>
                        This classroom level is already finished<?= !empty($level['completed_at']) ? ' on ' . htmlspecialchars($level['completed_at']) : '' ?>.
                        Progress has been recorded, and replay is disabled.
                    </span>
                </div>
                <div class="table_actions">
                    <a class="secondary_button" href="levels.php">Back to Levels</a>
                </div>
            <?php else: ?>
                <div class="play_layout">
                    <div class="instruction_box">
                        <strong>Instructions</strong><br>
                        <?= nl2br(htmlspecialchars($level['instructions'])) ?>
                        <hr>
                        <strong>Gameplay mode</strong><br>
                        This classroom level opens in the same Godot gameplay used by the normal game.
                        <hr>
                        <strong>Progress tracking</strong><br>
                        Opening the game records an attempt. Reaching the goal updates completion automatically.
                    </div>

                    <div class="player_shell">
                        <div class="game_shell">
                            <iframe
                                id="classroom-game-frame"
                                src="game/index.html?classroom_level_id=<?= (int)$level_id ?>"
                                class="game_frame"
                                title="Classroom level gameplay"
                            ></iframe>
                        </div>
                        <div id="completion-callout" class="callout" style="display:none;">
                            <strong>Level completed.</strong>
                            <span>Your progress has been updated. Redirecting to the finished page.</span>
                        </div>
                        <div class="table_actions">
                            <a class="secondary_button" href="levels.php">Back to Levels</a>
                            <a class="secondary_button" href="play_level.php?id=<?= (int)$level_id ?>">Refresh Progress</a>
                        </div>
                    </div>
                </div>
                <script>
                (function () {
                    const levelId = <?= (int)$level_id ?>;
                    const completionCallout = document.getElementById('completion-callout');
                    let redirected = false;

                    async function pollProgress() {
                        if (redirected) return;

                        try {
                            const response = await fetch('classroom_progress_api.php?level_id=' + levelId, {
                                credentials: 'same-origin'
                            });
                            if (!response.ok) return;

                            const payload = await response.json();
                            if (!payload.ok) return;

                            if ((payload.progress?.status || '') === 'completed') {
                                redirected = true;
                                if (completionCallout) {
                                    completionCallout.style.display = 'grid';
                                }
                                window.setTimeout(function () {
                                    window.location.href = 'play_level.php?id=' + levelId + '&finished=1';
                                }, 900);
                            }
                        } catch (_error) {
                        }
                    }

                    window.setInterval(pollProgress, 2500);
                    pollProgress();
                }());
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
