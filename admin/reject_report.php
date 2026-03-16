<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/database.php";

$reportId = $_GET['id'] ?? null;

if (!$reportId) {
    die("Report ID is missing.");
}

$sql = "UPDATE fare_reports SET status = 'rejected' WHERE report_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $reportId);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: view_reports.php");
    exit();
} else {
    mysqli_stmt_close($stmt);
    die("Failed to reject report.");
}
?>
