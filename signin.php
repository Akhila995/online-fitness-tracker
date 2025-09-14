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

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Check if username exists
    $stmt = $conn->prepare("SELECT id, password FROM registration WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $hashed_password);
        $stmt->fetch();

        // Verify password using password_verify()
        if (password_verify($password, $hashed_password)) {
            // Store user in session
            $_SESSION["user_id"] = $user_id;
            $_SESSION["username"] = $username;
            
            // Remove the problematic update statement
            // No need to update is_logged_in since the column doesn't exist
            
            // Close resources before redirect
            $stmt->close();
            $conn->close();
            
            // Make sure there's no output before header redirect
            if (!headers_sent()) {
                header("Location: personal_info.php");
                exit();
            } else {
                echo "<script>window.location.href = 'personal_info.php';</script>";
                exit();
            }
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password. Please register first.";
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Fitness Pro</title>
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

        .signin-container {
            display: flex;
            max-width: 900px;
            background: white;
            border-radius: 12px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .signin-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .signin-form h2 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .signin-form p {
            color: #777;
            margin-bottom: 20px;
        }

        .signin-form .form-control {
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
        }

        .signin-form .btn-primary {
            background: #6C5CE7;
            border: none;
            font-size: 18px;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            transition: 0.3s;
        }

        .signin-form .btn-primary:hover {
            background: #4c40af;
        }

        .signin-form .text-link {
            color: #6C5CE7;
            font-weight: bold;
            text-decoration: none;
        }

        .signin-form .text-link:hover {
            text-decoration: underline;
        }

        .signin-image {
            flex: 1;
            background: linear-gradient(135deg, #6C5CE7, #341F97);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .signin-image img {
            max-width: 100%;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .signin-container {
                flex-direction: column;
            }
            .signin-image {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="signin-container">
    <div class="signin-form">
        <h2>Sign In</h2>
        <p>Welcome back! Please log in to continue.</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary">Sign In</button>
            
            <div class="mt-3 text-center">
                <p>Don't have an account? <a href="reg.php" class="text-link">Register here</a></p>
                <p>Admin login? <a href="admin.php" class="text-link">Sign in as admin</a></p>
            </div>
        </form>
    </div>

    <div class="signin-image">
        <img src="s.jpg" alt="Fitness Image">
    </div>
</div>

</body>
</html>