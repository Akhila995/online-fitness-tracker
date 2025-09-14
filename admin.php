<?php
// Start with error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session before any output
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_tracker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";
$admin_logged_in = false;

// Admin credentials - normally these would be stored in a database
// For security, consider using environment variables or a separate secure config file
$admin_username = "admin";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT); // This is just for demonstration

// Handle admin login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Check if the entered credentials match the admin credentials
    if ($username === $admin_username && password_verify($password, $admin_password)) {
        // Store admin in session
        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_username"] = $admin_username;
        
        // Redirect to admin dashboard
        if (!headers_sent()) {
            header("Location: admin_dashboard.php");
            exit();
        } else {
            echo "<script>window.location.href = 'admin_dashboard.php';</script>";
            exit();
        }
    } else {
        $error_message = "Invalid admin credentials.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Fitness Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .admin-container {
            display: flex;
            max-width: 900px;
            background: white;
            border-radius: 12px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .admin-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .admin-form h2 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .admin-form p {
            color: #777;
            margin-bottom: 20px;
        }

        .admin-form .form-control {
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
        }

        .admin-form .btn-primary {
            background: #341F97;
            border: none;
            font-size: 18px;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            transition: 0.3s;
        }

        .admin-form .btn-primary:hover {
            background: #231579;
        }

        .admin-form .text-link {
            color: #341F97;
            font-weight: bold;
            text-decoration: none;
        }

        .admin-form .text-link:hover {
            text-decoration: underline;
        }

        .admin-image {
            flex: 1;
            background: linear-gradient(135deg, #341F97, #1A1051);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .admin-image img {
            max-width: 100%;
            border-radius: 8px;
        }

        .lock-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #341F97;
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            .admin-image {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="admin-form">
        <div class="text-center">
            <div class="lock-icon">🔒</div>
            <h2>Admin Login</h2>
            <p>Enter your credentials to access the admin dashboard.</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Admin username" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Admin password" required>
            </div>

            <button type="submit" class="btn btn-primary">Login as Admin</button>
            
            <div class="mt-3 text-center">
                <p><a href="signin.php" class="text-link">Return to user login</a></p>
            </div>
        </form>
    </div>

    <div class="admin-image">
        <div class="text-center text-white">
            <h3>Fitness Pro Admin</h3>
            <p>Manage your fitness platform with ease</p>
        </div>
    </div>
</div>

</body>
</html>