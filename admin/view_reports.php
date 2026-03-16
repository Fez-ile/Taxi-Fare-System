<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/database.php";

$sql = "SELECT 
            fare_reports.report_id,
            fare_reports.reported_fare,
            fare_reports.comment,
            fare_reports.reporter_name,
            fare_reports.status,
            fare_reports.reported_at,
            routes.route_id,
            routes.from_location,
            routes.to_location,
            routes.fare_amount AS current_fare
        FROM fare_reports
        INNER JOIN routes ON fare_reports.route_id = routes.route_id
        ORDER BY fare_reports.reported_at DESC";

$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Reports</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="page-wrapper">
        <div class="wide-container">
            <nav class="top-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="admin.php">Add Route</a>
                <a href="view_routes.php">View Routes</a>
                <a href="view_reports.php">View Reports</a>
                <a href="logout.php">Logout</a>
            </nav>

            <div class="card">
                <h1>Fare Reports</h1>
                <p class="subtitle">Review commuter fare update suggestions</p>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Route</th>
                                <th>Current Fare</th>
                                <th>Reported Fare</th>
                                <th>Comment</th>
                                <th>Reporter</th>
                                <th>Status</th>
                                <th>Reported At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $row['report_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['from_location'] . " to " . $row['to_location']); ?>
                                        </td>
                                        <td>R<?php echo number_format((float) $row['current_fare'], 2); ?></td>
                                        <td>R<?php echo number_format((float) $row['reported_fare'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['comment'] ?: "-"); ?></td>
                                        <td><?php echo htmlspecialchars($row['reporter_name'] ?: "Anonymous"); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                                        <td><?php echo date("d F Y H:i", strtotime($row['reported_at'])); ?></td>
                                        <td class="actions">
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <a class="btn-small edit"
                                                    href="approve_report.php?id=<?php echo $row['report_id']; ?>">Approve</a>
                                                <a class="btn-small delete"
                                                    href="reject_report.php?id=<?php echo $row['report_id']; ?>">Reject</a>
                                            <?php else: ?>
                                                <span>No action</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">No fare reports found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>