<?php
include "../config/database.php";

$message = "";
$messageType = "";

$selectedRouteId = $_GET["route_id"] ?? "";
$selectedFare = "";
$selectedComment = "";
$selectedName = "";

$routeSql = "SELECT 
                routes.route_id,
                routes.from_location,
                routes.to_location,
                routes.fare_amount,
                taxi_ranks.rank_name
             FROM routes
             INNER JOIN taxi_ranks ON routes.rank_id = taxi_ranks.rank_id
             ORDER BY routes.from_location ASC, routes.to_location ASC";

$routeResult = mysqli_query($conn, $routeSql);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedRouteId = trim($_POST["route_id"] ?? "");
    $selectedFare = trim($_POST["reported_fare"] ?? "");
    $selectedComment = trim($_POST["comment"] ?? "");
    $selectedName = trim($_POST["reporter_name"] ?? "");

    if ($selectedRouteId === "" || $selectedFare === "") {
        $message = "Please select a route and enter the reported fare.";
        $messageType = "error";
    } else {
        $sql = "INSERT INTO fare_reports (route_id, reported_fare, comment, reporter_name, status)
                VALUES (?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "idss", $selectedRouteId, $selectedFare, $selectedComment, $selectedName);

            if (mysqli_stmt_execute($stmt)) {
                $message = "Thank you. Your fare update report has been submitted for review.";
                $messageType = "success";
                $selectedRouteId = "";
                $selectedFare = "";
                $selectedComment = "";
                $selectedName = "";
            } else {
                $message = "Failed to submit fare report.";
                $messageType = "error";
            }

            mysqli_stmt_close($stmt);
        } else {
            $message = "Something went wrong.";
            $messageType = "error";
        }
    }

    $routeResult = mysqli_query($conn, $routeSql);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User - Report Fare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="page-wrapper">
        <div class="container">
            <nav class="top-nav">
                <a href="../index.php">Home</a>
                <a href="index.php">Search Fare</a>
                <a href="report_fare.php">Report Fare</a>
            </nav>

            <div class="card">
                <h1>Report Fare Update</h1>
                <p class="subtitle">Suggest a new fare and add a comment for admin review</p>

                <?php if (!empty($message)): ?>
                    <p class="status-message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="" class="fare-form">
                    <div class="form-group">
                        <label for="route_id">Select Route</label>
                        <select id="route_id" name="route_id" required>
                            <option value="">Select route</option>
                            <?php while ($route = mysqli_fetch_assoc($routeResult)): ?>
                                <option value="<?php echo $route['route_id']; ?>" <?php echo ($selectedRouteId == $route['route_id']) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($route['from_location'] . " to " . $route['to_location'] . " | Current Fare: R" . number_format((float) $route['fare_amount'], 2)); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reported_fare">Reported Fare</label>
                        <input type="number" step="0.01" id="reported_fare" name="reported_fare"
                            value="<?php echo htmlspecialchars($selectedFare); ?>" placeholder="Enter new fare"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="comment">Comment</label>
                        <textarea id="comment" name="comment"
                            placeholder="Example: Taxi drivers increased the fare this week"><?php echo htmlspecialchars($selectedComment); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="reporter_name">Your Name (Optional)</label>
                        <input type="text" id="reporter_name" name="reporter_name"
                            value="<?php echo htmlspecialchars($selectedName); ?>" placeholder="Enter your name">
                    </div>

                    <button type="submit">Submit Fare Report</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>