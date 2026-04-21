<?php
session_start();
include "db.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
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
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
        <div class="hbox">
            <div class="slideshow">
                <button class="prev" onclick="changeSlide(-1)">❮</button>
                <img class="slide" src="img/sc1.png">
                <img class="slide" src="img/sc2.jpg">
                <img class="slide" src="img/sc3.png">
                <button class="next" onclick="changeSlide(1)">❯</button>
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
                <h2>Learn Coding through puzzles!</h2><br>
                <p>
                    Make programming fun and exciting.<br>
                    This educational game teaches you<br>
                    the basics of programming in a gamified way.<br>
                </p><br>
                <?php if (isset($_SESSION['user'])): ?>
                    <button onclick="javascript:location.href='game.php'" class="play-button">
                        <h3>Click Here to Play</h3>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <br>
        
        <div class="page">
            <h2>What's New?</h2>
            <div class="update_log">
                
            </div>
        </div>
    </div>
</body>
</html>
