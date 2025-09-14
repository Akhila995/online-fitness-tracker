<?php
// Start the session
session_start();

// Database connection
$servername = "localhost";
$username = "root"; // Change to your MySQL username
$password = ""; // Change to your MySQL password
$dbname = "fitness_tracker"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Log the logout time (optional)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE user_sessions SET logout_time = NOW() WHERE user_id = ? AND logout_time IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Destroy all session data
$_SESSION = array();

// If session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    // Invalidate the token in database
    $token = $_COOKIE['remember_token'];
    $sql = "UPDATE remember_tokens SET expires = NOW() WHERE token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    
    // Remove the cookie
    setcookie("remember_token", "", time() - 3600, "/", "", true, true);
}

// Close the database connection
$conn->close();

// Set redirect timer (in seconds)
$redirect_time = 2; // Shorter time before redirect
$redirect_url = "signin.php"; // Redirect to login page
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out | Fitness Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 20px;
        }
        .logout-container {
            max-width: 500px;
            margin: 80px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .logo {
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 20px;
            color: white;
        }
        .header-bar {
            background-color: #212529;
            padding: 15px 0;
            color: white;
            margin-bottom: 40px;
        }
        .success-icon {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .redirect-timer {
            font-size: 14px;
            color: #6c757d;
            margin-top: 15px;
        }
        .btn-login {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 8px 24px;
            margin-top: 15px;
        }
    </style>
    <!-- Auto-redirect after page load -->
    <meta http-equiv="refresh" content="<?php echo $redirect_time; ?>;url=<?php echo $redirect_url; ?>">
</head>
<body>
    <div class="header-bar">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="logo">Fitness Tracker</div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="logout-container text-center">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                </svg>
            </div>
            <h2>You've Been Successfully Logged Out</h2>
            <p class="mt-3">Thank you for using Fitness Tracker. We hope to see you again soon!</p>
            <div id="redirect-message" class="redirect-timer">
                You will be redirected to login in <span id="countdown"><?php echo $redirect_time; ?></span> seconds.
            </div>
            <div class="mt-4">
                <a href="<?php echo $redirect_url; ?>" class="btn btn-login btn-primary">Log In Again</a>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer for redirect
        let timeLeft = <?php echo $redirect_time; ?>;
        const countdownElement = document.getElementById('countdown');
        
        const countdownTimer = setInterval(function() {
            timeLeft -= 1;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdownTimer);
                window.location.href = "<?php echo $redirect_url; ?>";
            }
        }, 1000);
    </script>
</body>
</html>