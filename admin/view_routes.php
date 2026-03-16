<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/database.php";

$sql = "SELECT 
            routes.route_id,
            routes.from_location,
            routes.to_location,
            routes.fare_amount,
            routes.last_updated,
            taxi_ranks.rank_name
        FROM routes
        INNER JOIN taxi_ranks ON routes.rank_id = taxi_ranks.rank_id
        ORDER BY routes.from_location ASC, routes.to_location ASC";

$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Routes</title>
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
                <h1>Available Routes</h1>
                <p class="subtitle">All saved taxi routes and fares</p>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Taxi Rank</th>
                                <th>Fare</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $row['route_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['from_location']); ?></td>
                                        <td><?php echo htmlspecialchars($row['to_location']); ?></td>
                                        <td><?php echo htmlspecialchars($row['rank_name']); ?></td>
                                        <td>R<?php echo number_format((float)$row['fare_amount'], 2); ?></td>
                                        <td><?php echo date("d F Y", strtotime($row['last_updated'])); ?></td>
                                        <td class="actions">
                                            <a class="btn-small edit" href="edit_route.php?id=<?php echo $row['route_id']; ?>">Edit</a>
                                            <a class="btn-small delete delete-link" href="delete_route.php?id=<?php echo $row['route_id']; ?>">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">No routes found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
