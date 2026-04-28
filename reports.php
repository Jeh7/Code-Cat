<?php
session_start();
include "db.php";
include "flash.php";
include "admin_report_helpers.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "Access denied";
    exit();
}

$admin_id = (int)($_SESSION['id'] ?? 0);
admin_reports_ensure_table($conn);
$allowed_roles = ['student', 'teacher', 'admin', 'na'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_user') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $new_role = trim((string)($_POST['user_role'] ?? 'na'));

        if ($username === '' || $email === '' || $password === '' || !in_array($new_role, $allowed_roles, true)) {
            flash_add('error', 'Complete username, email, password, and role before creating a user.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_add('error', 'Use a valid email address for the new user.');
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, email, role)
                VALUES (?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param("ssss", $username, $password_hash, $email, $new_role);
                if ($stmt->execute()) {
                    flash_add('success', 'User created successfully.');
                    header("Location: reports.php");
                    exit();
                }

                flash_add('error', 'That username or email is already in use.');
                $stmt->close();
            } else {
                flash_add('error', 'User creation is temporarily unavailable.');
            }
        }
    }

    if ($action === 'update_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $new_role = trim((string)($_POST['user_role'] ?? 'na'));

        if ($user_id <= 0 || $username === '' || $email === '' || !in_array($new_role, $allowed_roles, true)) {
            flash_add('error', 'Complete username, email, and role before saving changes.');
        } elseif ($user_id === $admin_id && $new_role !== 'admin') {
            flash_add('error', 'You cannot remove the admin role from the account you are currently using.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_add('error', 'Use a valid email address before saving changes.');
        } else {
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users
                    SET username = ?, email = ?, role = ?, password = ?
                    WHERE id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param("ssssi", $username, $email, $new_role, $password_hash, $user_id);
                }
            } else {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET username = ?, email = ?, role = ?
                    WHERE id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param("sssi", $username, $email, $new_role, $user_id);
                }
            }

            if (isset($stmt) && $stmt) {
                if ($stmt->execute()) {
                    if ($user_id === $admin_id) {
                        $_SESSION['user'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $new_role;
                    }

                    flash_add('success', 'User updated successfully.');
                    header("Location: reports.php");
                    exit();
                }

                flash_add('error', 'That username or email is already in use.');
                $stmt->close();
            } else {
                flash_add('error', 'User updates are temporarily unavailable.');
            }
        }
    }

    if ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            flash_add('error', 'Choose a valid user to delete.');
        } elseif ($user_id === $admin_id) {
            flash_add('error', 'You cannot delete the admin account you are currently using.');
        } else {
            $delete_target = null;
            $target_stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            if ($target_stmt) {
                $target_stmt->bind_param("i", $user_id);
                $target_stmt->execute();
                $target_result = $target_stmt->get_result();
                $delete_target = $target_result ? $target_result->fetch_assoc() : null;
                $target_stmt->close();
            }

            if (($delete_target['role'] ?? '') === 'admin') {
                $admin_count_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
                $admin_count_row = $admin_count_result ? $admin_count_result->fetch_assoc() : ['total' => 0];
                if ((int)$admin_count_row['total'] <= 1) {
                    flash_add('error', 'You cannot delete the last admin account.');
                    $delete_target = null;
                }
            }

            if (!$delete_target) {
                if (!isset($admin_count_row) || (int)($admin_count_row['total'] ?? 0) > 1) {
                    flash_add('error', 'That user could not be found.');
                }
            } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $deleted = $stmt->affected_rows > 0;
                $stmt->close();

                if ($deleted) {
                    flash_add('success', 'User deleted successfully.');
                    header("Location: reports.php");
                    exit();
                }
            }

            flash_add('error', 'That user could not be deleted.');
            }
        }
    }

    if ($action === 'generate_admin_report') {
        $report_type = (string)($_POST['report_type'] ?? '');
        $allowed_report_types = ['user_roster', 'role_summary', 'classroom_activity', 'achievement_summary'];

        if (!in_array($report_type, $allowed_report_types, true)) {
            flash_add('error', 'Choose a valid admin report type.');
        } else {
            $title = admin_reports_type_label($report_type);
            $lines = admin_reports_generate_lines($conn, $report_type);

            if (!$lines) {
                flash_add('error', 'That report could not be generated.');
            } else {
                $pdf = teacher_reports_build_pdf($title, $lines);
                $storage_dir = admin_reports_ensure_storage_dir();
                $filename = teacher_reports_slugify($title) . '-' . time() . '.pdf';
                $absolute_path = $storage_dir . DIRECTORY_SEPARATOR . $filename;

                if (file_put_contents($absolute_path, $pdf) !== false) {
                    $relative_path = 'uploads/admin_reports/' . $filename;
                    $summary = 'Generated ' . strtolower($title) . ' report.';
                    $stmt = $conn->prepare("
                        INSERT INTO admin_reports (admin_id, title, report_type, summary, file_path, original_filename)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    if ($stmt) {
                        $stmt->bind_param("isssss", $admin_id, $title, $report_type, $summary, $relative_path, $filename);
                        $saved = $stmt->execute();
                        $stmt->close();

                        if ($saved) {
                            flash_add('success', $title . ' generated successfully.');
                            header("Location: reports.php");
                            exit();
                        }
                    }

                    @unlink($absolute_path);
                    flash_add('error', 'The PDF was created, but the report record could not be saved.');
                } else {
                    flash_add('error', 'The PDF report could not be written to storage.');
                }
            }
        }
    }

    if ($action === 'delete_admin_report') {
        $report_id = (int)($_POST['report_id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_path FROM admin_reports WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $report_result = $stmt->get_result();
            $report = $report_result ? $report_result->fetch_assoc() : null;
            $stmt->close();

            if ($report) {
                $absolute_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$report['file_path']);
                $delete_stmt = $conn->prepare("DELETE FROM admin_reports WHERE id = ?");
                if ($delete_stmt) {
                    $delete_stmt->bind_param("i", $report_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();

                    if (is_file($absolute_path)) {
                        @unlink($absolute_path);
                    }

                    flash_add('success', 'Admin report removed.');
                    header("Location: reports.php");
                    exit();
                }
            }
        }

        flash_add('error', 'That admin report could not be removed.');
    }
}

$role = trim((string)($_GET['role'] ?? ''));

if ($role !== '' && !in_array($role, $allowed_roles, true)) {
    $role = '';
}

if ($role !== '') {
    $stmt = $conn->prepare("
        SELECT id, username, email, role, register_date
        FROM users
        WHERE role = ?
        ORDER BY role ASC, username ASC
    ");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT id, username, email, role, register_date
        FROM users
        ORDER BY role ASC, username ASC
    ");
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

$system_summary_result = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM classrooms) AS total_classrooms,
        (SELECT COUNT(*) FROM teacher_levels) AS total_levels,
        (SELECT COUNT(*) FROM student_level_progress WHERE status = 'completed') AS completed_entries,
        (SELECT COUNT(*) FROM teacher_reports) AS teacher_reports,
        (SELECT COUNT(*) FROM admin_reports) AS admin_reports
");
$system_summary = $system_summary_result ? $system_summary_result->fetch_assoc() : [
    'total_classrooms' => 0,
    'total_levels' => 0,
    'completed_entries' => 0,
    'teacher_reports' => 0,
    'admin_reports' => 0,
];

$progress_summary_result = $conn->query("
    SELECT
        COUNT(*) AS tracked_entries,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_entries,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS active_entries,
        COUNT(DISTINCT student_id) AS active_students
    FROM student_level_progress
");
$progress_summary = $progress_summary_result ? $progress_summary_result->fetch_assoc() : [
    'tracked_entries' => 0,
    'completed_entries' => 0,
    'active_entries' => 0,
    'active_students' => 0,
];

$level_status_result = $conn->query("
    SELECT
        COUNT(*) AS total_levels,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_levels,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_levels
    FROM teacher_levels
");
$level_status = $level_status_result ? $level_status_result->fetch_assoc() : [
    'total_levels' => 0,
    'published_levels' => 0,
    'draft_levels' => 0,
];

$achievement_summary_result = $conn->query("SELECT COUNT(*) AS total_unlocks FROM user_achievements");
$achievement_summary = $achievement_summary_result ? $achievement_summary_result->fetch_assoc() : ['total_unlocks' => 0];

$tracked_entries = (int)($progress_summary['tracked_entries'] ?? 0);
$completed_entries = (int)($progress_summary['completed_entries'] ?? 0);
$total_levels = (int)($level_status['total_levels'] ?? 0);
$published_levels = (int)($level_status['published_levels'] ?? 0);
$completion_rate = $tracked_entries > 0 ? (int)round(($completed_entries / $tracked_entries) * 100) : 0;
$publishing_rate = $total_levels > 0 ? (int)round(($published_levels / $total_levels) * 100) : 0;
$max_role_count = max(1, max($role_counts));
$progress_chart = [
    'Completed' => $completed_entries,
    'In progress' => (int)($progress_summary['active_entries'] ?? 0),
];
$max_progress_count = max(1, max($progress_chart));
$level_chart = [
    'Published' => $published_levels,
    'Draft' => (int)($level_status['draft_levels'] ?? 0),
];
$max_level_count = max(1, max($level_chart));
$report_chart = [
    'Admin PDFs' => (int)($system_summary['admin_reports'] ?? 0),
    'Teacher PDFs' => (int)($system_summary['teacher_reports'] ?? 0),
];
$max_report_count = max(1, max($report_chart));

$recent_users = [];
$recent_users_result = $conn->query("
    SELECT username, role, register_date
    FROM users
    ORDER BY register_date DESC, id DESC
    LIMIT 5
");
if ($recent_users_result && $recent_users_result->num_rows > 0) {
    while ($recent_user = $recent_users_result->fetch_assoc()) {
        $recent_users[] = $recent_user;
    }
}

$active_classrooms = [];
$active_classrooms_result = $conn->query("
    SELECT c.name,
           u.username AS teacher_name,
           COUNT(DISTINCT cm.student_id) AS students,
           COUNT(DISTINCT l.id) AS levels,
           COUNT(DISTINCT p.id) AS progress_entries
    FROM classrooms c
    INNER JOIN users u ON u.id = c.teacher_id
    LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
    LEFT JOIN teacher_levels l ON l.classroom_id = c.id
    LEFT JOIN student_level_progress p ON p.level_id = l.id AND p.student_id = cm.student_id
    GROUP BY c.id
    ORDER BY progress_entries DESC, students DESC, c.name ASC
    LIMIT 5
");
if ($active_classrooms_result && $active_classrooms_result->num_rows > 0) {
    while ($active_classroom = $active_classrooms_result->fetch_assoc()) {
        $active_classrooms[] = $active_classroom;
    }
}

$admin_reports = [];
$admin_report_result = $conn->query("
    SELECT *
    FROM admin_reports
    ORDER BY created_at DESC, id DESC
");

if ($admin_report_result && $admin_report_result->num_rows > 0) {
    while ($admin_report = $admin_report_result->fetch_assoc()) {
        $admin_reports[] = $admin_report;
    }
}

$edit_user_id = (int)($_GET['edit_user'] ?? 0);
$edit_user = null;
if ($edit_user_id > 0) {
    $edit_stmt = $conn->prepare("
        SELECT id, username, email, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    if ($edit_stmt) {
        $edit_stmt->bind_param("i", $edit_user_id);
        $edit_stmt->execute();
        $edit_result = $edit_stmt->get_result();
        $edit_user = $edit_result ? $edit_result->fetch_assoc() : null;
        $edit_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
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
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'teacher'): ?>
                        <a href="teacher_levels.php">Teacher Dashboard</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'student' || $_SESSION['role'] == 'na')): ?>
                        <a href="levels.php">Classroom Levels</a>
                    <?php endif; ?>
                    <a href="reports.php">Admin Reports</a>
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
                <h2>Admin Dashboard</h2>
                <p>Monitor accounts, classroom activity, progress, and generated reports from one administrative workspace.</p>
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

        <?= render_flash_messages() ?>

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
            <div class="dashboard_summary_card">
                <strong><?= (int)$system_summary['total_classrooms'] ?></strong>
                <span>Classrooms</span>
            </div>
            <div class="dashboard_summary_card">
                <strong><?= (int)$system_summary['total_levels'] ?></strong>
                <span>Teacher levels</span>
            </div>
            <div class="dashboard_summary_card">
                <strong><?= (int)$system_summary['completed_entries'] ?></strong>
                <span>Completed progress entries</span>
            </div>
            <div class="dashboard_summary_card">
                <strong><?= (int)$system_summary['admin_reports'] ?></strong>
                <span>Generated admin reports</span>
            </div>
        </div>

        <div class="admin_chart_grid">
            <div class="admin_chart_panel">
                <div class="dashboard_section_header">
                    <h3>Role distribution</h3>
                    <p>Current account mix by role.</p>
                </div>
                <div class="bar_chart">
                    <?php foreach ($role_counts as $chart_role => $chart_count): ?>
                    <div class="bar_row">
                        <span><?= htmlspecialchars(ucfirst($chart_role)) ?></span>
                        <div class="bar_track" aria-label="<?= htmlspecialchars($chart_role) ?> <?= (int)$chart_count ?>">
                            <span style="width: <?= (int)round(((int)$chart_count / $max_role_count) * 100) ?>%;"></span>
                        </div>
                        <strong><?= (int)$chart_count ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin_chart_panel">
                <div class="dashboard_section_header">
                    <h3>Progress status</h3>
                    <p><?= $completion_rate ?>% of tracked entries are complete.</p>
                </div>
                <div class="bar_chart">
                    <?php foreach ($progress_chart as $label => $count): ?>
                    <div class="bar_row">
                        <span><?= htmlspecialchars($label) ?></span>
                        <div class="bar_track" aria-label="<?= htmlspecialchars($label) ?> <?= (int)$count ?>">
                            <span style="width: <?= (int)round(((int)$count / $max_progress_count) * 100) ?>%;"></span>
                        </div>
                        <strong><?= (int)$count ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin_chart_panel">
                <div class="dashboard_section_header">
                    <h3>Level publishing</h3>
                    <p><?= $publishing_rate ?>% of levels are published.</p>
                </div>
                <div class="bar_chart">
                    <?php foreach ($level_chart as $label => $count): ?>
                    <div class="bar_row">
                        <span><?= htmlspecialchars($label) ?></span>
                        <div class="bar_track" aria-label="<?= htmlspecialchars($label) ?> <?= (int)$count ?>">
                            <span style="width: <?= (int)round(((int)$count / $max_level_count) * 100) ?>%;"></span>
                        </div>
                        <strong><?= (int)$count ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin_chart_panel">
                <div class="dashboard_section_header">
                    <h3>Generated reports</h3>
                    <p>PDF output across admin and teacher areas.</p>
                </div>
                <div class="bar_chart">
                    <?php foreach ($report_chart as $label => $count): ?>
                    <div class="bar_row">
                        <span><?= htmlspecialchars($label) ?></span>
                        <div class="bar_track" aria-label="<?= htmlspecialchars($label) ?> <?= (int)$count ?>">
                            <span style="width: <?= (int)round(((int)$count / $max_report_count) * 100) ?>%;"></span>
                        </div>
                        <strong><?= (int)$count ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="admin_insight_grid">
            <div class="dashboard_section">
                <div class="dashboard_section_header">
                    <h3>Most active classrooms</h3>
                    <p>Classrooms with the most tracked progress entries.</p>
                </div>
                <div class="admin_compact_list">
                    <?php if ($active_classrooms): ?>
                        <?php foreach ($active_classrooms as $classroom): ?>
                        <div class="admin_compact_item">
                            <strong><?= htmlspecialchars((string)$classroom['name']) ?></strong>
                            <span><?= (int)$classroom['progress_entries'] ?> progress entries · <?= (int)$classroom['students'] ?> students · <?= htmlspecialchars((string)$classroom['teacher_name']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty_state">
                            <strong>No classroom activity yet.</strong>
                            <span>Activity appears after teachers publish levels and students start playing.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard_section">
                <div class="dashboard_section_header">
                    <h3>Recent users</h3>
                    <p>Newest accounts created in the system.</p>
                </div>
                <div class="admin_compact_list">
                    <?php if ($recent_users): ?>
                        <?php foreach ($recent_users as $recent_user): ?>
                        <div class="admin_compact_item">
                            <strong><?= htmlspecialchars((string)$recent_user['username']) ?></strong>
                            <span><?= htmlspecialchars((string)$recent_user['role']) ?> · <?= htmlspecialchars((string)$recent_user['register_date']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty_state">
                            <strong>No users yet.</strong>
                            <span>Create the first account from the user management section.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="filter_card">
            <div class="dashboard_section_header">
                <h3>Generate admin PDF</h3>
                <p>Create an auditable PDF for the system area you need to review.</p>
            </div>
            <form method="POST" class="report_filters">
                <input type="hidden" name="action" value="generate_admin_report">
                <div class="field_group">
                    <label for="report_type">Report type</label>
                    <select name="report_type" id="report_type" required>
                        <option value="user_roster">User roster</option>
                        <option value="role_summary">Role and system summary</option>
                        <option value="classroom_activity">Classroom activity</option>
                        <option value="achievement_summary">Achievement summary</option>
                    </select>
                </div>
                <button type="submit">Generate PDF</button>
            </form>
        </div>

        <div class="table_wrap">
            <table class="report_table">
                <tr>
                    <th>Generated Report</th>
                    <th>Type</th>
                    <th>Summary</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
                <?php if ($admin_reports): ?>
                    <?php foreach ($admin_reports as $admin_report): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$admin_report['title']) ?></td>
                        <td><span class="status_pill"><?= htmlspecialchars(admin_reports_type_label((string)$admin_report['report_type'])) ?></span></td>
                        <td><?= htmlspecialchars((string)($admin_report['summary'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)$admin_report['created_at']) ?></td>
                        <td>
                            <div class="table_actions">
                                <a class="secondary_link" href="admin_report_download.php?id=<?= (int)$admin_report['id'] ?>">Download PDF</a>
                                <form method="POST" class="inline_form">
                                    <input type="hidden" name="action" value="delete_admin_report">
                                    <input type="hidden" name="report_id" value="<?= (int)$admin_report['id'] ?>">
                                    <button type="submit" class="link_button">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty_state_row">
                        <td colspan="5">No admin PDF reports have been generated yet.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="filter_card">
            <div class="dashboard_section_header">
                <h3>Create user</h3>
                <p>Add a student, teacher, admin, or unassigned account directly from the admin dashboard.</p>
            </div>
            <form method="POST" class="report_filters">
                <input type="hidden" name="action" value="create_user">
                <div class="field_group">
                    <label for="create_username">Username</label>
                    <input type="text" name="username" id="create_username" required>
                </div>
                <div class="field_group">
                    <label for="create_email">Email</label>
                    <input type="email" name="email" id="create_email" required>
                </div>
                <div class="field_group">
                    <label for="create_password">Password</label>
                    <input type="password" name="password" id="create_password" required>
                </div>
                <div class="field_group">
                    <label for="create_role">Role</label>
                    <select name="user_role" id="create_role" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Admin</option>
                        <option value="na">Not applicable</option>
                    </select>
                </div>
                <button type="submit">Create User</button>
            </form>
        </div>

        <?php if ($edit_user): ?>
        <div class="filter_card">
            <div class="dashboard_section_header">
                <h3>Edit user</h3>
                <p>Update account details. Leave password blank to keep the current password.</p>
            </div>
            <form method="POST" class="report_filters">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" value="<?= (int)$edit_user['id'] ?>">
                <div class="field_group">
                    <label for="edit_username">Username</label>
                    <input type="text" name="username" id="edit_username" required value="<?= htmlspecialchars((string)$edit_user['username']) ?>">
                </div>
                <div class="field_group">
                    <label for="edit_email">Email</label>
                    <input type="email" name="email" id="edit_email" required value="<?= htmlspecialchars((string)$edit_user['email']) ?>">
                </div>
                <div class="field_group">
                    <label for="edit_password">New password</label>
                    <input type="password" name="password" id="edit_password">
                </div>
                <div class="field_group">
                    <label for="edit_role">Role</label>
                    <select name="user_role" id="edit_role" required>
                        <option value="student" <?= $edit_user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= $edit_user['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="na" <?= $edit_user['role'] === 'na' ? 'selected' : '' ?>>Not applicable</option>
                    </select>
                </div>
                <button type="submit">Save Changes</button>
                <a class="secondary_button" href="reports.php">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

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
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><span class="status_pill"><?= htmlspecialchars($row['role']) ?></span></td>
                        <td><?= htmlspecialchars($row['register_date']) ?></td>
                        <td>
                            <div class="table_actions">
                                <a class="secondary_link" href="reports.php?edit_user=<?= (int)$row['id'] ?>">Edit</a>
                                <?php if ((int)$row['id'] !== $admin_id): ?>
                                <form method="POST" class="inline_form">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="link_button">Delete</button>
                                </form>
                                <?php else: ?>
                                    <span class="status_pill">Current account</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty_state_row">
                        <td colspan="6">No users match the current filter.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>
</html>
