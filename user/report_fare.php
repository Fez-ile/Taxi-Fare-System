<?php
include "../config/database.php";

$message = "";
$messageType = "";

$invalidLocations = ["Soweto", "Johannesburg CBD", "Alexandra"];

$selectedRouteId = $_GET["route_id"] ?? "";
$selectedFare = "";
$selectedComment = "";
$selectedName = "";
$manualFrom = "";
$manualTo = "";
$manualRouteType = "pretoria";

$routeSql = "SELECT 
                routes.route_id,
                routes.from_location,
                routes.to_location,
                routes.fare_amount,
                routes.route_type,
                taxi_ranks.rank_name
             FROM routes
             LEFT JOIN taxi_ranks ON routes.rank_id = taxi_ranks.rank_id
             WHERE routes.from_location NOT IN (?, ?, ?)
               AND routes.to_location NOT IN (?, ?, ?)
             ORDER BY routes.route_type ASC, routes.from_location ASC, routes.to_location ASC";

$routeResult = null;
$routeStmt = mysqli_prepare($conn, $routeSql);
if ($routeStmt) {
    $invalid1 = $invalidLocations[0];
    $invalid2 = $invalidLocations[1];
    $invalid3 = $invalidLocations[2];
    mysqli_stmt_bind_param($routeStmt, "ssssss", $invalid1, $invalid2, $invalid3, $invalid1, $invalid2, $invalid3);
    mysqli_stmt_execute($routeStmt);
    $routeResult = mysqli_stmt_get_result($routeStmt);
    mysqli_stmt_close($routeStmt);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedRouteId = trim($_POST["route_id"] ?? "");
    $selectedFare = trim($_POST["reported_fare"] ?? "");
    $selectedComment = trim($_POST["comment"] ?? "");
    $selectedName = trim($_POST["reporter_name"] ?? "");
    $manualFrom = trim($_POST["manual_from"] ?? "");
    $manualTo = trim($_POST["manual_to"] ?? "");
    $manualRouteType = trim($_POST["manual_route_type"] ?? "pretoria");

    if ($selectedFare === "") {
        $message = "Please enter the reported fare.";
        $messageType = "error";
    } elseif (!is_numeric($selectedFare) || (float) $selectedFare <= 0) {
        $message = "Please enter a valid numeric fare amount.";
        $messageType = "error";
    } else {
        $resolvedRouteId = null;

        if ($selectedRouteId !== "") {
            if (!ctype_digit($selectedRouteId)) {
                $message = "Invalid route selected.";
                $messageType = "error";
            } else {
                $routeId = (int) $selectedRouteId;
                $checkSql = "SELECT route_id
                                                         FROM routes
                                                         WHERE route_id = ?
                                                             AND from_location NOT IN (?, ?, ?)
                                                             AND to_location NOT IN (?, ?, ?)
                                                         LIMIT 1";
                $checkStmt = mysqli_prepare($conn, $checkSql);
                if ($checkStmt) {
                    $invalid1 = $invalidLocations[0];
                    $invalid2 = $invalidLocations[1];
                    $invalid3 = $invalidLocations[2];
                    mysqli_stmt_bind_param($checkStmt, "issssss", $routeId, $invalid1, $invalid2, $invalid3, $invalid1, $invalid2, $invalid3);
                    mysqli_stmt_execute($checkStmt);
                    $checkResult = mysqli_stmt_get_result($checkStmt);
                    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                        $resolvedRouteId = $routeId;
                    } else {
                        $message = "Selected route does not exist.";
                        $messageType = "error";
                    }
                    mysqli_stmt_close($checkStmt);
                } else {
                    $message = "Unable to validate selected route.";
                    $messageType = "error";
                }
            }
        } else {
            if ($manualFrom === "" || $manualTo === "") {
                $message = "Select a route or enter both From and To locations.";
                $messageType = "error";
            } elseif ($manualFrom === $manualTo) {
                $message = "From and To locations cannot be the same.";
                $messageType = "error";
            } elseif (in_array($manualFrom, $invalidLocations, true) || in_array($manualTo, $invalidLocations, true)) {
                $message = "Invalid locations cannot be reported.";
                $messageType = "error";
            } else {
                $findSql = "SELECT route_id
                            FROM routes
                                                        WHERE (
                                                                     (from_location = ? AND to_location = ?)
                                                                     OR (from_location = ? AND to_location = ?)
                                                                    )
                              AND route_type = ?
                              AND from_location NOT IN (?, ?, ?)
                              AND to_location NOT IN (?, ?, ?)
                            LIMIT 1";
                $findStmt = mysqli_prepare($conn, $findSql);
                if ($findStmt) {
                    $invalid1 = $invalidLocations[0];
                    $invalid2 = $invalidLocations[1];
                    $invalid3 = $invalidLocations[2];
                    mysqli_stmt_bind_param(
                        $findStmt,
                        "sssssssssss",
                        $manualFrom,
                        $manualTo,
                        $manualTo,
                        $manualFrom,
                        $manualRouteType,
                        $invalid1,
                        $invalid2,
                        $invalid3,
                        $invalid1,
                        $invalid2,
                        $invalid3
                    );
                    mysqli_stmt_execute($findStmt);
                    $findResult = mysqli_stmt_get_result($findStmt);
                    if ($findResult && mysqli_num_rows($findResult) > 0) {
                        $resolvedRoute = mysqli_fetch_assoc($findResult);
                        $resolvedRouteId = (int) $resolvedRoute["route_id"];
                    } else {
                        $message = "No matching route found. Please select an existing route.";
                        $messageType = "error";
                    }
                    mysqli_stmt_close($findStmt);
                } else {
                    $message = "Unable to validate manual route details.";
                    $messageType = "error";
                }
            }
        }

        if ($message === "" && $resolvedRouteId !== null) {
            $sql = "INSERT INTO fare_reports (route_id, reported_fare, comment, reporter_name, status)
                    VALUES (?, ?, ?, ?, 'pending')";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                $fareAmount = (float) $selectedFare;
                mysqli_stmt_bind_param($stmt, "idss", $resolvedRouteId, $fareAmount, $selectedComment, $selectedName);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Thank you. Your fare update report has been submitted for review.";
                    $messageType = "success";
                    $selectedRouteId = "";
                    $selectedFare = "";
                    $selectedComment = "";
                    $selectedName = "";
                    $manualFrom = "";
                    $manualTo = "";
                    $manualRouteType = "pretoria";
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
    }

    $routeResult = null;
    $routeStmt = mysqli_prepare($conn, $routeSql);
    if ($routeStmt) {
        $invalid1 = $invalidLocations[0];
        $invalid2 = $invalidLocations[1];
        $invalid3 = $invalidLocations[2];
        mysqli_stmt_bind_param($routeStmt, "ssssss", $invalid1, $invalid2, $invalid3, $invalid1, $invalid2, $invalid3);
        mysqli_stmt_execute($routeStmt);
        $routeResult = mysqli_stmt_get_result($routeStmt);
        mysqli_stmt_close($routeStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggest Fare Update</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php
    $assetPathPrefix = "../";
    $navLinks = [
        ["href" => "../index.php", "label" => "HOME", "active" => false],
        ["href" => "report_fare.php", "label" => "REPORT FARE", "active" => true],
        ["href" => "../admin/login.php", "label" => "ADMIN", "active" => false],
    ];
    ?>
    <div class="page-shell">
        <?php include "../includes/header.php"; ?>

        <main class="container">
            <section class="hero">
                <h2>Report a Fare Update</h2>
                <p>Help keep fare information accurate by submitting updated prices you have seen.</p>
            </section>

            <section class="card form-card">
                <div class="form-header">
                    <h3>Fare Report Form</h3>
                    <p>Select the route, enter the new fare, and include a short comment if needed.</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="status-message <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="route_id">Select Route</label>
                        <select id="route_id" name="route_id">
                            <option value="">Select route</option>
                            <optgroup label="Pretoria Routes">
                                <?php
                                mysqli_data_seek($routeResult, 0);
                                while ($route = mysqli_fetch_assoc($routeResult)):
                                    if ($route['route_type'] === 'pretoria'):
                                        ?>
                                        <option value="<?php echo $route['route_id']; ?>" <?php echo ($selectedRouteId == $route['route_id']) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($route['from_location'] . " to " . $route['to_location'] . " | Current Fare: R" . number_format((float) $route['fare_amount'], 2)); ?>
                                        </option>
                                    <?php endif; endwhile; ?>
                            </optgroup>
                            <optgroup label="Long Distance Routes">
                                <?php
                                mysqli_data_seek($routeResult, 0);
                                while ($route = mysqli_fetch_assoc($routeResult)):
                                    if ($route['route_type'] === 'long_distance'):
                                        ?>
                                        <option value="<?php echo $route['route_id']; ?>" <?php echo ($selectedRouteId == $route['route_id']) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($route['from_location'] . " to " . $route['to_location'] . " | Current Fare: R" . number_format((float) $route['fare_amount'], 2)); ?>
                                        </option>
                                    <?php endif; endwhile; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-header">
                        <p>Or enter route details manually if you do not want to select from the list.</p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="manual_from">From (Optional)</label>
                            <input type="text" id="manual_from" name="manual_from"
                                value="<?php echo htmlspecialchars($manualFrom); ?>"
                                placeholder="Enter starting location">
                        </div>

                        <div class="form-group">
                            <label for="manual_to">To (Optional)</label>
                            <input type="text" id="manual_to" name="manual_to"
                                value="<?php echo htmlspecialchars($manualTo); ?>" placeholder="Enter destination">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="manual_route_type">Route Type</label>
                        <select id="manual_route_type" name="manual_route_type">
                            <option value="pretoria" <?php echo ($manualRouteType === "pretoria") ? "selected" : ""; ?>>
                                Pretoria</option>
                            <option value="long_distance" <?php echo ($manualRouteType === "long_distance") ? "selected" : ""; ?>>Long Distance</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reported_fare">Reported Fare</label>
                            <input type="number" step="0.01" id="reported_fare" name="reported_fare"
                                value="<?php echo htmlspecialchars($selectedFare); ?>" placeholder="Enter new fare"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="reporter_name">Your Name (Optional)</label>
                            <input type="text" id="reporter_name" name="reporter_name"
                                value="<?php echo htmlspecialchars($selectedName); ?>" placeholder="Enter your name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="comment">Comment</label>
                        <textarea id="comment" name="comment"
                            placeholder="Example: Taxi drivers increased the fare this week"><?php echo htmlspecialchars($selectedComment); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-full">Submit Suggestion</button>
                </form>
            </section>
        </main>
    </div>
</body>

</html>
