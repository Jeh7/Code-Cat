<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    echo "Access denied";
    exit();
}

$teacher_id = $_SESSION['id'];
$message = "";
$level_form = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_classroom') {
        $classroom_name = trim($_POST['classroom_name'] ?? '');
        $classroom_description = trim($_POST['classroom_description'] ?? '');

        if ($classroom_name !== '' && $classroom_description !== '') {
            $conn->query("
                INSERT INTO classrooms (teacher_id, name, description)
                VALUES ($teacher_id, '$classroom_name', '$classroom_description')
            ");
            header("Location: teacher_levels.php");
            exit();
        }

        $message = "Classroom name and description are required.";
    }

    if ($action === 'add_student') {
        $classroom_id = (int)($_POST['classroom_id'] ?? 0);
        $student_username = trim($_POST['student_username'] ?? '');

        $classroom_check = $conn->query("SELECT id FROM classrooms WHERE id=$classroom_id AND teacher_id=$teacher_id");
        $student_result = $conn->query("SELECT id, role FROM users WHERE username='$student_username'");

        if ($classroom_check && $classroom_check->num_rows > 0 && $student_result && $student_result->num_rows > 0) {
            $student = $student_result->fetch_assoc();

            if ($student['role'] === 'student' || $student['role'] === 'na') {
                $student_id = (int)$student['id'];
                $conn->query("
                    INSERT IGNORE INTO classroom_members (classroom_id, student_id)
                    VALUES ($classroom_id, $student_id)
                ");
                header("Location: teacher_levels.php");
                exit();
            }
        }

        $message = "Only existing student accounts can be added to a classroom.";
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
        header("Location: teacher_levels.php");
        exit();
    }

    if ($action === 'save_level') {
        $level_id = (int)($_POST['level_id'] ?? 0);
        $classroom_id = (int)($_POST['classroom_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'beginner';
        $status = $_POST['status'] ?? 'draft';
        $grid_width = min(14, max(3, (int)($_POST['grid_width'] ?? 6)));
        $grid_height = min(14, max(3, (int)($_POST['grid_height'] ?? 6)));
        $start_x = max(0, (int)($_POST['start_x'] ?? 0));
        $start_y = max(0, (int)($_POST['start_y'] ?? 0));
        $goal_x = max(0, (int)($_POST['goal_x'] ?? 5));
        $goal_y = max(0, (int)($_POST['goal_y'] ?? 5));
        $walls = trim($_POST['walls'] ?? '');

        $level_form = [
            'id' => $level_id,
            'classroom_id' => $classroom_id,
            'title' => $title,
            'description' => $description,
            'instructions' => $instructions,
            'difficulty' => $difficulty,
            'status' => $status,
            'grid_width' => $grid_width,
            'grid_height' => $grid_height,
            'start_x' => $start_x,
            'start_y' => $start_y,
            'goal_x' => $goal_x,
            'goal_y' => $goal_y,
            'walls' => $walls,
        ];

        $classroom_check = $conn->query("SELECT id FROM classrooms WHERE id=$classroom_id AND teacher_id=$teacher_id");

        $coordinates_valid =
            $start_x < $grid_width &&
            $goal_x < $grid_width &&
            $start_y < $grid_height &&
            $goal_y < $grid_height &&
            !($start_x === $goal_x && $start_y === $goal_y);

        if ($title !== '' && $description !== '' && $instructions !== '' && $classroom_check && $classroom_check->num_rows > 0 && $coordinates_valid) {
            if ($level_id > 0) {
                $sql = "UPDATE teacher_levels
                        SET classroom_id=$classroom_id,
                            title='$title',
                            description='$description',
                            instructions='$instructions',
                            difficulty='$difficulty',
                            status='$status',
                            grid_width=$grid_width,
                            grid_height=$grid_height,
                            start_x=$start_x,
                            start_y=$start_y,
                            goal_x=$goal_x,
                            goal_y=$goal_y,
                            walls='$walls'
                        WHERE id=$level_id AND teacher_id=$teacher_id";
                $conn->query($sql);
            } else {
                $sql = "INSERT INTO teacher_levels (
                            teacher_id, classroom_id, title, description, instructions, difficulty, status,
                            grid_width, grid_height, start_x, start_y, goal_x, goal_y, walls
                        )
                        VALUES (
                            $teacher_id, $classroom_id, '$title', '$description', '$instructions', '$difficulty', '$status',
                            $grid_width, $grid_height, $start_x, $start_y, $goal_x, $goal_y, '$walls'
                        )";
                $conn->query($sql);
            }

            header("Location: teacher_levels.php");
            exit();
        }

        $message = "Each level needs a classroom, valid grid coordinates, and all required fields.";
    }

    if ($action === 'delete_level') {
        $level_id = (int)($_POST['level_id'] ?? 0);
        $conn->query("DELETE FROM teacher_levels WHERE id=$level_id AND teacher_id=$teacher_id");
        header("Location: teacher_levels.php");
        exit();
    }
}

$editing = null;
$edit_id = (int)($_GET['edit'] ?? 0);

if ($edit_id > 0) {
    $edit_result = $conn->query("SELECT * FROM teacher_levels WHERE id=$edit_id AND teacher_id=$teacher_id");
    if ($edit_result && $edit_result->num_rows > 0) {
        $editing = $edit_result->fetch_assoc();
    }
}

if (!empty($level_form)) {
    $editing = $level_form;
}

$classrooms = $conn->query("
SELECT c.*,
       COUNT(cm.id) AS student_count,
       COUNT(l.id) AS level_count
FROM classrooms c
LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
LEFT JOIN teacher_levels l ON l.classroom_id = c.id
WHERE c.teacher_id = '$teacher_id'
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
WHERE l.teacher_id = '$teacher_id'
GROUP BY l.id
ORDER BY l.created_at DESC
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
WHERE l.teacher_id = '$teacher_id'
ORDER BY c.name ASC, l.created_at DESC, u.username ASC
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
WHERE c.teacher_id = '$teacher_id'
ORDER BY c.name ASC, u.username ASC
");

$teacher_overview = $conn->query("
SELECT
    (SELECT COUNT(*) FROM classrooms WHERE teacher_id = '$teacher_id') AS total_classrooms,
    (SELECT COUNT(*) FROM teacher_levels WHERE teacher_id = '$teacher_id') AS total_levels,
    (SELECT COUNT(*)
     FROM classroom_members cm
     INNER JOIN classrooms c ON c.id = cm.classroom_id
     WHERE c.teacher_id = '$teacher_id') AS total_students
")->fetch_assoc();

$classroom_select_options = [];
$classroom_options_result = $conn->query("
SELECT id, name
FROM classrooms
WHERE teacher_id = '$teacher_id'
ORDER BY name ASC
");

if ($classroom_options_result && $classroom_options_result->num_rows > 0) {
    while ($classroom_option = $classroom_options_result->fetch_assoc()) {
        $classroom_select_options[] = $classroom_option;
    }
}

$has_classrooms = count($classroom_select_options) > 0;

$editor_walls = trim((string)($editing['walls'] ?? ''));
$editor_wall_points = [];

if ($editor_walls !== '') {
    $editor_parts = preg_split('/\s+/', $editor_walls);
    foreach ($editor_parts as $editor_part) {
        $editor_coords = explode(',', $editor_part);
        if (count($editor_coords) === 2) {
            $editor_wall_points[] = [
                'x' => (int)$editor_coords[0],
                'y' => (int)$editor_coords[1],
            ];
        }
    }
}
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
            <div class="report_header">
                <div>
                    <h2>Teacher Dashboard</h2>
                    <p>Manage classrooms, assign students, create levels, and monitor classroom progress.</p>
                </div>
                <div class="table_actions">
                    <a class="secondary_button" href="level_editor.php">Open Level Editor</a>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="form_error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="stats_grid">
                <div class="stat_card">
                    <strong><?= (int)$teacher_overview['total_classrooms'] ?></strong>
                    <span>Classrooms</span>
                </div>
                <div class="stat_card">
                    <strong><?= (int)$teacher_overview['total_levels'] ?></strong>
                    <span>Levels</span>
                </div>
                <div class="stat_card">
                    <strong><?= (int)$teacher_overview['total_students'] ?></strong>
                    <span>Tracked students</span>
                </div>
            </div>
        </div>

        <div class="page teacher_page">
            <div class="teacher_split">
                <form method="POST" class="teacher_form">
                    <input type="hidden" name="action" value="save_classroom">
                    <div class="form_heading">
                        <h3>Create a classroom</h3>
                    </div>
                    <p class="form_hint">Create the classroom first. Levels can only be assigned to an existing classroom.</p>
                    <label for="classroom_name">Classroom name</label>
                    <input type="text" id="classroom_name" name="classroom_name" required>

                    <label for="classroom_description">Description</label>
                    <input type="text" id="classroom_description" name="classroom_description" required>

                    <button type="submit">Create classroom</button>
                </form>

                <form method="POST" class="teacher_form">
                    <input type="hidden" name="action" value="save_level">
                    <input type="hidden" name="level_id" value="<?= $editing ? (int)($editing['id'] ?? 0) : 0 ?>">

                    <div class="form_heading">
                        <h3><?= $editing ? 'Edit level' : 'Create a new level' ?></h3>
                        <?php if ($editing): ?>
                            <a class="secondary_link" href="teacher_levels.php">Cancel editing</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$has_classrooms): ?>
                        <div class="empty_state">
                            <strong>Create a classroom before creating a level.</strong>
                            <span>The level form is disabled until at least one classroom exists.</span>
                        </div>
                    <?php else: ?>
                        <div class="callout compact_notice">
                            <strong>Assign each level to one classroom.</strong>
                            <span>Students will only see levels from classrooms they belong to.</span>
                        </div>
                    <?php endif; ?>

                    <label for="classroom_id">Classroom</label>
                    <select id="classroom_id" name="classroom_id" required <?= !$has_classrooms ? 'disabled' : '' ?>>
                        <option value="">Select classroom</option>
                        <?php if ($has_classrooms): ?>
                            <?php foreach ($classroom_select_options as $classroom): ?>
                                <option value="<?= (int)$classroom['id'] ?>" <?= ((int)($editing['classroom_id'] ?? 0) === (int)$classroom['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classroom['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <label for="title">Level title</label>
                    <input type="text" id="title" name="title" required value="<?= htmlspecialchars($editing['title'] ?? '') ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>

                    <label for="description">Short description</label>
                    <input type="text" id="description" name="description" required value="<?= htmlspecialchars($editing['description'] ?? '') ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>

                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="6" required <?= !$has_classrooms ? 'disabled' : '' ?>><?= htmlspecialchars($editing['instructions'] ?? '') ?></textarea>

                    <div class="teacher_form_row">
                        <div>
                            <label for="grid_width">Grid width</label>
                            <input type="number" id="grid_width" name="grid_width" min="3" max="14" value="<?= (int)($editing['grid_width'] ?? 6) ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>
                        </div>
                        <div>
                            <label for="grid_height">Grid height</label>
                            <input type="number" id="grid_height" name="grid_height" min="3" max="14" value="<?= (int)($editing['grid_height'] ?? 6) ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <div class="teacher_form_row">
                        <div>
                            <label for="start_x">Start X</label>
                            <input type="number" id="start_x" name="start_x" min="0" value="<?= (int)($editing['start_x'] ?? 0) ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>
                        </div>
                        <div>
                            <label for="start_y">Start Y</label>
                            <input type="number" id="start_y" name="start_y" min="0" value="<?= (int)($editing['start_y'] ?? 0) ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>
                        </div>
                        <div>
                            <label for="goal_x">Goal X</label>
                            <input type="number" id="goal_x" name="goal_x" min="0" value="<?= (int)($editing['goal_x'] ?? 5) ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>
                        </div>
                        <div>
                            <label for="goal_y">Goal Y</label>
                            <input type="number" id="goal_y" name="goal_y" min="0" value="<?= (int)($editing['goal_y'] ?? 5) ?>" <?= !$has_classrooms ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <div class="level_editor_panel <?= !$has_classrooms ? 'is-disabled' : '' ?>">
                        <div class="form_heading">
                            <h3>Visual level editor</h3>
                        </div>
                        <div class="editor_toolbar">
                            <button type="button" class="editor_tool is-active" data-tool="wall" <?= !$has_classrooms ? 'disabled' : '' ?>>Wall</button>
                            <button type="button" class="editor_tool" data-tool="start" <?= !$has_classrooms ? 'disabled' : '' ?>>Start</button>
                            <button type="button" class="editor_tool" data-tool="goal" <?= !$has_classrooms ? 'disabled' : '' ?>>Goal</button>
                            <button type="button" class="editor_tool" data-tool="erase" <?= !$has_classrooms ? 'disabled' : '' ?>>Erase</button>
                        </div>
                        <div
                            id="teacher-level-editor"
                            class="level_board editor_board"
                            data-width="<?= (int)($editing['grid_width'] ?? 6) ?>"
                            data-height="<?= (int)($editing['grid_height'] ?? 6) ?>"
                            data-start-x="<?= (int)($editing['start_x'] ?? 0) ?>"
                            data-start-y="<?= (int)($editing['start_y'] ?? 0) ?>"
                            data-goal-x="<?= (int)($editing['goal_x'] ?? 5) ?>"
                            data-goal-y="<?= (int)($editing['goal_y'] ?? 5) ?>"
                            data-walls='<?= htmlspecialchars(json_encode($editor_wall_points), ENT_QUOTES) ?>'
                        ></div>
                        <p class="form_hint">Click or drag to paint walls. Use the other tools to move the start and goal.</p>
                    </div>

                    <label for="walls">Walls</label>
                    <textarea id="walls" name="walls" rows="4" placeholder="Enter blocked cells like 1,0 1,1 3,4" <?= !$has_classrooms ? 'disabled' : '' ?>><?= htmlspecialchars($editing['walls'] ?? '') ?></textarea>
                    <p class="form_hint">Use space or new lines between coordinates. Example: <code>1,0 1,1 1,2</code></p>

                    <div class="teacher_form_row">
                        <div>
                            <label for="difficulty">Difficulty</label>
                            <select id="difficulty" name="difficulty" <?= !$has_classrooms ? 'disabled' : '' ?>>
                                <option value="beginner" <?= (($editing['difficulty'] ?? '') === 'beginner') ? 'selected' : '' ?>>Beginner</option>
                                <option value="intermediate" <?= (($editing['difficulty'] ?? '') === 'intermediate') ? 'selected' : '' ?>>Intermediate</option>
                                <option value="advanced" <?= (($editing['difficulty'] ?? '') === 'advanced') ? 'selected' : '' ?>>Advanced</option>
                            </select>
                        </div>
                        <div>
                            <label for="status">Status</label>
                            <select id="status" name="status" <?= !$has_classrooms ? 'disabled' : '' ?>>
                                <option value="draft" <?= (($editing['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= (($editing['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" <?= !$has_classrooms ? 'disabled' : '' ?>><?= $editing ? 'Update level' : 'Save level' ?></button>
                </form>
            </div>
        </div>

        <div class="page teacher_page">
            <h2>Your Classrooms</h2>
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
                            <input type="text" id="student_<?= (int)$classroom['id'] ?>" name="student_username" placeholder="student username" required>
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
            <h2>Classroom Members</h2>
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
                        <tr>
                            <td colspan="4">No classrooms or students available yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="page teacher_page">
            <h2>Your Levels</h2>
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
                            <td><?= htmlspecialchars($level['status']) ?></td>
                            <td><?= htmlspecialchars($level['difficulty']) ?></td>
                            <td><?= (int)$level['tracked_students'] ?></td>
                            <td><?= (int)$level['completed_students'] ?></td>
                            <td><?= (int)$level['active_students'] ?></td>
                            <td>
                                <div class="table_actions">
                                    <a class="secondary_link" href="teacher_levels.php?edit=<?= (int)$level['id'] ?>">Edit</a>
                                    <a class="secondary_link" href="level_editor.php?id=<?= (int)$level['id'] ?>">Open editor</a>
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
                        <tr>
                            <td colspan="8">No levels created yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="page teacher_page">
            <h2>Student Progress</h2>
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
                            <td><?= htmlspecialchars($progress['difficulty']) ?></td>
                            <td><?= htmlspecialchars($progress['status']) ?></td>
                            <td><?= (int)$progress['attempts'] ?></td>
                            <td><?= htmlspecialchars($progress['last_played_at'] ?? 'Not started') ?></td>
                            <td><?= htmlspecialchars($progress['completed_at'] ?? '-') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">No student activity recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const editor = document.getElementById('teacher-level-editor');
        if (!editor) return;

        const gridWidthInput = document.getElementById('grid_width');
        const gridHeightInput = document.getElementById('grid_height');
        const startXInput = document.getElementById('start_x');
        const startYInput = document.getElementById('start_y');
        const goalXInput = document.getElementById('goal_x');
        const goalYInput = document.getElementById('goal_y');
        const wallsInput = document.getElementById('walls');
        const toolButtons = document.querySelectorAll('.editor_tool');

        if (gridWidthInput.disabled) return;

        let activeTool = 'wall';
        let gridWidth = parseInt(editor.dataset.width, 10);
        let gridHeight = parseInt(editor.dataset.height, 10);
        let start = { x: parseInt(editor.dataset.startX, 10), y: parseInt(editor.dataset.startY, 10) };
        let goal = { x: parseInt(editor.dataset.goalX, 10), y: parseInt(editor.dataset.goalY, 10) };
        let walls = new Set((JSON.parse(editor.dataset.walls || '[]')).map((wall) => wall.x + ',' + wall.y));
        let isPointerDown = false;

        function setActiveTool(tool) {
            activeTool = tool;
            toolButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.tool === tool);
            });
        }

        function syncFields() {
            startXInput.value = start.x;
            startYInput.value = start.y;
            goalXInput.value = goal.x;
            goalYInput.value = goal.y;
            wallsInput.value = Array.from(walls).sort().join(' ');
        }

        function clampPoint(point) {
            point.x = Math.max(0, Math.min(gridWidth - 1, point.x));
            point.y = Math.max(0, Math.min(gridHeight - 1, point.y));
        }

        function normalizeState() {
            gridWidth = Math.max(3, parseInt(gridWidthInput.value || '6', 10));
            gridHeight = Math.max(3, parseInt(gridHeightInput.value || '6', 10));
            gridWidthInput.value = gridWidth;
            gridHeightInput.value = gridHeight;

            start = {
                x: parseInt(startXInput.value || '0', 10),
                y: parseInt(startYInput.value || '0', 10)
            };
            goal = {
                x: parseInt(goalXInput.value || '0', 10),
                y: parseInt(goalYInput.value || '0', 10)
            };

            clampPoint(start);
            clampPoint(goal);

            if (start.x === goal.x && start.y === goal.y) {
                goal.x = Math.min(gridWidth - 1, start.x + 1);
                goal.y = start.y;
                if (goal.x === start.x && goal.y === start.y) {
                    goal.x = start.x;
                    goal.y = Math.min(gridHeight - 1, start.y + 1);
                }
            }

            walls = new Set(Array.from(walls).filter((key) => {
                const [x, y] = key.split(',').map(Number);
                if (x < 0 || y < 0 || x >= gridWidth || y >= gridHeight) return false;
                if (x === start.x && y === start.y) return false;
                if (x === goal.x && y === goal.y) return false;
                return true;
            }));

            syncFields();
        }

        function applyToolAt(x, y) {
            const targetKey = x + ',' + y;

            if (activeTool === 'wall') {
                if (targetKey !== start.x + ',' + start.y && targetKey !== goal.x + ',' + goal.y) {
                    walls.add(targetKey);
                }
            }

            if (activeTool === 'erase') {
                walls.delete(targetKey);
            }

            if (activeTool === 'start') {
                start = { x: x, y: y };
                walls.delete(targetKey);
                if (goal.x === x && goal.y === y) {
                    goal = { x: Math.min(gridWidth - 1, x + 1), y: y };
                }
            }

            if (activeTool === 'goal') {
                goal = { x: x, y: y };
                walls.delete(targetKey);
                if (start.x === x && start.y === y) {
                    start = { x: Math.max(0, x - 1), y: y };
                }
            }
        }

        function renderEditor() {
            normalizeState();
            editor.style.gridTemplateColumns = 'repeat(' + gridWidth + ', 40px)';
            editor.innerHTML = '';

            for (let y = 0; y < gridHeight; y++) {
                for (let x = 0; x < gridWidth; x++) {
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'board_cell editor_cell';
                    cell.dataset.x = x;
                    cell.dataset.y = y;

                    const key = x + ',' + y;
                    if (walls.has(key)) {
                        cell.classList.add('wall');
                    }

                    if (goal.x === x && goal.y === y) {
                        cell.classList.add('goal');
                        cell.textContent = 'G';
                    }

                    if (start.x === x && start.y === y) {
                        cell.classList.add('player');
                        cell.textContent = 'C';
                    }

                    cell.addEventListener('pointerdown', function (event) {
                        event.preventDefault();
                        isPointerDown = true;
                        applyToolAt(x, y);
                        renderEditor();
                    });

                    cell.addEventListener('pointerenter', function () {
                        if (!isPointerDown) return;
                        if (activeTool !== 'wall' && activeTool !== 'erase') return;
                        applyToolAt(x, y);
                        renderEditor();
                    });

                    editor.appendChild(cell);
                }
            }
        }

        toolButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setActiveTool(button.dataset.tool);
            });
        });

        [gridWidthInput, gridHeightInput, startXInput, startYInput, goalXInput, goalYInput].forEach((input) => {
            input.addEventListener('input', renderEditor);
        });

        wallsInput.addEventListener('input', function () {
            const nextWalls = new Set();
            wallsInput.value.trim().split(/\s+/).filter(Boolean).forEach((part) => {
                const coords = part.split(',');
                if (coords.length === 2) {
                    nextWalls.add(parseInt(coords[0], 10) + ',' + parseInt(coords[1], 10));
                }
            });
            walls = nextWalls;
            renderEditor();
        });

        window.addEventListener('pointerup', function () {
            isPointerDown = false;
        });

        setActiveTool('wall');
        renderEditor();
    }());
    </script>
</body>
</html>
