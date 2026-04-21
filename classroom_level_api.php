<?php
session_start();
include "db.php";

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
$level_id = (int)($_GET['id'] ?? 0);

if ($student_id <= 0 || $level_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'A valid classroom level is required.',
    ]);
    exit();
}

$level_result = $conn->query("
SELECT l.*,
       c.name AS classroom_name,
       u.username AS teacher_name
FROM teacher_levels l
INNER JOIN classrooms c ON c.id = l.classroom_id
INNER JOIN classroom_members cm ON cm.classroom_id = c.id
INNER JOIN users u ON u.id = l.teacher_id
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

$existing_progress_result = $conn->query("
SELECT status, attempts, last_played_at, completed_at
FROM student_level_progress
WHERE level_id = $level_id
  AND student_id = $student_id
LIMIT 1
");

$existing_progress = $existing_progress_result && $existing_progress_result->num_rows > 0
    ? $existing_progress_result->fetch_assoc()
    : null;

if ($existing_progress && $existing_progress['status'] === 'completed') {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'message' => 'This classroom level has already been completed.',
        'completed' => true,
    ]);
    exit();
}

$conn->query("
    INSERT INTO student_level_progress (level_id, student_id, status, attempts, last_played_at)
    VALUES ($level_id, $student_id, 'in_progress', 1, NOW())
    ON DUPLICATE KEY UPDATE
        status = IF(status = 'completed', 'completed', 'in_progress'),
        attempts = attempts + 1,
        last_played_at = NOW()
");

$progress_result = $conn->query("
SELECT status, attempts, last_played_at, completed_at
FROM student_level_progress
WHERE level_id = $level_id
  AND student_id = $student_id
LIMIT 1
");

$progress = $progress_result && $progress_result->num_rows > 0
    ? $progress_result->fetch_assoc()
    : [
        'status' => 'not_started',
        'attempts' => 0,
        'last_played_at' => null,
        'completed_at' => null,
    ];

$walls = [];
$walls_raw = trim((string)($level['walls'] ?? ''));
$spikes = [];
$spikes_raw = trim((string)($level['spikes'] ?? ''));
$entities = [];

if ($walls_raw !== '') {
    $parts = preg_split('/\s+/', $walls_raw);
    foreach ($parts as $part) {
        $coords = explode(',', $part);
        if (count($coords) !== 2) {
            continue;
        }

        $walls[] = [
            'x' => (int)$coords[0],
            'y' => (int)$coords[1],
        ];
    }
}

if ($spikes_raw !== '') {
    $parts = preg_split('/\s+/', $spikes_raw);
    foreach ($parts as $part) {
        $coords = explode(',', $part);
        if (count($coords) !== 2) {
            continue;
        }

        $spikes[] = [
            'x' => (int)$coords[0],
            'y' => (int)$coords[1],
        ];
    }
}

$entities_raw = trim((string)($level['entities'] ?? ''));
if ($entities_raw !== '') {
    $decoded_entities = json_decode($entities_raw, true);
    if (is_array($decoded_entities)) {
        foreach ($decoded_entities as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            $entities[] = [
                'type' => (string)($entity['type'] ?? ''),
                'x' => (int)($entity['x'] ?? 0),
                'y' => (int)($entity['y'] ?? 0),
            ];
        }
    }
}

echo json_encode([
    'ok' => true,
    'level' => [
        'id' => (int)$level['id'],
        'classroom_id' => (int)$level['classroom_id'],
        'classroom_name' => $level['classroom_name'],
        'teacher_name' => $level['teacher_name'],
        'title' => $level['title'],
        'description' => $level['description'],
        'instructions' => $level['instructions'],
        'difficulty' => $level['difficulty'],
        'grid_width' => (int)$level['grid_width'],
        'grid_height' => (int)$level['grid_height'],
        'start_x' => (int)$level['start_x'],
        'start_y' => (int)$level['start_y'],
        'goal_x' => (int)$level['goal_x'],
        'goal_y' => (int)$level['goal_y'],
        'walls' => $walls,
        'spikes' => $spikes,
        'entities' => $entities,
    ],
    'progress' => [
        'status' => $progress['status'],
        'attempts' => (int)$progress['attempts'],
        'last_played_at' => $progress['last_played_at'],
        'completed_at' => $progress['completed_at'],
    ],
]);
