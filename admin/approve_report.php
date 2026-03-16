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

$sql = "SELECT route_id, reported_fare, status FROM fare_reports WHERE report_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $reportId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$report = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$report) {
    die("Report not found.");
}

if ($report['status'] !== 'pending') {
    header("Location: view_reports.php");
    exit();
}

$updateRouteSql = "UPDATE routes SET fare_amount = ?, last_updated = CURDATE() WHERE route_id = ?";
$updateRouteStmt = mysqli_prepare($conn, $updateRouteSql);
mysqli_stmt_bind_param($updateRouteStmt, "di", $report['reported_fare'], $report['route_id']);
mysqli_stmt_execute($updateRouteStmt);
mysqli_stmt_close($updateRouteStmt);

$updateReportSql = "UPDATE fare_reports SET status = 'approved' WHERE report_id = ?";
$updateReportStmt = mysqli_prepare($conn, $updateReportSql);
mysqli_stmt_bind_param($updateReportStmt, "i", $reportId);
mysqli_stmt_execute($updateReportStmt);
mysqli_stmt_close($updateReportStmt);

header("Location: view_reports.php");
exit();
?>
