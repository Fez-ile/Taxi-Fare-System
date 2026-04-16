<?php
include "../config/database.php";

$invalidLocations = ["Soweto", "Johannesburg CBD", "Alexandra"];

$message = "";
$resultData = null;

$selectedFrom = "";
$selectedTo = "";

$locations = [];
$locationsSql = "SELECT DISTINCT location
                 FROM (
                     SELECT from_location AS location FROM routes WHERE route_type = 'pretoria'
                     UNION
                     SELECT to_location AS location FROM routes WHERE route_type = 'pretoria'
                 ) AS pretoria_locations
                 WHERE location NOT IN (?, ?, ?)
                 ORDER BY location ASC";
$locationsStmt = mysqli_prepare($conn, $locationsSql);
if ($locationsStmt) {
    $invalid1 = $invalidLocations[0];
    $invalid2 = $invalidLocations[1];
    $invalid3 = $invalidLocations[2];
    mysqli_stmt_bind_param($locationsStmt, "sss", $invalid1, $invalid2, $invalid3);
    mysqli_stmt_execute($locationsStmt);
    $locationsResult = mysqli_stmt_get_result($locationsStmt);
    while ($row = mysqli_fetch_assoc($locationsResult)) {
        $locations[] = $row["location"];
    }
    mysqli_stmt_close($locationsStmt);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedFrom = trim($_POST["from"] ?? "");
    $selectedTo = trim($_POST["to"] ?? "");

    if ($selectedFrom === "" || $selectedTo === "") {
        $message = "Please select both starting location and destination.";
    } elseif ($selectedFrom === $selectedTo) {
        $message = "Starting location and destination cannot be the same.";
    } elseif (in_array($selectedFrom, $invalidLocations, true) || in_array($selectedTo, $invalidLocations, true)) {
        $message = "Invalid locations cannot be used.";
    } else {
        $sql = "SELECT 
                    routes.route_id,
                    routes.from_location,
                    routes.to_location,
                    routes.fare_amount,
                    routes.last_updated
                FROM routes
                                WHERE (
                                                (routes.from_location = ? AND routes.to_location = ?)
                                                OR (routes.from_location = ? AND routes.to_location = ?)
                                            )
                  AND routes.route_type = 'pretoria'
                  AND routes.from_location NOT IN (?, ?, ?)
                  AND routes.to_location NOT IN (?, ?, ?)";
        ;

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            $invalid1 = $invalidLocations[0];
            $invalid2 = $invalidLocations[1];
            $invalid3 = $invalidLocations[2];
            mysqli_stmt_bind_param($stmt, "ssssssssss", $selectedFrom, $selectedTo, $selectedTo, $selectedFrom, $invalid1, $invalid2, $invalid3, $invalid1, $invalid2, $invalid3);
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
    <title>User - Search Pretoria Taxi Fare</title>
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
                <h1>Check Pretoria Taxi Fare</h1>
                <p class="subtitle">Know your taxi fare before traveling</p>

                <form id="fareForm" method="POST" action="" class="fare-form">
                    <div class="form-group">
                        <label for="from">From</label>
                        <select id="from" name="from">
                            <option value="">Select starting location</option>
                            <?php foreach ($locations as $location): ?>
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
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo ($selectedTo === $location) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-full">Check Fare</button>

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
                        <p><strong>Fare:</strong> R<?php echo number_format((float) $resultData["fare_amount"], 2); ?></p>
                        <p><strong>Last Updated:</strong>
                            <?php echo date("d F Y", strtotime($resultData["last_updated"])); ?></p>
                        <a class="link-button btn"
                            href="report_fare.php?route_id=<?php echo $resultData['route_id']; ?>">Report
                            a Fare Update</a>
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

    <script src="../assets/js/script.js"></script>
</body>

</html>
