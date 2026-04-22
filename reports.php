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

$report_count = $result ? $result->num_rows : 0;
$role_counts = [
    'student' => 0,
    'teacher' => 0,
    'admin' => 0,
    'na' => 0
];

$role_summary = $conn->query("
    SELECT role, COUNT(*) AS total
    FROM users
    GROUP BY role
");

if ($role_summary) {
    while ($summary_row = $role_summary->fetch_assoc()) {
        $summary_role = (string)($summary_row['role'] ?? '');
        if (array_key_exists($summary_role, $role_counts)) {
            $role_counts[$summary_role] = (int)$summary_row['total'];
        }
    }
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
        <div class="page_back_row is-tight">
            <a class="secondary_button" href="index.php">Back to Home</a>
        </div>

        <div class="dashboard_hero">
            <div>
                <h2>User Report</h2>
                <p>Review registered users, narrow the list by role, and export the current report when you need an offline copy.</p>
            </div>
            <div class="dashboard_summary">
                <div class="dashboard_summary_card">
                    <strong><?= $report_count ?></strong>
                    <span><?= $role === '' ? 'Users in current view' : ucfirst($role) . ' users in current view' ?></span>
                </div>
                <div class="dashboard_summary_card">
                    <strong><?= array_sum($role_counts) ?></strong>
                    <span>Total registered accounts</span>
                </div>
            </div>
        </div>

        <div class="dashboard_summary">
            <div class="dashboard_summary_card">
                <strong><?= $role_counts['student'] ?></strong>
                <span>Students</span>
            </div>
            <div class="dashboard_summary_card">
                <strong><?= $role_counts['teacher'] ?></strong>
                <span>Teachers</span>
            </div>
            <div class="dashboard_summary_card">
                <strong><?= $role_counts['admin'] ?></strong>
                <span>Admins</span>
            </div>
            <div class="dashboard_summary_card">
                <strong><?= $role_counts['na'] ?></strong>
                <span>No role selected</span>
            </div>
        </div>

        <div class="filter_card">
            <div class="dashboard_section_header">
                <h3>Filter the report</h3>
                <p>Choose one role to narrow the table, or keep all roles visible.</p>
            </div>
            <form method="GET" class="report_filters">
                <div class="field_group">
                    <label for="role">Role</label>
                    <select name="role" id="role">
                        <option value="">All roles</option>
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="na" <?= $role === 'na' ? 'selected' : '' ?>>Not applicable</option>
                    </select>
                </div>
                <button type="submit">Apply Filter</button>
                <a class="secondary_button" href="reports.php">Reset</a>
                <a class="secondary_button" href="export.php">Export CSV</a>
            </form>
        </div>

        <div class="table_wrap">
            <table class="report_table">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                </tr>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><span class="status_pill"><?= htmlspecialchars($row['role']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty_state_row">
                        <td colspan="3">No users match the current filter.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>
</html>
