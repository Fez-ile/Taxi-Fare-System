<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/database.php";

$routeId = $_GET['id'] ?? null;

if (!$routeId) {
    die("Route ID is missing.");
}

$sql = "DELETE FROM routes WHERE route_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $routeId);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: view_routes.php");
    exit();
} else {
    mysqli_stmt_close($stmt);
    die("Failed to delete route.");
}
?>
