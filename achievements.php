<?php
session_start();
include "db.php";
include "achievement_helpers.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
achievement_ensure_defaults($conn);
$achievement_titles = achievement_titles_for_role((string)($_SESSION['role'] ?? 'student'));
$placeholders = implode(',', array_fill(0, count($achievement_titles), '?'));

$stmt = $conn->prepare("
SELECT a.id, a.title, a.description,
       ua.id AS unlocked
FROM achievements a
LEFT JOIN user_achievements ua
ON a.id = ua.achievement_id AND ua.user_id = ?
WHERE a.title IN ($placeholders)
ORDER BY a.id
");

$result = false;
if ($stmt) {
    $types = 'i' . str_repeat('s', count($achievement_titles));
    $params = array_merge([$user_id], $achievement_titles);
    $bind_params = [$types];
    foreach ($params as $key => &$value) {
        $bind_params[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Achievements</title>
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
                        <a href="teacher_reports.php">Teacher Reports</a>
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

    <div class="panel">
        <div class="page_back_row">
            <a class="secondary_button" href="index.php">Back to Home</a>
        </div>
        <h2><?= (($_SESSION['role'] ?? '') === 'teacher') ? 'Teacher Achievements' : 'Student Achievements' ?></h2>
        <div class="achievements">
        <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card <?php echo $row['unlocked'] ? 'unlocked' : 'locked'; ?>">
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p><?php echo htmlspecialchars($row['description']); ?></p>
                <span class="card_status">
                    <?php echo $row['unlocked'] ? "Unlocked" : "Locked"; ?>
                </span>
            </div>
        <?php endwhile; ?>
        <?php else: ?>
            <div class="empty_state">
                <strong>No achievements found.</strong>
                <span>Refresh the page after the database has been initialized.</span>
            </div>
        <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
if ($stmt) {
    $stmt->close();
}
?>
