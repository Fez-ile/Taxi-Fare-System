<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="page-wrapper">
        <div class="container">
            <nav class="top-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="admin.php">Add Route</a>
                <a href="view_routes.php">View Routes</a>
                <a href="view_reports.php">View Reports</a>
                <a href="logout.php">Logout</a>
            </nav>

            <div class="card center-card">
                <h1>Admin Dashboard</h1>
                <p class="subtitle">Welcome, <?php echo htmlspecialchars($_SESSION["admin_username"]); ?></p>

                <div class="portal-links">
                    <a class="portal-btn" href="admin.php">Add New Route</a>
                    <a class="portal-btn" href="view_routes.php">Manage Routes</a>
                    <a class="portal-btn" href="view_reports.php">Review Fare Reports</a>
                    <a class="portal-btn secondary" href="logout.php">Log Out</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>