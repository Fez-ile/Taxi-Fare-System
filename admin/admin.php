<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/database.php";

$message = "";
$messageType = "";

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
        $checkSql = "SELECT route_id FROM routes WHERE from_location = ? AND to_location = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "ss", $from, $to);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            $message = "This route already exists.";
            $messageType = "error";
        } else {
            $sql = "INSERT INTO routes (from_location, to_location, rank_id, fare_amount, last_updated)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssids", $from, $to, $rankId, $fare, $lastUpdated);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Route added successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to add route.";
                    $messageType = "error";
                }

                mysqli_stmt_close($stmt);
            } else {
                $message = "Something went wrong.";
                $messageType = "error";
            }
        }

        mysqli_stmt_close($checkStmt);
    }

    $rankResult = mysqli_query($conn, "SELECT rank_id, rank_name FROM taxi_ranks ORDER BY rank_name ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Add Route</title>
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
                <h1>Add New Route</h1>
                <p class="subtitle">Use this page to add taxi fare information</p>

                <?php if (!empty($message)): ?>
                    <p class="status-message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="" class="fare-form">
                    <div class="form-group">
                        <label for="from_location">From Location</label>
                        <input type="text" id="from_location" name="from_location" placeholder="Enter starting location" required>
                    </div>

                    <div class="form-group">
                        <label for="to_location">To Location</label>
                        <input type="text" id="to_location" name="to_location" placeholder="Enter destination" required>
                    </div>

                    <div class="form-group">
                        <label for="rank_id">Taxi Rank</label>
                        <select id="rank_id" name="rank_id" required>
                            <option value="">Select taxi rank</option>
                            <?php while ($rank = mysqli_fetch_assoc($rankResult)): ?>
                                <option value="<?php echo $rank['rank_id']; ?>">
                                    <?php echo htmlspecialchars($rank['rank_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fare_amount">Fare Amount</label>
                        <input type="number" step="0.01" id="fare_amount" name="fare_amount" placeholder="Enter fare amount" required>
                    </div>

                    <div class="form-group">
                        <label for="last_updated">Last Updated</label>
                        <input type="date" id="last_updated" name="last_updated" required>
                    </div>

                    <button type="submit">Add Route</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
