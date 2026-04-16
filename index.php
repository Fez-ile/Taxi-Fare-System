<?php
include "config/database.php";

$invalidLocations = ["Soweto", "Johannesburg CBD", "Alexandra"];

function getExclusionPlaceholders($values)
{
    return implode(",", array_fill(0, count($values), "?"));
}

function fetchPretoriaLocations($conn, $invalidLocations)
{
    $locations = [];
    $placeholders = getExclusionPlaceholders($invalidLocations);

        $sql = "SELECT DISTINCT location
                        FROM (
                                SELECT from_location AS location FROM routes WHERE route_type = 'pretoria'
                                UNION
                                SELECT to_location AS location FROM routes WHERE route_type = 'pretoria'
                        ) AS pretoria_locations
                        WHERE location NOT IN ($placeholders)
                        ORDER BY location ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = str_repeat("s", count($invalidLocations));
        mysqli_stmt_bind_param($stmt, $types, ...$invalidLocations);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $locations[] = $row["location"];
        }
        mysqli_stmt_close($stmt);
    }

    return $locations;
}

function fetchLongDistanceCities($conn, $invalidLocations)
{
    $cities = [];
    $placeholders = getExclusionPlaceholders($invalidLocations);

    $sql = "SELECT DISTINCT tr.location AS city
            FROM routes r
            INNER JOIN taxi_ranks tr ON tr.rank_id = r.rank_id
            WHERE r.route_type = 'long_distance'
              AND tr.location NOT IN ($placeholders)
            ORDER BY tr.location ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = str_repeat("s", count($invalidLocations));
        mysqli_stmt_bind_param($stmt, $types, ...$invalidLocations);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $cities[] = $row["city"];
        }
        mysqli_stmt_close($stmt);
    }

    return $cities;
}

function fetchRanksByCity($conn, $city, $invalidLocations)
{
    $ranks = [];
    $placeholders = getExclusionPlaceholders($invalidLocations);

    $sql = "SELECT DISTINCT tr.rank_id, tr.rank_name
            FROM routes r
            INNER JOIN taxi_ranks tr ON tr.rank_id = r.rank_id
            WHERE r.route_type = 'long_distance'
              AND tr.location = ?
              AND tr.location NOT IN ($placeholders)
            ORDER BY tr.rank_name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = "s" . str_repeat("s", count($invalidLocations));
        mysqli_stmt_bind_param($stmt, $types, $city, ...$invalidLocations);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $ranks[] = [
                "rank_id" => (int) $row["rank_id"],
                "rank_name" => $row["rank_name"]
            ];
        }
        mysqli_stmt_close($stmt);
    }

    return $ranks;
}

function fetchDestinationsByCityRank($conn, $city, $rankId, $invalidLocations)
{
    $destinations = [];
    $placeholders = getExclusionPlaceholders($invalidLocations);

    $sql = "SELECT DISTINCT r.to_location
            FROM routes r
            INNER JOIN taxi_ranks tr ON tr.rank_id = r.rank_id
            WHERE r.route_type = 'long_distance'
              AND tr.location = ?
              AND r.rank_id = ?
              AND r.to_location NOT IN ($placeholders)
            ORDER BY r.to_location ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = "si" . str_repeat("s", count($invalidLocations));
        mysqli_stmt_bind_param($stmt, $types, $city, $rankId, ...$invalidLocations);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $destinations[] = $row["to_location"];
        }
        mysqli_stmt_close($stmt);
    }

    return $destinations;
}

if (isset($_GET["action"])) {
    header("Content-Type: application/json");

    if ($_GET["action"] === "get_ranks") {
        $city = trim($_GET["city"] ?? "");
        if ($city === "") {
            echo json_encode(["success" => false, "ranks" => []]);
            exit;
        }

        echo json_encode([
            "success" => true,
            "ranks" => fetchRanksByCity($conn, $city, $invalidLocations)
        ]);
        exit;
    }

    if ($_GET["action"] === "get_destinations") {
        $city = trim($_GET["city"] ?? "");
        $rankId = (int) ($_GET["rank_id"] ?? 0);

        if ($city === "" || $rankId <= 0) {
            echo json_encode(["success" => false, "destinations" => []]);
            exit;
        }

        echo json_encode([
            "success" => true,
            "destinations" => fetchDestinationsByCityRank($conn, $city, $rankId, $invalidLocations)
        ]);
        exit;
    }

    echo json_encode(["success" => false]);
    exit;
}

$pretoriaMessage = "";
$pretoriaMessageType = "";
$pretoriaResult = null;
$pretoriaFromValue = "";
$pretoriaToValue = "";

$longDistanceMessage = "";
$longDistanceMessageType = "";
$longDistanceResult = null;
$longCityValue = "";
$longRankIdValue = "";
$longDestinationValue = "";
$longDistanceDestinations = [];

