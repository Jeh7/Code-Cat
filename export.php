<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "Access denied";
    exit();
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_report.csv"');

$output = fopen("php://output", "w");

// header row
fputcsv($output, ['ID', 'Username', 'Email', 'Role', 'Registered']);

$result = $conn->query("
    SELECT id, username, email, role, register_date
    FROM users
    ORDER BY role ASC, username ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
