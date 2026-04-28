<?php
session_start();
include "db.php";
include "admin_report_helpers.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "Access denied";
    exit();
}

admin_reports_ensure_table($conn);

$report_id = (int)($_GET['id'] ?? 0);
if ($report_id <= 0) {
    echo "Report not found";
    exit();
}

$stmt = $conn->prepare("
    SELECT title, file_path, original_filename
    FROM admin_reports
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    echo "Report not found";
    exit();
}

$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$report) {
    echo "Report not found";
    exit();
}

$file_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$report['file_path']);
if (!is_file($file_path)) {
    echo "File not found";
    exit();
}

$download_name = trim((string)($report['original_filename'] ?? ''));
if ($download_name === '') {
    $download_name = teacher_reports_slugify((string)$report['title']) . '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"');
readfile($file_path);
exit();
