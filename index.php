<?php
session_start();
include "db.php";
include "flash.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="tab">
        <a href="index.php">
            <img src="img\logo.png" class="logo">
        </a>
        <div class="nav-buttons">
            <?php if (!isset($_SESSION['user'])): ?>
                <button onclick="javascript:location.href='login.php'">Login</button>
                <button onclick="javascript:location.href='register.php'">Register</button>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
        <?= render_flash_messages() ?>
        <div class="hbox">
            <div class="slideshow">
                <button class="prev" onclick="changeSlide(-1)" aria-label="Previous slide">&#10094;</button>
                <img class="slide" src="img/sc1.png" alt="Code Cat gameplay screenshot 1">
                <img class="slide" src="img/sc2.jpg" alt="Code Cat gameplay screenshot 2">
                <img class="slide" src="img/sc3.png" alt="Code Cat gameplay screenshot 3">
                <button class="next" onclick="changeSlide(1)" aria-label="Next slide">&#10095;</button>
            </div>

            <script>
            let index = 0;
            showSlide(index);

            function showSlide(i) {
                let slides = document.getElementsByClassName("slide");
                if (i >= slides.length) index = 0;
                if (i < 0) index = slides.length - 1;
                for (let j = 0; j < slides.length; j++) {
                    slides[j].style.display = "none";
                }
                slides[index].style.display = "block";
            }

            function changeSlide(n) {
                index += n;
                showSlide(index);
            }

            setInterval(() => {
                changeSlide(1);
            }, 6000);
            </script>

            <div class="intro_panel">
                <h1>Welcome, to Code Cat</h1>
                <h2>Learn Coding through puzzles!</h2>
                <p>
                    Make programming fun and exciting.<br>
                    This educational game teaches you<br>
                    the basics of programming in a gamified way.<br>
                </p>
                <?php if (isset($_SESSION['user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <button onclick="javascript:location.href='teacher_levels.php'" class="play-button">Open Teacher Dashboard</button>
                    <div class="callout">
                        <strong>Teacher accounts do not launch the game.</strong>
                        <span>Use classrooms, levels, teacher reports, and student progress tracking from your dashboard.</span>
                    </div>
                <?php elseif (isset($_SESSION['user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <button onclick="javascript:location.href='reports.php'" class="play-button">Open Admin Reports</button>
                    <div class="callout">
                        <strong>Admin accounts do not launch the game.</strong>
                        <span>Use user reports, system summaries, and generated admin PDFs from your dashboard.</span>
                    </div>
                <?php elseif (isset($_SESSION['user'])): ?>
                    <button onclick="javascript:location.href='gameplay.php'" class="play-button">Choose Gameplay</button>
                <?php else: ?>
                    <div class="callout">
                        <strong>Create an account to launch the game.</strong>
                        <span>Register first, then choose a role and start solving puzzles.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="page">
            <h2>What's New?</h2>
            <div class="update_log">
                <div class="empty_state">
                    <strong>No updates posted yet.</strong>
                    <span>Use this area for release notes, puzzle additions, or classroom announcements.</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
