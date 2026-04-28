<?php
session_start();
include "db.php";
include "achievement_helpers.php";

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Not logged in',
    ]);
    exit();
}

$user_id = (int)($_SESSION['id'] ?? 0);
$achievement_id = (int)($_GET['id'] ?? 0);

$achievement = $conn->prepare("SELECT id FROM achievements WHERE id = ? LIMIT 1");
if (!$achievement) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Achievements are temporarily unavailable.',
    ]);
    exit();
}

$achievement->bind_param("i", $achievement_id);
$achievement->execute();
$achievement_result = $achievement->get_result();
$exists = $achievement_result && $achievement_result->num_rows > 0;
$achievement->close();

if (!$exists) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Achievement not found.',
    ]);
    exit();
}

$unlocked = achievement_unlock($conn, $user_id, $achievement_id);

echo json_encode([
    'ok' => true,
    'unlocked' => $unlocked,
]);
?>
