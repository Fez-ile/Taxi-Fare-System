<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/database.php";

$message = "";
$messageType = "";

$routeId = $_GET['id'] ?? null;

if (!$routeId) {
    die("Route ID is missing.");
}

$sql = "SELECT * FROM routes WHERE route_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $routeId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$route = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$route) {
    die("Route not found.");
}

$rankResult = mysqli_query($conn, "SELECT rank_id, rank_name FROM taxi_ranks ORDER BY rank_name ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $from = trim($_POST["from_location"] ?? "");
    $to = trim($_POST["to_location"] ?? "");
    $rankId = trim($_POST["rank_id"] ?? "");
    $fare = trim($_POST["fare_amount"] ?? "");
    $lastUpdated = trim($_POST["last_updated"] ?? "");

    if ($from === "" || $to === "" || $rankId === "" || $fare === "" || $lastUpdated === "") {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } elseif ($from === $to) {
        $message = "Starting location and destination cannot be the same.";
        $messageType = "error";
    } else {
        $updateSql = "UPDATE routes
                      SET from_location = ?, to_location = ?, rank_id = ?, fare_amount = ?, last_updated = ?
                      WHERE route_id = ?";

        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, "ssidsi", $from, $to, $rankId, $fare, $lastUpdated, $routeId);

        if (mysqli_stmt_execute($updateStmt)) {
            $message = "Route updated successfully.";
            $messageType = "success";

            $route['from_location'] = $from;
            $route['to_location'] = $to;
            $route['rank_id'] = $rankId;
            $route['fare_amount'] = $fare;
            $route['last_updated'] = $lastUpdated;
        } else {
            $message = "Failed to update route.";
            $messageType = "error";
        }

        mysqli_stmt_close($updateStmt);
    }

    $rankResult = mysqli_query($conn, "SELECT rank_id, rank_name FROM taxi_ranks ORDER BY rank_name ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Edit Route</title>
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

            <div class="card">
                <h1>Edit Route</h1>
                <p class="subtitle">Update the selected route details</p>

                <?php if (!empty($message)): ?>
                    <p class="status-message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="" class="fare-form">
                    <div class="form-group">
                        <label for="from_location">From Location</label>
                        <input type="text" id="from_location" name="from_location" value="<?php echo htmlspecialchars($route['from_location']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="to_location">To Location</label>
                        <input type="text" id="to_location" name="to_location" value="<?php echo htmlspecialchars($route['to_location']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="rank_id">Taxi Rank</label>
                        <select id="rank_id" name="rank_id" required>
                            <?php while ($rank = mysqli_fetch_assoc($rankResult)): ?>
                                <option value="<?php echo $rank['rank_id']; ?>" <?php echo ($route['rank_id'] == $rank['rank_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rank['rank_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fare_amount">Fare Amount</label>
                        <input type="number" step="0.01" id="fare_amount" name="fare_amount" value="<?php echo htmlspecialchars($route['fare_amount']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_updated">Last Updated</label>
                        <input type="date" id="last_updated" name="last_updated" value="<?php echo htmlspecialchars($route['last_updated']); ?>" required>
                    </div>

                    <button type="submit">Update Route</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
