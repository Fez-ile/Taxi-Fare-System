<?php
include "config/database.php";

$message = "";
$resultData = null;

$selectedFrom = "";
$selectedTo = "";

$fromLocations = [];
$fromQuery = mysqli_query($conn, "SELECT DISTINCT from_location FROM routes ORDER BY from_location ASC");
while ($row = mysqli_fetch_assoc($fromQuery)) {
    $fromLocations[] = $row["from_location"];
}

$toLocations = [];
$toQuery = mysqli_query($conn, "SELECT DISTINCT to_location FROM routes ORDER BY to_location ASC");
while ($row = mysqli_fetch_assoc($toQuery)) {
    $toLocations[] = $row["to_location"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedFrom = trim($_POST["from"] ?? "");
    $selectedTo = trim($_POST["to"] ?? "");

    if ($selectedFrom === "" || $selectedTo === "") {
        $message = "Please select both starting location and destination.";
    } elseif ($selectedFrom === $selectedTo) {
        $message = "Starting location and destination cannot be the same.";
    } else {
        $sql = "SELECT 
                    routes.route_id,
                    routes.from_location,
                    routes.to_location,
                    routes.fare_amount,
                    routes.last_updated,
                    taxi_ranks.rank_name
                FROM routes
                INNER JOIN taxi_ranks ON routes.rank_id = taxi_ranks.rank_id
                WHERE routes.from_location = ? AND routes.to_location = ?";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $selectedFrom, $selectedTo);
            mysqli_stmt_execute($stmt);
            $queryResult = mysqli_stmt_get_result($stmt);

            if ($queryResult && mysqli_num_rows($queryResult) > 0) {
                $resultData = mysqli_fetch_assoc($queryResult);
            } else {
                $message = "No fare information found for this route yet.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $message = "Something went wrong while searching.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxi Fare Information System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="page-wrapper">
        <div class="container">
            <nav class="top-nav">
                <a href="index.php">Home</a>
                <a href="user/report_fare.php">Report Fare</a>
                <a href="admin/login.php">Admin Login</a>
            </nav>

            <div class="card">
                <h1>Check Taxi Fare</h1>
                <p class="subtitle">Know your taxi fare before going to the taxi rank</p>

                <form id="fareForm" method="POST" action="" class="fare-form">
                    <div class="form-group">
                        <label for="from">From</label>
                        <select id="from" name="from">
                            <option value="">Select starting location</option>
                            <?php foreach ($fromLocations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo ($selectedFrom === $location) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="to">To</label>
                        <select id="to" name="to">
                            <option value="">Select destination</option>
                            <?php foreach ($toLocations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo ($selectedTo === $location) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit">Check Fare</button>

                    <p id="message" class="message <?php echo !empty($message) ? 'show' : ''; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                </form>

                <?php if ($resultData): ?>
                    <div class="result-box">
                        <h2>Fare Result</h2>
                        <p><strong>Route:</strong>
                            <?php echo htmlspecialchars($resultData["from_location"] . " to " . $resultData["to_location"]); ?>
                        </p>
                        <p><strong>Taxi Rank:</strong> <?php echo htmlspecialchars($resultData["rank_name"]); ?></p>
                        <p><strong>Fare:</strong> R<?php echo number_format((float) $resultData["fare_amount"], 2); ?></p>
                        <p><strong>Last Updated:</strong>
                            <?php echo date("d F Y", strtotime($resultData["last_updated"])); ?></p>
                        <a class="link-button"
                            href="user/report_fare.php?route_id=<?php echo $resultData['route_id']; ?>">Report a Fare
                            Update</a>
                    </div>
                <?php else: ?>
                    <div class="result-box">
                        <h2>Fare Result</h2>
                        <p class="empty-state">Search for a route to see the fare details here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>

</html>