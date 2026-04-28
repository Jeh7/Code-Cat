<?php
session_start();
include "db.php";
include "flash.php";
include "teacher_report_helpers.php";
include "achievement_helpers.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo "Access denied";
    exit();
}

$teacher_id = (int)($_SESSION['id'] ?? 0);
teacher_reports_ensure_table($conn);

$classrooms = [];
$classroom_result = $conn->query("
    SELECT c.id, c.name, c.description,
           COUNT(DISTINCT cm.id) AS student_count,
           COUNT(DISTINCT l.id) AS level_count
    FROM classrooms c
    LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
    LEFT JOIN teacher_levels l ON l.classroom_id = c.id
    WHERE c.teacher_id = $teacher_id
    GROUP BY c.id
    ORDER BY c.name ASC
");

if ($classroom_result && $classroom_result->num_rows > 0) {
    while ($classroom = $classroom_result->fetch_assoc()) {
        $classrooms[] = $classroom;
    }
}

$selected_classroom_id = (int)($_GET['classroom_id'] ?? 0);
if ($selected_classroom_id === 0 && isset($classrooms[0]['id'])) {
    $selected_classroom_id = (int)$classrooms[0]['id'];
}

function teacher_reports_find_classroom(array $classrooms, int $classroom_id): ?array
{
    foreach ($classrooms as $classroom) {
        if ((int)$classroom['id'] === $classroom_id) {
            return $classroom;
        }
    }

    return null;
}

function teacher_reports_generate_lines(mysqli $conn, int $teacher_id, int $classroom_id): array
{
    $summary_stmt = $conn->prepare("
        SELECT c.name,
               c.description,
               COUNT(DISTINCT cm.student_id) AS total_students,
               COUNT(DISTINCT l.id) AS total_levels,
               SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_entries,
               SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) AS active_entries,
               SUM(CASE WHEN p.status = 'not_started' THEN 1 ELSE 0 END) AS not_started_entries
        FROM classrooms c
        LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
        LEFT JOIN teacher_levels l ON l.classroom_id = c.id
        LEFT JOIN student_level_progress p ON p.level_id = l.id AND p.student_id = cm.student_id
        WHERE c.id = ?
          AND c.teacher_id = ?
        GROUP BY c.id
        LIMIT 1
    ");

    if (!$summary_stmt) {
        return [];
    }

    $summary_stmt->bind_param("ii", $classroom_id, $teacher_id);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary = $summary_result ? $summary_result->fetch_assoc() : null;
    $summary_stmt->close();

    if (!$summary) {
        return [];
    }

    $student_rows = [];
    $student_stmt = $conn->prepare("
        SELECT u.username,
               u.email,
               COUNT(DISTINCT p.id) AS tracked_entries,
               SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_entries,
               SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) AS active_entries,
               COALESCE(MAX(p.last_played_at), NULL) AS last_activity
        FROM classroom_members cm
        INNER JOIN users u ON u.id = cm.student_id
        LEFT JOIN teacher_levels l ON l.classroom_id = cm.classroom_id
        LEFT JOIN student_level_progress p ON p.level_id = l.id AND p.student_id = cm.student_id
        WHERE cm.classroom_id = ?
        GROUP BY u.id, u.username, u.email
        ORDER BY u.username ASC
    ");

    if ($student_stmt) {
        $student_stmt->bind_param("i", $classroom_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        if ($student_result && $student_result->num_rows > 0) {
            while ($student = $student_result->fetch_assoc()) {
                $student_rows[] = $student;
            }
        }
        $student_stmt->close();
    }

    $lines = [
        'Teacher: ' . (string)($_SESSION['user'] ?? 'Teacher'),
        'Generated: ' . date('Y-m-d H:i:s'),
        '',
        'Classroom: ' . (string)$summary['name'],
        'Description: ' . (string)$summary['description'],
        'Students: ' . (int)$summary['total_students'],
        'Levels: ' . (int)$summary['total_levels'],
        'Completed progress entries: ' . (int)$summary['completed_entries'],
        'Active progress entries: ' . (int)$summary['active_entries'],
        'Not started entries: ' . (int)$summary['not_started_entries'],
        '',
        'Student Breakdown'
    ];

    if (!$student_rows) {
        $lines[] = 'No enrolled students were found for this classroom.';
        return $lines;
    }

    foreach ($student_rows as $student) {
        $lines[] = '- ' . (string)$student['username']
            . ' | Email: ' . (string)$student['email']
            . ' | Tracked: ' . (int)$student['tracked_entries']
            . ' | Completed: ' . (int)$student['completed_entries']
            . ' | Active: ' . (int)$student['active_entries']
            . ' | Last activity: ' . ((string)($student['last_activity'] ?? '') !== '' ? (string)$student['last_activity'] : 'No activity');
    }

    return $lines;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'generate_pdf') {
        $classroom_id = (int)($_POST['classroom_id'] ?? 0);
        $classroom = teacher_reports_find_classroom($classrooms, $classroom_id);

        if (!$classroom) {
            flash_add('error', 'Choose one of your classrooms before generating a PDF report.');
        } else {
            $lines = teacher_reports_generate_lines($conn, $teacher_id, $classroom_id);
            if (!$lines) {
                flash_add('error', 'The selected classroom could not be used to generate a report.');
            } else {
                $title = (string)$classroom['name'] . ' Progress Report';
                $pdf = teacher_reports_build_pdf($title, $lines);
                $storage_dir = teacher_reports_ensure_storage_dir();
                $safe_name = teacher_reports_slugify($title);
                $filename = ($safe_name !== '' ? $safe_name : 'teacher-report') . '-' . time() . '.pdf';
                $absolute_path = $storage_dir . DIRECTORY_SEPARATOR . $filename;

                if (file_put_contents($absolute_path, $pdf) !== false) {
                    $relative_path = 'uploads/teacher_reports/' . $filename;
                    $summary = 'Generated classroom progress report for ' . (string)$classroom['name'];
                    $stmt = $conn->prepare("
                        INSERT INTO teacher_reports (teacher_id, classroom_id, title, report_type, summary, file_path, original_filename)
                        VALUES (?, ?, ?, 'generated_pdf', ?, ?, ?)
                    ");

                    if ($stmt) {
                        $original_filename = $filename;
                        $stmt->bind_param(
                            "iissss",
                            $teacher_id,
                            $classroom_id,
                            $title,
                            $summary,
                            $relative_path,
                            $original_filename
                        );
                        $saved = $stmt->execute();
                        $stmt->close();
                        if ($saved) {
                            achievement_unlock_by_title($conn, $teacher_id, 'Report Maker');
                            flash_add('success', 'PDF report generated successfully.');
                            header("Location: teacher_reports.php");
                            exit();
                        }

                        @unlink($absolute_path);
                        flash_add('error', 'The PDF was created, but the report record could not be saved.');
                    } else {
                        @unlink($absolute_path);
                        flash_add('error', 'The PDF was created, but the report record could not be saved.');
                    }
                } else {
                    flash_add('error', 'The PDF report could not be written to storage.');
                }
            }
        }
    }

    if ($action === 'delete_report') {
        $report_id = (int)($_POST['report_id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_path FROM teacher_reports WHERE id = ? AND teacher_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ii", $report_id, $teacher_id);
            $stmt->execute();
            $report_result = $stmt->get_result();
            $report = $report_result ? $report_result->fetch_assoc() : null;
            $stmt->close();

            if ($report) {
                $absolute_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$report['file_path']);
                $delete_stmt = $conn->prepare("DELETE FROM teacher_reports WHERE id = ? AND teacher_id = ?");
                if ($delete_stmt) {
                    $delete_stmt->bind_param("ii", $report_id, $teacher_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    if (is_file($absolute_path)) {
                        @unlink($absolute_path);
                    }
                    flash_add('success', 'Report removed.');
                    header("Location: teacher_reports.php");
                    exit();
                } else {
                    flash_add('error', 'The report could not be deleted.');
                }
            } else {
                flash_add('error', 'That report could not be found.');
            }
        }
    }
}

$report_rows = [];
$report_stmt = $conn->prepare("
    SELECT tr.*, c.name AS classroom_name
    FROM teacher_reports tr
    LEFT JOIN classrooms c ON c.id = tr.classroom_id
    WHERE tr.teacher_id = ?
    ORDER BY tr.created_at DESC, tr.id DESC
");

if ($report_stmt) {
    $report_stmt->bind_param("i", $teacher_id);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();
    if ($report_result && $report_result->num_rows > 0) {
        while ($report = $report_result->fetch_assoc()) {
            $report_rows[] = $report;
        }
    }
    $report_stmt->close();
}

$generated_count = 0;
foreach ($report_rows as $report_row) {
    if (($report_row['report_type'] ?? '') === 'generated_pdf') {
        $generated_count++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Reports</title>
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
                <a class="secondary_button" href="teacher_levels.php">Back to Dashboard</a>
            </div>

            <div class="dashboard_hero">
                <div>
                    <h2>Teacher Reports</h2>
                    <p>Generate classroom progress PDFs from the data already tracked in Code Cat, then export or download them when needed.</p>
                </div>
                <div class="dashboard_summary">
                    <div class="dashboard_summary_card">
                        <strong><?= count($report_rows) ?></strong>
                        <span>Saved teacher reports</span>
                    </div>
                    <div class="dashboard_summary_card">
                        <strong><?= $generated_count ?></strong>
                        <span>Generated PDFs</span>
                    </div>
                </div>
            </div>

            <?= render_flash_messages() ?>

            <?php if (!$classrooms): ?>
                <div class="empty_state">
                    <strong>Create a classroom first.</strong>
                    <span>Teacher PDF reports are tied to a classroom. Add a classroom from the teacher dashboard before generating reports here.</span>
                </div>
            <?php else: ?>
                <form method="POST" class="teacher_form">
                    <input type="hidden" name="action" value="generate_pdf">
                    <div class="form_heading">
                        <h3>Generate classroom PDF</h3>
                    </div>
                    <p class="form_hint">Create a progress report from classroom, level, and student activity data already stored in the app.</p>

                    <div class="field_group">
                        <label for="generate_classroom_id">Classroom</label>
                        <select id="generate_classroom_id" name="classroom_id" required>
                            <option value="">Select classroom</option>
                            <?php foreach ($classrooms as $classroom): ?>
                                <option value="<?= (int)$classroom['id'] ?>" <?= $selected_classroom_id === (int)$classroom['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classroom['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="callout compact_notice">
                        <strong>Included in the PDF</strong>
                        <span>Classroom summary, level counts, progress totals, and a student-by-student activity breakdown.</span>
                    </div>

                    <button type="submit">Generate PDF Report</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="page teacher_page">
            <div class="dashboard_section_header">
                <h2>Saved Reports</h2>
                <p>All generated PDFs for your classrooms appear here so you can download or remove them later.</p>
            </div>

            <div class="table_wrap">
                <table class="report_table">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Classroom</th>
                        <th>Summary</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    <?php if ($report_rows): ?>
                        <?php foreach ($report_rows as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$report['title']) ?></td>
                            <td><span class="status_pill"><?= htmlspecialchars((string)$report['report_type']) ?></span></td>
                            <td><?= htmlspecialchars((string)($report['classroom_name'] ?? 'Unassigned')) ?></td>
                            <td><?= htmlspecialchars((string)($report['summary'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)$report['created_at']) ?></td>
                            <td>
                                <div class="table_actions">
                                    <a class="secondary_link" href="teacher_report_download.php?id=<?= (int)$report['id'] ?>">Download PDF</a>
                                    <form method="POST" class="inline_form">
                                        <input type="hidden" name="action" value="delete_report">
                                        <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                        <button type="submit" class="link_button">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty_state_row">
                            <td colspan="6">No teacher PDF reports have been saved yet.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
