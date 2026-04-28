<?php
session_start();
include "db.php";
include "classroom_level_helpers.php";
include "achievement_helpers.php";

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student', 'na'], true)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Access denied.',
    ]);
    exit();
}

$student_id = (int)($_SESSION['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $level_id = (int)($_GET['level_id'] ?? 0);

    if ($student_id <= 0 || $level_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'A valid level is required.',
        ]);
        exit();
    }

    $progress_result = $conn->query("
    SELECT p.status, p.attempts, p.last_played_at, p.completed_at
    FROM teacher_levels l
    INNER JOIN classrooms c ON c.id = l.classroom_id
    INNER JOIN classroom_members cm ON cm.classroom_id = c.id
    LEFT JOIN student_level_progress p
        ON p.level_id = l.id AND p.student_id = cm.student_id
    WHERE l.id = $level_id
      AND l.status = 'published'
      AND cm.student_id = $student_id
    LIMIT 1
    ");

    if (!$progress_result || $progress_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Classroom level not found.',
        ]);
        exit();
    }

    $progress = $progress_result->fetch_assoc();
    echo json_encode([
        'ok' => true,
        'progress' => [
            'status' => $progress['status'] ?? 'not_started',
            'attempts' => (int)($progress['attempts'] ?? 0),
            'last_played_at' => $progress['last_played_at'] ?? null,
            'completed_at' => $progress['completed_at'] ?? null,
        ],
    ]);
    exit();
}

$raw_body = file_get_contents('php://input');
$payload = json_decode($raw_body, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$level_id = (int)($payload['level_id'] ?? 0);
$status = trim((string)($payload['status'] ?? ''));

if ($student_id <= 0 || $level_id <= 0 || $status !== 'completed') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'A valid completion payload is required.',
    ]);
    exit();
}

$level_result = $conn->query("
SELECT l.id, l.classroom_id
FROM teacher_levels l
INNER JOIN classrooms c ON c.id = l.classroom_id
INNER JOIN classroom_members cm ON cm.classroom_id = c.id
WHERE l.id = $level_id
  AND l.status = 'published'
  AND cm.student_id = $student_id
LIMIT 1
");

if (!$level_result || $level_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Classroom level not found.',
    ]);
    exit();
}

$level = $level_result->fetch_assoc();
$gate = get_classroom_level_gate($conn, $student_id, (int)$level['classroom_id'], $level_id);
if (!$gate['found'] || !$gate['unlocked']) {
    http_response_code(423);
    echo json_encode([
        'ok' => false,
        'message' => 'Complete ' . (($gate['blocked_by']['title'] ?? 'the previous level')) . ' first.',
        'locked' => true,
    ]);
    exit();
}

$conn->query("
    INSERT INTO student_level_progress (level_id, student_id, status, attempts, last_played_at, completed_at)
    VALUES ($level_id, $student_id, 'completed', 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        status = 'completed',
        last_played_at = NOW(),
        completed_at = NOW()
");

achievement_unlock_completed_level_milestones($conn, $student_id);

echo json_encode([
    'ok' => true,
    'message' => 'Progress saved.',
]);
