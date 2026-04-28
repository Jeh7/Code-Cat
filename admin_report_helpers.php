<?php

include_once "teacher_report_helpers.php";

function admin_reports_ensure_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS admin_reports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            report_type ENUM('user_roster', 'role_summary', 'classroom_activity', 'achievement_summary') NOT NULL,
            summary TEXT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_reports_admin (admin_id),
            CONSTRAINT fk_admin_reports_admin
                FOREIGN KEY (admin_id) REFERENCES users(id)
                ON DELETE CASCADE
        )
    ");
}

function admin_reports_storage_dir(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'admin_reports';
}

function admin_reports_ensure_storage_dir(): string
{
    $dir = admin_reports_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function admin_reports_type_label(string $report_type): string
{
    $labels = [
        'user_roster' => 'User Roster',
        'role_summary' => 'Role Summary',
        'classroom_activity' => 'Classroom Activity',
        'achievement_summary' => 'Achievement Summary',
    ];

    return $labels[$report_type] ?? 'Admin Report';
}

function admin_reports_generate_lines(mysqli $conn, string $report_type): array
{
    if ($report_type === 'user_roster') {
        return admin_reports_user_roster_lines($conn);
    }

    if ($report_type === 'role_summary') {
        return admin_reports_role_summary_lines($conn);
    }

    if ($report_type === 'classroom_activity') {
        return admin_reports_classroom_activity_lines($conn);
    }

    if ($report_type === 'achievement_summary') {
        return admin_reports_achievement_summary_lines($conn);
    }

    return [];
}

function admin_reports_user_roster_lines(mysqli $conn): array
{
    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        '',
        'Registered Users',
    ];

    $result = $conn->query("
        SELECT id, username, email, role, register_date
        FROM users
        ORDER BY role ASC, username ASC
    ");

    if (!$result || $result->num_rows === 0) {
        $lines[] = 'No users were found.';
        return $lines;
    }

    while ($user = $result->fetch_assoc()) {
        $lines[] = '#' . (int)$user['id']
            . ' | ' . (string)$user['username']
            . ' | ' . (string)$user['email']
            . ' | Role: ' . (string)$user['role']
            . ' | Registered: ' . (string)$user['register_date'];
    }

    return $lines;
}

function admin_reports_role_summary_lines(mysqli $conn): array
{
    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        '',
        'Role Summary',
    ];

    $result = $conn->query("
        SELECT role, COUNT(*) AS total
        FROM users
        GROUP BY role
        ORDER BY role ASC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $lines[] = ucfirst((string)$row['role']) . ': ' . (int)$row['total'];
        }
    }

    $totals = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM users) AS users_total,
            (SELECT COUNT(*) FROM classrooms) AS classrooms_total,
            (SELECT COUNT(*) FROM teacher_levels) AS levels_total,
            (SELECT COUNT(*) FROM teacher_reports) AS teacher_reports_total
    ");
    $total_row = $totals ? $totals->fetch_assoc() : [];

    $lines[] = '';
    $lines[] = 'System Totals';
    $lines[] = 'Users: ' . (int)($total_row['users_total'] ?? 0);
    $lines[] = 'Classrooms: ' . (int)($total_row['classrooms_total'] ?? 0);
    $lines[] = 'Teacher levels: ' . (int)($total_row['levels_total'] ?? 0);
    $lines[] = 'Teacher reports: ' . (int)($total_row['teacher_reports_total'] ?? 0);

    return $lines;
}

function admin_reports_classroom_activity_lines(mysqli $conn): array
{
    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        '',
        'Classroom Activity',
    ];

    $result = $conn->query("
        SELECT c.name,
               u.username AS teacher_name,
               COUNT(DISTINCT cm.student_id) AS students,
               COUNT(DISTINCT l.id) AS levels,
               SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_entries,
               SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) AS active_entries
        FROM classrooms c
        INNER JOIN users u ON u.id = c.teacher_id
        LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
        LEFT JOIN teacher_levels l ON l.classroom_id = c.id
        LEFT JOIN student_level_progress p ON p.level_id = l.id AND p.student_id = cm.student_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ");

    if (!$result || $result->num_rows === 0) {
        $lines[] = 'No classrooms were found.';
        return $lines;
    }

    while ($classroom = $result->fetch_assoc()) {
        $lines[] = (string)$classroom['name']
            . ' | Teacher: ' . (string)$classroom['teacher_name']
            . ' | Students: ' . (int)$classroom['students']
            . ' | Levels: ' . (int)$classroom['levels']
            . ' | Completed: ' . (int)$classroom['completed_entries']
            . ' | Active: ' . (int)$classroom['active_entries'];
    }

    return $lines;
}

function admin_reports_achievement_summary_lines(mysqli $conn): array
{
    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        '',
        'Achievement Summary',
    ];

    $result = $conn->query("
        SELECT a.title,
               COUNT(ua.id) AS unlocks
        FROM achievements a
        LEFT JOIN user_achievements ua ON ua.achievement_id = a.id
        GROUP BY a.id
        ORDER BY unlocks DESC, a.title ASC
    ");

    if (!$result || $result->num_rows === 0) {
        $lines[] = 'No achievements were found.';
        return $lines;
    }

    while ($achievement = $result->fetch_assoc()) {
        $lines[] = (string)$achievement['title'] . ': ' . (int)$achievement['unlocks'] . ' unlocks';
    }

    return $lines;
}
