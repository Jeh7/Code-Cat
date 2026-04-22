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

$student_id = (int)($_SESSION['id'] ?? 0);

$levels_stmt = $conn->prepare("
SELECT l.id,
       l.classroom_id,
       l.title,
       l.description,
       l.instructions,
       l.difficulty,
       l.created_at,
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
WHERE cm.student_id = ?
  AND l.status = 'published'
ORDER BY c.name ASC, l.created_at ASC, l.id ASC
");

$levels_result = false;
if ($levels_stmt) {
    $levels_stmt->bind_param("i", $student_id);
    $levels_stmt->execute();
    $levels_result = $levels_stmt->get_result();
}

$levels = [];
if ($levels_result && $levels_result->num_rows > 0) {
    $classroom_is_unlocked = [];
    $level_number = 1;

    while ($level = $levels_result->fetch_assoc()) {
        $classroom_id = (int)$level['classroom_id'];
        $status = (string)($level['progress_status'] ?? 'not_started');

        if (!array_key_exists($classroom_id, $classroom_is_unlocked)) {
            $classroom_is_unlocked[$classroom_id] = true;
        }

        $level['is_unlocked'] = $classroom_is_unlocked[$classroom_id];
        $level['display_number'] = $level_number;
        $levels[] = $level;
        $classroom_is_unlocked[$classroom_id] = ($status === 'completed');
        $level_number++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Classroom Levels</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body class="levels_page_body">
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
        <div class="page teacher_page levels_page">
            <h2 class="levels_title">LEVEL SELECT</h2>

            <div class="levels_selector_grid">
                <?php if ($levels): ?>
                    <?php foreach ($levels as $level): ?>
                    <?php $status = (string)($level['progress_status'] ?? 'not_started'); ?>
                    <?php $is_clickable = ($level['is_unlocked'] ?? false) && $status !== 'completed'; ?>
                    <?php $tile_classes = 'level_select_tile'; ?>
                    <?php if ($is_clickable): ?>
                        <?php $tile_classes .= ' is-active'; ?>
                    <?php else: ?>
                        <?php $tile_classes .= ' is-disabled'; ?>
                    <?php endif; ?>

                    <?php if ($is_clickable): ?>
                        <a
                            class="<?= $tile_classes ?>"
                            href="play_level.php?id=<?= (int)$level['id'] ?>"
                            title="<?= htmlspecialchars($level['title'] . ' | ' . $level['classroom_name']) ?>"
                            aria-label="Open level <?= (int)$level['display_number'] ?>: <?= htmlspecialchars($level['title']) ?>"
                        >
                            <?= (int)$level['display_number'] ?>
                        </a>
                    <?php else: ?>
                        <span
                            class="<?= $tile_classes ?>"
                            title="<?= htmlspecialchars($level['title'] . ' | ' . $level['classroom_name']) ?>"
                            aria-label="<?= $status === 'completed' ? 'Completed level ' : 'Locked level ' ?><?= (int)$level['display_number'] ?>"
                        >
                            <?= (int)$level['display_number'] ?>
                        </span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty_state levels_empty_state">
                        <strong>No classroom levels are assigned to you yet.</strong>
                        <span>Your teacher needs to add you to a classroom and publish levels for that class.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
