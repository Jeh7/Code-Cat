<?php
session_start();
include "db.php";
include "flash.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    echo "Access denied";
    exit();
}

$teacher_id = (int)($_SESSION['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_classroom') {
        $classroom_name = trim($_POST['classroom_name'] ?? '');
        $classroom_description = trim($_POST['classroom_description'] ?? '');

        if ($classroom_name !== '' && $classroom_description !== '') {
            $stmt = $conn->prepare("
                INSERT INTO classrooms (teacher_id, name, description)
                VALUES (?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("iss", $teacher_id, $classroom_name, $classroom_description);
                $stmt->execute();
                $stmt->close();
                flash_add('success', 'Classroom created successfully.');
                header("Location: teacher_levels.php");
                exit();
            }
        }

        flash_add('error', 'Classroom name and description are required.');
    }

    if ($action === 'add_student') {
        $classroom_id = (int)($_POST['classroom_id'] ?? 0);
        $student_username = trim($_POST['student_username'] ?? '');

        $classroom_stmt = $conn->prepare("SELECT id FROM classrooms WHERE id = ? AND teacher_id = ? LIMIT 1");
        $student_stmt = $conn->prepare("SELECT id, role FROM users WHERE username = ? LIMIT 1");
        $classroom_check = false;
        $student_result = false;

        if ($classroom_stmt) {
            $classroom_stmt->bind_param("ii", $classroom_id, $teacher_id);
            $classroom_stmt->execute();
            $classroom_check = $classroom_stmt->get_result();
        }

        if ($student_stmt) {
            $student_stmt->bind_param("s", $student_username);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
        }

        if ($classroom_check && $classroom_check->num_rows > 0 && $student_result && $student_result->num_rows > 0) {
            $student = $student_result->fetch_assoc();

            if ($student['role'] === 'student' || $student['role'] === 'na') {
                $student_id = (int)$student['id'];
                $insert_stmt = $conn->prepare("
                    INSERT IGNORE INTO classroom_members (classroom_id, student_id)
                    VALUES (?, ?)
                ");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("ii", $classroom_id, $student_id);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    if ($classroom_stmt) {
                        $classroom_stmt->close();
                    }
                    if ($student_stmt) {
                        $student_stmt->close();
                    }
                    flash_add('success', 'Student added to the classroom.');
                    header("Location: teacher_levels.php");
                    exit();
                }
            }
        }

        if ($classroom_stmt) {
            $classroom_stmt->close();
        }
        if ($student_stmt) {
            $student_stmt->close();
        }

        flash_add('error', 'Only existing student accounts can be added to a classroom.');
    }

    if ($action === 'remove_student') {
        $classroom_id = (int)($_POST['classroom_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $conn->query("
            DELETE cm
            FROM classroom_members cm
            INNER JOIN classrooms c ON c.id = cm.classroom_id
            WHERE cm.classroom_id=$classroom_id
              AND cm.student_id=$student_id
              AND c.teacher_id=$teacher_id
        ");
        flash_add('success', 'Student removed from the classroom.');
        header("Location: teacher_levels.php");
        exit();
    }

    if ($action === 'delete_level') {
        $level_id = (int)($_POST['level_id'] ?? 0);
        $conn->query("DELETE FROM teacher_levels WHERE id=$level_id AND teacher_id=$teacher_id");
        flash_add('success', 'Level deleted.');
        header("Location: teacher_levels.php");
        exit();
    }
}

$classrooms = $conn->query("
SELECT c.*,
       COUNT(cm.id) AS student_count,
       COUNT(l.id) AS level_count
FROM classrooms c
LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
LEFT JOIN teacher_levels l ON l.classroom_id = c.id
WHERE c.teacher_id = $teacher_id
GROUP BY c.id
ORDER BY c.created_at DESC
");

$level_summary_sql = "
SELECT l.*,
       c.name AS classroom_name,
       COUNT(p.id) AS tracked_students,
       SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_students,
       SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) AS active_students
FROM teacher_levels l
INNER JOIN classrooms c ON c.id = l.classroom_id
LEFT JOIN student_level_progress p ON p.level_id = l.id
WHERE l.teacher_id = $teacher_id
GROUP BY l.id
ORDER BY c.name ASC, l.created_at ASC, l.id ASC
";
$levels = $conn->query($level_summary_sql);

$progress_sql = "
SELECT c.name AS classroom_name,
       l.title,
       l.difficulty,
       u.username,
       p.status,
       p.attempts,
       p.last_played_at,
       p.completed_at
FROM student_level_progress p
INNER JOIN teacher_levels l ON l.id = p.level_id
INNER JOIN classrooms c ON c.id = l.classroom_id
INNER JOIN users u ON u.id = p.student_id
WHERE l.teacher_id = $teacher_id
ORDER BY c.name ASC, l.created_at ASC, u.username ASC
";
$progress_rows = $conn->query($progress_sql);

$student_members = $conn->query("
SELECT c.id AS classroom_id,
       c.name AS classroom_name,
       u.id AS student_id,
       u.username,
       u.email
FROM classrooms c
LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
LEFT JOIN users u ON u.id = cm.student_id
WHERE c.teacher_id = $teacher_id
ORDER BY c.name ASC, u.username ASC
");

$teacher_overview = $conn->query("
SELECT
    (SELECT COUNT(*) FROM classrooms WHERE teacher_id = $teacher_id) AS total_classrooms,
    (SELECT COUNT(*) FROM teacher_levels WHERE teacher_id = $teacher_id) AS total_levels,
    (SELECT COUNT(*)
     FROM classroom_members cm
     INNER JOIN classrooms c ON c.id = cm.classroom_id
     WHERE c.teacher_id = $teacher_id) AS total_students
")->fetch_assoc();

$classroom_select_options = [];
$classroom_options_result = $conn->query("
SELECT id, name
FROM classrooms
WHERE teacher_id = $teacher_id
ORDER BY name ASC
");

if ($classroom_options_result && $classroom_options_result->num_rows > 0) {
    while ($classroom_option = $classroom_options_result->fetch_assoc()) {
        $classroom_select_options[] = $classroom_option;
    }
}

$has_classrooms = count($classroom_select_options) > 0;
$prefill_classroom_id = (int)($classroom_select_options[0]['id'] ?? 0);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard</title>
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
                    <a href="teacher_levels.php">Teacher Dashboard</a>
                    <a href="teacher_reports.php">Teacher Reports</a>
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
            <div class="page_back_row is-tight">
                <a class="secondary_button" href="index.php">Back to Home</a>
            </div>
            <div class="dashboard_hero">
                <div>
                    <h2>Teacher Dashboard</h2>
                    <p>Manage classrooms, enroll students, create classroom levels, and review learning activity from one place.</p>
                </div>
                <div class="table_actions">
                    <a class="secondary_button" href="teacher_reports.php">Teacher Reports</a>
                    <a class="primary_button" href="level_editor.php">Open Level Editor</a>
                </div>
            </div>

            <?= render_flash_messages() ?>

            <div class="stats_grid">
                <div class="stat_card">
                    <strong><?= (int)$teacher_overview['total_classrooms'] ?></strong>
                    <span>Classrooms you manage</span>
                </div>
                <div class="stat_card">
                    <strong><?= (int)$teacher_overview['total_levels'] ?></strong>
                    <span>Levels created</span>
                </div>
                <div class="stat_card">
                    <strong><?= (int)$teacher_overview['total_students'] ?></strong>
                    <span>Tracked students</span>
                </div>
            </div>

            <div class="dashboard_flow">
                <div class="flow_step">
                    <strong>1. Create a classroom</strong>
                    <span>Set up the class space before building content.</span>
                </div>
                <div class="flow_step">
                    <strong>2. Add students</strong>
                    <span>Enroll existing student accounts by username.</span>
                </div>
                <div class="flow_step">
                    <strong>3. Build and publish levels</strong>
                    <span>Use the editor to create the exact puzzles students will play.</span>
                </div>
                <div class="flow_step">
                    <strong>4. Review progress</strong>
                    <span>Check which students started, are active, or already finished.</span>
                </div>
            </div>
        </div>

        <div class="page teacher_page">
            <div class="dashboard_creation_intro">
                <div class="creation_hero">
                    <div>
                        <h2>Create Levels</h2>
                        <p>The full classroom level editor is the primary tool for building playable stages. Use it to place walls, spikes, keys, doors, locked doors, stun guns, and enemies.</p>
                    </div>
                    <div class="creation_actions">
                        <?php if ($has_classrooms): ?>
                            <a class="secondary_button" href="level_editor.php<?= $prefill_classroom_id > 0 ? '?classroom_id=' . $prefill_classroom_id : '' ?>">Open Full Editor</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard_guides">
                    <div class="callout creation_callout">
                        <strong>Recommended flow</strong>
                        <span>1. Create a classroom. 2. Open the full editor. 3. Build the level with drag-and-place tools. 4. Publish it so enrolled students can play it.</span>
                    </div>
                    <div class="callout creation_callout">
                        <strong>What the full editor includes</strong>
                        <span>Drag to paint walls or spikes, place the start and goal, add gameplay objects, and save the exact classroom stage used by Godot.</span>
                    </div>
                    <div class="callout creation_callout">
                        <strong>Dashboard role</strong>
                        <span>Create classrooms here, manage enrollment, and monitor progress. All level creation and editing now happens in the full editor.</span>
                    </div>
                </div>
            </div>

            <div class="teacher_split teacher_creation_split">
                <form method="POST" class="teacher_form">
                    <input type="hidden" name="action" value="save_classroom">
                    <div class="form_heading">
                        <h3>Create a classroom</h3>
                    </div>
                    <p class="form_hint">Create the classroom first. Levels can only be assigned to an existing classroom.</p>
                    <div class="field_group">
                        <label for="classroom_name">Classroom name</label>
                        <input type="text" id="classroom_name" name="classroom_name" placeholder="Example: Grade 7 - Section A" required>
                    </div>

                    <div class="field_group">
                        <label for="classroom_description">Description</label>
                        <input type="text" id="classroom_description" name="classroom_description" placeholder="What students should expect in this class" required>
                    </div>

                    <button type="submit">Create classroom</button>
                </form>

                <div class="teacher_form teacher_editor_cta">
                    <div class="form_heading">
                        <h3>Build classroom levels in the full editor</h3>
                    </div>
                    <?php if (!$has_classrooms): ?>
                        <div class="empty_state">
                            <strong>Create a classroom before creating a level.</strong>
                            <span>Once a classroom exists, open the full editor to create a level for that class.</span>
                        </div>
                    <?php else: ?>
                        <div class="callout compact_notice">
                            <strong>Single creation flow.</strong>
                            <span>Use the full editor to set metadata, place gameplay objects, and save the actual classroom level students will play in Godot.</span>
                        </div>
                        <div class="teacher_cta_list">
                            <div class="callout compact_notice">
                                <strong>What you can do there</strong>
                                <span>Assign the classroom, choose the grid size, place walls, spikes, keys, doors, enemies, and publish the level.</span>
                            </div>
                            <div class="table_actions dashboard_editor_actions">
                                <a class="secondary_button" href="level_editor.php<?= $prefill_classroom_id > 0 ? '?classroom_id=' . $prefill_classroom_id : '' ?>">Create level in full editor</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="page teacher_page">
            <div class="dashboard_section_header">
                <h2>Your Classrooms</h2>
                <p>Each classroom card keeps enrollment close to the class it belongs to, so adding students does not require leaving the page.</p>
            </div>
            <div class="classroom_grid">
                <?php if ($classrooms && $classrooms->num_rows > 0): ?>
                    <?php while ($classroom = $classrooms->fetch_assoc()): ?>
                    <div class="classroom_card">
                        <div class="level_card_header">
                            <h3><?= htmlspecialchars($classroom['name']) ?></h3>
                            <span class="badge"><?= (int)$classroom['student_count'] ?> students</span>
                        </div>
                        <p><?= htmlspecialchars($classroom['description']) ?></p>
                        <div class="level_meta">
                            <span><strong>Levels:</strong> <?= (int)$classroom['level_count'] ?></span>
                        </div>
                        <form method="POST" class="teacher_form compact_form">
                            <input type="hidden" name="action" value="add_student">
                            <input type="hidden" name="classroom_id" value="<?= (int)$classroom['id'] ?>">
                            <label for="student_<?= (int)$classroom['id'] ?>">Add student by username</label>
                            <input type="text" id="student_<?= (int)$classroom['id'] ?>" name="student_username" placeholder="Enter an existing username" required>
                            <span class="field_help">Only existing student or unassigned accounts can be enrolled.</span>
                            <button type="submit">Add student</button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty_state">
                        <strong>No classrooms yet.</strong>
                        <span>Create a classroom first before assigning students or publishing levels.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="page teacher_page">
            <div class="dashboard_section_header">
                <h2>Classroom Members</h2>
                <p>Review current enrollment and remove students when class membership changes.</p>
            </div>
            <div class="table_wrap">
                <table class="report_table">
                    <tr>
                        <th>Classroom</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                    <?php if ($student_members && $student_members->num_rows > 0): ?>
                        <?php while ($member = $student_members->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['classroom_name']) ?></td>
                            <td><?= htmlspecialchars($member['username'] ?? 'No students yet') ?></td>
                            <td><?= htmlspecialchars($member['email'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($member['student_id'])): ?>
                                <form method="POST" class="inline_form">
                                    <input type="hidden" name="action" value="remove_student">
                                    <input type="hidden" name="classroom_id" value="<?= (int)$member['classroom_id'] ?>">
                                    <input type="hidden" name="student_id" value="<?= (int)$member['student_id'] ?>">
                                    <button type="submit" class="link_button">Remove</button>
                                </form>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty_state_row">
                            <td colspan="4">No classrooms or students available yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="page teacher_page">
            <div class="dashboard_section_header">
                <h2>Your Levels</h2>
                <p>See what is already live, which levels are active, and where student progress is concentrated.</p>
            </div>
            <div class="table_wrap">
                <table class="report_table">
                    <tr>
                        <th>Classroom</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Difficulty</th>
                        <th>Tracked Students</th>
                        <th>Completed</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                    <?php if ($levels && $levels->num_rows > 0): ?>
                        <?php while ($level = $levels->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($level['classroom_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($level['title']) ?></strong><br>
                                <span><?= htmlspecialchars($level['description']) ?></span>
                            </td>
                            <td><span class="status_pill status-<?= htmlspecialchars($level['status']) ?>"><?= htmlspecialchars($level['status']) ?></span></td>
                            <td><span class="status_pill"><?= htmlspecialchars($level['difficulty']) ?></span></td>
                            <td><?= (int)$level['tracked_students'] ?></td>
                            <td><?= (int)$level['completed_students'] ?></td>
                            <td><?= (int)$level['active_students'] ?></td>
                            <td>
                                <div class="table_actions">
                                    <a class="secondary_link" href="level_editor.php?id=<?= (int)$level['id'] ?>">Edit in full editor</a>
                                    <form method="POST" class="inline_form">
                                        <input type="hidden" name="action" value="delete_level">
                                        <input type="hidden" name="level_id" value="<?= (int)$level['id'] ?>">
                                        <button type="submit" class="link_button">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty_state_row">
                            <td colspan="8">No levels created yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="page teacher_page">
            <div class="dashboard_section_header">
                <h2>Student Progress</h2>
                <p>Use this table to spot who has not started, who is still working, and which levels are already being completed.</p>
            </div>
            <div class="table_wrap">
                <table class="report_table">
                    <tr>
                        <th>Classroom</th>
                        <th>Student</th>
                        <th>Level</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Last Activity</th>
                        <th>Completed</th>
                    </tr>
                    <?php if ($progress_rows && $progress_rows->num_rows > 0): ?>
                        <?php while ($progress = $progress_rows->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($progress['classroom_name']) ?></td>
                            <td><?= htmlspecialchars($progress['username']) ?></td>
                            <td><?= htmlspecialchars($progress['title']) ?></td>
                            <td><span class="status_pill"><?= htmlspecialchars($progress['difficulty']) ?></span></td>
                            <td><span class="status_pill status-<?= htmlspecialchars($progress['status']) ?>"><?= htmlspecialchars($progress['status']) ?></span></td>
                            <td><?= (int)$progress['attempts'] ?></td>
                            <td><?= htmlspecialchars($progress['last_played_at'] ?? 'Not started') ?></td>
                            <td><?= htmlspecialchars($progress['completed_at'] ?? '-') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty_state_row">
                            <td colspan="8">No student activity recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
