<?php
session_start();
include "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($username === "" || $password === "") {
        $message = "Please enter both username and password.";
    } else {
        $sql = "SELECT * FROM admins WHERE username = ? AND password = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $username, $password);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $admin = mysqli_fetch_assoc($result);

            $_SESSION["admin_id"] = $admin["admin_id"];
            $_SESSION["admin_username"] = $admin["username"];

            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid username or password.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="page-wrapper">
        <div class="container">
            <nav class="top-nav">
                <a href="../index.php">Home</a>
                <a href="../user/report_fare.php">Report Fare</a>
                <a href="login.php">Admin Login</a>
            </nav>

            <div class="card">
                <h1>Admin Login</h1>
                <p class="subtitle">Log in to manage routes and fare reports</p>

                <?php if (!empty($message)): ?>
                    <p class="status-message error"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>

                <form method="POST" action="" class="fare-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                    </div>

                    <button type="submit">Log In</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>