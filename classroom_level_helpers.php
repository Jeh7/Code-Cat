<?php

function classroom_level_number_from_title(string $title): int
{
    if (preg_match('/^Level\s+(\d+)$/i', trim($title), $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

function next_classroom_level_title(mysqli $conn, int $classroom_id, int $exclude_level_id = 0): string
{
    $classroom_id = (int)$classroom_id;
    $exclude_level_id = (int)$exclude_level_id;
    $where_exclude = $exclude_level_id > 0 ? "AND id != $exclude_level_id" : "";
    $result = $conn->query("
        SELECT title
        FROM teacher_levels
        WHERE classroom_id = $classroom_id
        $where_exclude
    ");

    $count = 0;
    $max_number = 0;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $count++;
            $max_number = max($max_number, classroom_level_number_from_title((string)($row['title'] ?? '')));
        }
    }

    $next_number = max($count, $max_number) + 1;
    return 'Level ' . $next_number;
}

function get_classroom_level_sequence(mysqli $conn, int $classroom_id): array
{
    $classroom_id = (int)$classroom_id;
    $levels = [];
    $result = $conn->query("
        SELECT id, title, created_at
        FROM teacher_levels
        WHERE classroom_id = $classroom_id
          AND status = 'published'
        ORDER BY created_at ASC, id ASC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $levels[] = $row;
        }
    }

    return $levels;
}

function get_classroom_level_gate(mysqli $conn, int $student_id, int $classroom_id, int $target_level_id): array
{
    $sequence = get_classroom_level_sequence($conn, $classroom_id);
    if (!$sequence) {
        return [
            'found' => false,
            'unlocked' => false,
            'blocked_by' => null,
        ];
    }

    $level_ids = array_map(static function ($level) {
        return (int)$level['id'];
    }, $sequence);

    $status_by_level = [];
    if ($level_ids) {
        $id_list = implode(',', $level_ids);
        $student_id = (int)$student_id;
        $progress_result = $conn->query("
            SELECT level_id, status
            FROM student_level_progress
            WHERE student_id = $student_id
              AND level_id IN ($id_list)
        ");

        if ($progress_result) {
            while ($progress = $progress_result->fetch_assoc()) {
                $status_by_level[(int)$progress['level_id']] = (string)$progress['status'];
            }
        }
    }

    $unlocked = true;
    $blocked_by = null;

    foreach ($sequence as $level) {
        $current_id = (int)$level['id'];
        if ($current_id === $target_level_id) {
            return [
                'found' => true,
                'unlocked' => $unlocked,
                'blocked_by' => $blocked_by,
            ];
        }

        if (($status_by_level[$current_id] ?? 'not_started') !== 'completed' && $blocked_by === null) {
            $unlocked = false;
            $blocked_by = $level;
        }
    }

    return [
        'found' => false,
        'unlocked' => false,
        'blocked_by' => $blocked_by,
    ];
}
