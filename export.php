<?php
include "db.php";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_report.csv"');

$output = fopen("php://output", "w");

// header row
fputcsv($output, ['ID', 'Username', 'Role']);

$result = $conn->query("SELECT * FROM users");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();