<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$role = (string)($_SESSION['role'] ?? 'na');
$role_label = $role === 'na' ? 'No role assigned' : ucfirst($role);
$primary_action = [
    'href' => 'gameplay.php',
    'label' => 'Continue Playing',
    'description' => 'Open gameplay modes and choose what to play next.',
];

if ($role === 'teacher') {
    $primary_action = [
        'href' => 'teacher_levels.php',
        'label' => 'Open Teacher Dashboard',
        'description' => 'Manage classrooms, levels, and student progress.',
    ];
} elseif ($role === 'admin') {
    $primary_action = [
        'href' => 'reports.php',
        'label' => 'Open Admin Dashboard',
        'description' => 'Review users, analytics, and system reports.',
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=profile-dashboard">
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
                    <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                        <a href="achievements.php">Achievements</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'teacher'): ?>
                        <a href="teacher_levels.php">Teacher Dashboard</a>
                        <a href="teacher_reports.php">Teacher Reports</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'student' || $_SESSION['role'] == 'na')): ?>
                        <a href="gameplay.php">Gameplay Modes</a>
                        <a href="levels.php">Classroom Levels</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                        <a href="reports.php">Admin Reports</a>
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

    <div class="login_body">
        <div class="user_profile">
            <div class="page_back_row is-tight">
                <a class="secondary_button" href="index.php">Back to Home</a>
            </div>
            <div class="profile_shell profile_dashboard">
                <div class="profile_dashboard_hero">
                    <div class="profile_identity">
                        <img src="img\default-pfp.png" alt="" class="profile_dashboard_avatar">
                        <div>
                            <span class="profile_overline">Signed in</span>
                            <h2><?= htmlspecialchars($_SESSION['user']) ?></h2>
                            <p><?= htmlspecialchars((string)($_SESSION['email'] ?? '')) ?></p>
                        </div>
                    </div>

                    <div class="profile_hero_action">
                        <span class="profile_role_badge"><?= htmlspecialchars($role_label) ?></span>
                        <a class="primary_button" href="<?= htmlspecialchars($primary_action['href']) ?>"><?= htmlspecialchars($primary_action['label']) ?></a>
                        <p><?= htmlspecialchars($primary_action['description']) ?></p>
                    </div>
                </div>

                <div class="profile_details">
                    <div class="profile_field"><strong>Username</strong><span><?= htmlspecialchars($_SESSION['user']) ?></span></div>
                    <div class="profile_field"><strong>Role</strong><span><?= htmlspecialchars($role_label) ?></span></div>
                    <div class="profile_field"><strong>Email</strong><span><?= htmlspecialchars($_SESSION['email']) ?></span></div>
                    <div class="profile_field"><strong>Date Created</strong><span><?= htmlspecialchars($_SESSION['reg_date']) ?></span></div>
                </div>

                <div class="dashboard_section">
                    <div class="dashboard_section_header">
                        <h3>Quick actions</h3>
                        <p>Use the links below to jump back into your current flow.</p>
                    </div>
                    <div class="quick_action_grid">
                        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                            <a class="quick_action_card" href="achievements.php">
                                <strong>Achievements</strong>
                                <span>Review unlocked milestones and track what is left.</span>
                            </a>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                            <a class="quick_action_card" href="teacher_levels.php">
                                <strong>Teacher Dashboard</strong>
                                <span>Manage classrooms, levels, and student progress.</span>
                            </a>
                            <a class="quick_action_card" href="teacher_reports.php">
                                <strong>Teacher Reports</strong>
                                <span>Generate classroom progress reports and download the exported PDFs.</span>
                            </a>
                        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a class="quick_action_card" href="reports.php">
                                <strong>Admin Reports</strong>
                                <span>Generate user, classroom, achievement, and system summary reports.</span>
                            </a>
                        <?php else: ?>
                            <a class="quick_action_card" href="gameplay.php">
                                <strong>Gameplay Modes</strong>
                                <span>Choose between the original game and classroom levels.</span>
                            </a>
                            <a class="quick_action_card" href="levels.php">
                                <strong>Classroom Levels</strong>
                                <span>Browse teacher-created levels and continue your progress.</span>
                            </a>
                        <?php endif; ?>
                        <a class="quick_action_card" href="logout.php">
                            <strong>Log Out</strong>
                            <span>End this session on the current device.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