$pretoriaLocations = fetchPretoriaLocations($conn, $invalidLocations);
$longDistanceCities = fetchLongDistanceCities($conn, $invalidLocations);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "";

    if ($formAction === "search_pretoria") {
        $pretoriaFromValue = trim($_POST["pretoria_from"] ?? "");
        $pretoriaToValue = trim($_POST["pretoria_to"] ?? "");

        if ($pretoriaFromValue === "" || $pretoriaToValue === "") {
            $pretoriaMessage = "Please select both starting location and destination.";
            $pretoriaMessageType = "error";
        } elseif ($pretoriaFromValue === $pretoriaToValue) {
            $pretoriaMessage = "Starting location and destination cannot be the same.";
            $pretoriaMessageType = "error";
        } elseif (in_array($pretoriaFromValue, $invalidLocations, true) || in_array($pretoriaToValue, $invalidLocations, true)) {
            $pretoriaMessage = "Invalid location selected.";
            $pretoriaMessageType = "error";
        } else {
            $sql = "SELECT route_id, from_location, to_location, fare_amount, last_updated
                    FROM routes
                    WHERE route_type = 'pretoria'
                                            AND (
                                                     (from_location = ? AND to_location = ?)
                                                     OR (from_location = ? AND to_location = ?)
                                            )
                      AND from_location NOT IN (?, ?, ?)
                      AND to_location NOT IN (?, ?, ?)
                    LIMIT 1";

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $invalid1 = $invalidLocations[0];
                $invalid2 = $invalidLocations[1];
                $invalid3 = $invalidLocations[2];
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssss",
                    $pretoriaFromValue,
                    $pretoriaToValue,
                    $pretoriaToValue,
                    $pretoriaFromValue,
                    $invalid1,
                    $invalid2,
                    $invalid3,
                    $invalid1,
                    $invalid2,
                    $invalid3
                );
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($result && mysqli_num_rows($result) > 0) {
                    $pretoriaResult = mysqli_fetch_assoc($result);
                } else {
                    $pretoriaMessage = "No fare information found for this Pretoria route.";
                    $pretoriaMessageType = "error";
                }

                mysqli_stmt_close($stmt);
            } else {
                $pretoriaMessage = "Something went wrong while searching.";
                $pretoriaMessageType = "error";
            }
        }
    }

    if ($formAction === "search_long_distance") {
        $longCityValue = trim($_POST["departure_city"] ?? "");
        $longRankIdValue = trim($_POST["departure_rank"] ?? "");
        $longDestinationValue = trim($_POST["destination_town"] ?? "");

        if ($longCityValue !== "" && ctype_digit($longRankIdValue)) {
            $longDistanceDestinations = fetchDestinationsByCityRank($conn, $longCityValue, (int) $longRankIdValue, $invalidLocations);
        }

        if ($longCityValue === "" || $longRankIdValue === "" || $longDestinationValue === "") {
            $longDistanceMessage = "Please select departure city, rank, and destination.";
            $longDistanceMessageType = "error";
        } elseif (!ctype_digit($longRankIdValue)) {
            $longDistanceMessage = "Invalid rank selected.";
            $longDistanceMessageType = "error";
        } elseif (in_array($longDestinationValue, $invalidLocations, true)) {
            $longDistanceMessage = "Invalid destination selected.";
            $longDistanceMessageType = "error";
        } else {
            $rankIdInt = (int) $longRankIdValue;

            $sql = "SELECT r.route_id, tr.location AS from_city, tr.rank_name AS from_rank, r.to_location, r.fare_amount, r.last_updated
                    FROM routes r
                    INNER JOIN taxi_ranks tr ON tr.rank_id = r.rank_id
                    WHERE r.route_type = 'long_distance'
                      AND tr.location = ?
                      AND r.rank_id = ?
                      AND r.to_location = ?
                      AND r.to_location NOT IN (?, ?, ?)
                    LIMIT 1";

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $invalid1 = $invalidLocations[0];
                $invalid2 = $invalidLocations[1];
                $invalid3 = $invalidLocations[2];
                mysqli_stmt_bind_param($stmt, "sissss", $longCityValue, $rankIdInt, $longDestinationValue, $invalid1, $invalid2, $invalid3);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($result && mysqli_num_rows($result) > 0) {
                    $longDistanceResult = mysqli_fetch_assoc($result);
                } else {
                    $longDistanceMessage = "No fare found for this city/rank/destination combination.";
                    $longDistanceMessageType = "error";
                }

                mysqli_stmt_close($stmt);
            } else {
                $longDistanceMessage = "Something went wrong while searching.";
                $longDistanceMessageType = "error";
            }
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
    <link rel="icon" type="image/jpeg" href="assets/images/logo.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="page-wrapper">
        <header class="topbar">
            <div class="topbar-inner">
                <div class="brand">
                    <img src="assets/images/logo.jpg" alt="Taxi Fare System logo" class="logo-img">
                    <div class="brand-text">
                        <div class="brand-title">Taxi Fare System</div>
                        <div class="brand-subtitle">Know before you go</div>
                    </div>
                </div>
                <nav class="top-nav">
                    <a href="index.php" class="nav-link active">HOME</a>
                    <a href="admin/login.php" class="nav-link">ADMIN</a>
                </nav>
            </div>
        </header>

        <main class="main-content">
            <section class="hero">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1 class="hero-title">Taxi Fare Information System</h1>
                    <p class="hero-subtitle">Check taxi fares before traveling. Know the cost, plan your trip better.</p>
                    <div class="hero-badge">
                        <svg class="badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>Only prices for Pretoria are available for now.</span>
                    </div>
                </div>
            </section>

            <div class="tabs-container">
                <a href="index.php" class="tab-button tab-active">PRETORIA TAXIS</a>
                <a href="user/report_fare.php" class="tab-button">REPORT FARE</a>
            </div>

            <div class="tabs-content">
                <section>
                    <div class="card fare-card">
                        <div class="card-header">
                            <h2 class="card-title">Pretoria Fare Lookup</h2>
                            <p class="card-subtitle">Pretoria routes do not use taxi ranks. Select From and To to get the fare.</p>
                        </div>
                        <div class="card-body">
                            <?php if ($pretoriaMessage !== ""): ?>
                                <div class="message-box message-<?php echo htmlspecialchars($pretoriaMessageType); ?>" style="display:block;">
                                    <?php echo htmlspecialchars($pretoriaMessage); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="fare-form">
                                <input type="hidden" name="form_action" value="search_pretoria">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="pretoria-from" class="form-label">From Location</label>
                                        <select id="pretoria-from" name="pretoria_from" class="form-input" required>
                                            <option value="">Select starting location</option>
                                            <?php foreach ($pretoriaLocations as $location): ?>
                                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $pretoriaFromValue === $location ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($location); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="pretoria-to" class="form-label">To Location</label>
                                        <select id="pretoria-to" name="pretoria_to" class="form-input" required>
                                            <option value="">Select destination</option>
                                            <?php foreach ($pretoriaLocations as $location): ?>
                                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $pretoriaToValue === $location ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($location); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-large">GET FARE</button>
                            </form>
                        </div>

                        <?php if ($pretoriaResult): ?>
                            <div class="result-card">
                                <div class="result-header"><h3>Fare Information</h3></div>
                                <div class="result-details">
                                    <div class="result-item"><span class="result-label">From:</span><span class="result-value"><?php echo htmlspecialchars($pretoriaResult["from_location"]); ?></span></div>
                                    <div class="result-item"><span class="result-label">To:</span><span class="result-value"><?php echo htmlspecialchars($pretoriaResult["to_location"]); ?></span></div>
                                    <div class="result-item"><span class="result-label">Fare:</span><span class="result-value fare-amount">R<?php echo number_format((float) $pretoriaResult["fare_amount"], 2); ?></span></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card fare-card">
                        <div class="card-header">
                            <h2 class="card-title">Long Distance Fare Lookup</h2>
                            <p class="card-subtitle">Select city, then rank, then destination town.</p>
                        </div>
                        <div class="card-body">
                            <?php if ($longDistanceMessage !== ""): ?>
                                <div class="message-box message-<?php echo htmlspecialchars($longDistanceMessageType); ?>" style="display:block;">
                                    <?php echo htmlspecialchars($longDistanceMessage); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="fare-form" id="long-distance-form">
                                <input type="hidden" name="form_action" value="search_long_distance">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ld-city" class="form-label">Departure City</label>
                                        <select id="ld-city" name="departure_city" class="form-input" required>
                                            <option value="">Select city</option>
                                            <?php foreach ($longDistanceCities as $city): ?>
                                                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $longCityValue === $city ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($city); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="ld-rank" class="form-label">Departure Rank</label>
                                        <select id="ld-rank" name="departure_rank" class="form-input" required>
                                            <option value="">Select rank</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ld-destination" class="form-label">Destination Town</label>
                                        <select id="ld-destination" name="destination_town" class="form-input" required>
                                            <option value="">Select destination town</option>
                                            <?php foreach ($longDistanceDestinations as $destination): ?>
                                                <option value="<?php echo htmlspecialchars($destination); ?>" <?php echo $longDestinationValue === $destination ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($destination); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-large">GET FARE</button>
                            </form>
                        </div>

                        <?php if ($longDistanceResult): ?>
                            <div class="result-card">
                                <div class="result-header"><h3>Fare Information</h3></div>
                                <div class="result-details">
                                    <div class="result-item"><span class="result-label">City:</span><span class="result-value"><?php echo htmlspecialchars($longDistanceResult["from_city"]); ?></span></div>
                                    <div class="result-item"><span class="result-label">Rank:</span><span class="result-value"><?php echo htmlspecialchars($longDistanceResult["from_rank"]); ?></span></div>
                                    <div class="result-item"><span class="result-label">Destination:</span><span class="result-value"><?php echo htmlspecialchars($longDistanceResult["to_location"]); ?></span></div>
                                    <div class="result-item"><span class="result-label">Fare:</span><span class="result-value fare-amount">R<?php echo number_format((float) $longDistanceResult["fare_amount"], 2); ?></span></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="features-section">
                <div class="features-header">
                    <h2 class="features-title">Why Choose Our Service?</h2>
                </div>
                <div class="features-grid">
                    <div class="card feature-card"><h3 class="feature-title">INSTANT FARE CHECK</h3><p class="feature-text">Get accurate taxi fares instantly. Plan your journey with confidence.</p></div>
                    <div class="card feature-card"><h3 class="feature-title">CURRENT PRICING</h3><p class="feature-text">All fares are regularly updated to reflect the latest rates in Pretoria.</p></div>
                    <div class="card feature-card"><h3 class="feature-title">COMMUNITY SUPPORT</h3><p class="feature-text">Report fare changes to help keep fare information accurate for everyone.</p></div>
                    <div class="card feature-card"><h3 class="feature-title">VERIFIED INFORMATION</h3><p class="feature-text">Every fare update is reviewed before being published.</p></div>
                    <div class="card feature-card"><h3 class="feature-title">EASY ACCESS</h3><p class="feature-text">Use from your phone or computer whenever you need it.</p></div>
                    <div class="card feature-card"><h3 class="feature-title">LOCAL FOCUS</h3><p class="feature-text">Designed around Pretoria routes and commuter needs.</p></div>
                </div>
            </section>

            <footer class="footer">
                <div class="footer-content">
                    <p class="footer-primary">© 2026 Taxi Fare Information System. Helping commuters plan their trips better.</p>
                    <p class="footer-secondary">Fare information is crowdsourced and may not always be accurate. Please verify with taxi operators.</p>
                </div>
            </footer>
        </main>
    </div>

    <script>
        async function loadRanks(city, selectedRankId, selectedDestination) {
            const rankSelect = document.getElementById("ld-rank");
            const destinationSelect = document.getElementById("ld-destination");

            rankSelect.innerHTML = '<option value="">Select rank</option>';
            destinationSelect.innerHTML = '<option value="">Select destination town</option>';

            if (!city) {
                return;
            }

            const response = await fetch("index.php?action=get_ranks&city=" + encodeURIComponent(city));
            const data = await response.json();

            if (!data.success || !Array.isArray(data.ranks)) {
                return;
            }

            data.ranks.forEach((rank) => {
                const option = document.createElement("option");
                option.value = String(rank.rank_id);
                option.textContent = rank.rank_name;
                if (selectedRankId && String(selectedRankId) === String(rank.rank_id)) {
                    option.selected = true;
                }
                rankSelect.appendChild(option);
            });

            if (selectedRankId) {
                await loadDestinations(city, selectedRankId, selectedDestination);
            }
        }

        async function loadDestinations(city, rankId, selectedDestination) {
            const destinationSelect = document.getElementById("ld-destination");
            destinationSelect.innerHTML = '<option value="">Select destination town</option>';

            if (!city || !rankId) {
                return;
            }

            const response = await fetch(
                "index.php?action=get_destinations&city=" + encodeURIComponent(city) + "&rank_id=" + encodeURIComponent(rankId)
            );
            const data = await response.json();

            if (!data.success || !Array.isArray(data.destinations)) {
                return;
            }

            data.destinations.forEach((destination) => {
                const option = document.createElement("option");
                option.value = destination;
                option.textContent = destination;
                if (selectedDestination && selectedDestination === destination) {
                    option.selected = true;
                }
                destinationSelect.appendChild(option);
            });
        }

        document.addEventListener("DOMContentLoaded", async function () {
            const citySelect = document.getElementById("ld-city");
            const rankSelect = document.getElementById("ld-rank");
            const selectedCity = <?php echo json_encode($longCityValue); ?>;
            const selectedRankId = <?php echo json_encode($longRankIdValue); ?>;
            const selectedDestination = <?php echo json_encode($longDestinationValue); ?>;

            citySelect.addEventListener("change", async function () {
                await loadRanks(this.value, "", "");
            });

            rankSelect.addEventListener("change", async function () {
                await loadDestinations(citySelect.value, this.value, "");
            });

            if (selectedCity) {
                await loadRanks(selectedCity, selectedRankId, selectedDestination);
            }
        });
    </script>
</body>
</html>
