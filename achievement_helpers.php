<?php

function achievement_defaults(): array
{
    $defaults = [];
    foreach (achievement_definitions() as $title => $achievement) {
        $defaults[$title] = $achievement['description'];
    }

    return $defaults;
}

function achievement_definitions(): array
{
    return [
        'First Login' => [
            'description' => 'Log in to Code Cat for the first time.',
            'audience' => 'shared',
        ],
        'Ready to Learn' => [
            'description' => 'Create an account and choose a role.',
            'audience' => 'shared',
        ],
        'Achievement Hunter' => [
            'description' => 'Unlock your first achievement.',
            'audience' => 'shared',
        ],
        'Teacher Mode' => [
            'description' => 'Register with the teacher role.',
            'audience' => 'teacher',
        ],
        'Classroom Creator' => [
            'description' => 'Create your first classroom.',
            'audience' => 'teacher',
        ],
        'Student Mentor' => [
            'description' => 'Add your first student to a classroom.',
            'audience' => 'teacher',
        ],
        'Level Builder' => [
            'description' => 'Create your first classroom level.',
            'audience' => 'teacher',
        ],
        'Published Author' => [
            'description' => 'Publish a classroom level for students.',
            'audience' => 'teacher',
        ],
        'Report Maker' => [
            'description' => 'Generate your first classroom progress report.',
            'audience' => 'teacher',
        ],
        'Puzzle Starter' => [
            'description' => 'Launch the game from the dashboard.',
            'audience' => 'student',
        ],
        'Classroom Enrollee' => [
            'description' => 'Get added to your first classroom.',
            'audience' => 'student',
        ],
        'Level Explorer' => [
            'description' => 'Start a classroom level.',
            'audience' => 'student',
        ],
        'Classroom Rookie' => [
            'description' => 'Complete your first classroom level.',
            'audience' => 'student',
        ],
        'Classroom Climber' => [
            'description' => 'Complete five classroom levels.',
            'audience' => 'student',
        ],
        'Classroom Champion' => [
            'description' => 'Complete ten classroom levels.',
            'audience' => 'student',
        ],
    ];
}

function achievement_titles_for_role(string $role): array
{
    $role = $role === 'teacher' ? 'teacher' : 'student';
    $titles = [];

    foreach (achievement_definitions() as $title => $achievement) {
        if ($achievement['audience'] === 'shared' || $achievement['audience'] === $role) {
            $titles[] = $title;
        }
    }

    return $titles;
}

function achievement_ensure_defaults(mysqli $conn): void
{
    $stmt = $conn->prepare("
        INSERT INTO achievements (title, description)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");

    if (!$stmt) {
        return;
    }

    foreach (achievement_defaults() as $title => $description) {
        $stmt->bind_param("ss", $title, $description);
        $stmt->execute();
    }

    $stmt->close();
}

function achievement_find_id(mysqli $conn, string $title): int
{
    $stmt = $conn->prepare("SELECT id FROM achievements WHERE title = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("s", $title);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['id'] ?? 0);
}

function achievement_unlock(mysqli $conn, int $user_id, int $achievement_id, bool $unlock_hunter = true): bool
{
    if ($user_id <= 0 || $achievement_id <= 0) {
        return false;
    }

    $role_stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if ($role_stmt) {
        $role_stmt->bind_param("i", $user_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role_row = $role_result ? $role_result->fetch_assoc() : null;
        $role_stmt->close();

        if (($role_row['role'] ?? '') === 'admin') {
            return false;
        }
    }

    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_achievements (user_id, achievement_id)
        VALUES (?, ?)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $user_id, $achievement_id);
    $stmt->execute();
    $unlocked = $stmt->affected_rows > 0;
    $stmt->close();

    if ($unlocked && $unlock_hunter) {
        $hunter_id = achievement_find_id($conn, 'Achievement Hunter');
        if ($hunter_id > 0 && $hunter_id !== $achievement_id) {
            achievement_unlock($conn, $user_id, $hunter_id, false);
        }
    }

    return $unlocked;
}

function achievement_unlock_by_title(mysqli $conn, int $user_id, string $title): bool
{
    achievement_ensure_defaults($conn);
    return achievement_unlock($conn, $user_id, achievement_find_id($conn, $title), $title !== 'Achievement Hunter');
}

function achievement_unlock_completed_level_milestones(mysqli $conn, int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS completed_count
        FROM student_level_progress
        WHERE student_id = ?
          AND status = 'completed'
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $completed_count = (int)($row['completed_count'] ?? 0);
    if ($completed_count >= 1) {
        achievement_unlock_by_title($conn, $user_id, 'Classroom Rookie');
    }
    if ($completed_count >= 5) {
        achievement_unlock_by_title($conn, $user_id, 'Classroom Climber');
    }
    if ($completed_count >= 10) {
        achievement_unlock_by_title($conn, $user_id, 'Classroom Champion');
    }
}
