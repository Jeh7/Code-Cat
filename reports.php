<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "Access denied";
    exit();
}

$role = trim((string)($_GET['role'] ?? ''));
$allowed_roles = ['student', 'teacher', 'admin', 'na'];

if ($role !== '' && !in_array($role, $allowed_roles, true)) {
    $role = '';
}

if ($role !== '') {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM users");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Report</title>
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
                        <a href="levels.php">Classroom Levels</a>
                    <?php endif; ?>
                    <a href="reports.php">User Reports</a>
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

    <div class="panel report_panel">
        <div class="report_header">
            <div>
                <div class="page_back_row is-tight">
                    <a class="secondary_button" href="index.php">Back to Home</a>
                </div>
                <h2>User Report</h2>
                <p>Review registered users by role and export the current list.</p>
            </div>
            <a class="secondary_button" href="export.php">Export CSV</a>
        </div>

        <form method="GET" class="report_filters">
            <label for="role">Role</label>
            <select name="role" id="role">
                <option value="">All roles</option>
                <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="na" <?= $role === 'na' ? 'selected' : '' ?>>Not applicable</option>
            </select>
            <button type="submit">Apply Filter</button>
        </form>

        <div class="table_wrap">
            <table class="report_table">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['role']) ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
