<?php
session_start();
include "db.php";

// only admin can access
if ($_SESSION['role'] != 'admin') {
    echo "Access denied";
    exit();
}

// filter (optional)
$role = $_GET['role'] ?? '';

// query
$sql = "SELECT * FROM users";

if ($role != '') {
    $sql .= " WHERE role='$role'";
}

$result = $conn->query($sql);
?>

<h2>User Report</h2>

<form method="GET">
    <select name="role">
        <option value="">All</option>
        <option value="student">Student</option>
        <option value="teacher">Teacher</option>
        <option value="admin">Admin</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table border="1">
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Role</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= $row['username'] ?></td>
    <td><?= $row['role'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<a href="export.php">Export CSV</a>