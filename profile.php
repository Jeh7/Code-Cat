<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
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
            <div class="profile_shell">
                <div class="profile_summary">
                    <div class="profile_intro_card">
                        <h2>Your Profile</h2>
                        <p class="form_intro">This page gives you a quick account summary and the fastest route back to the parts of Code Cat you use most.</p>
                        <div class="callout">
                            <strong>Signed in as <?= htmlspecialchars($_SESSION['user']) ?></strong>
                            <span>Your current role is <?= htmlspecialchars($_SESSION['role']) ?>.</span>
                        </div>
                    </div>

                    <div class="profile_meta_card">
                        <strong>Account snapshot</strong>
                        <p>Email: <?= htmlspecialchars($_SESSION['email']) ?></p>
                        <p>Created: <?= htmlspecialchars($_SESSION['reg_date']) ?></p>
                    </div>
                </div>

                <div class="profile_details">
                    <div class="profile_field"><strong>Username</strong><span><?= htmlspecialchars($_SESSION['user']) ?></span></div>
                    <div class="profile_field"><strong>Role</strong><span><?= htmlspecialchars($_SESSION['role']) ?></span></div>
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
