<?php
// Database connection with XAMPP default credentials
$db_host = "localhost";
$db_user = "root";       // Default XAMPP username
$db_pass = "";           // Default XAMPP password is blank
$db_name = "fitness_tracker";

// Create database and table if they don't exist
$conn = new mysqli($db_host, $db_user, $db_pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db_name);

// Create users table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(50) NOT NULL,
    weight FLOAT,
    height FLOAT,
    age INT(3),
    activity_level VARCHAR(20),
    goal VARCHAR(20),
    theme VARCHAR(10),
    notifications TINYINT(1) DEFAULT 1,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Check if we have a test user, if not create one
$sql = "SELECT * FROM users WHERE id = 1";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    // Insert a default user
    $sql = "INSERT INTO users (name, email, weight, height, age, activity_level, goal, theme, notifications) 
            VALUES ('ammudu', 'ammudu@example.com', 70, 175, 30, 'moderate', 'maintain', 'light', 1)";
    if ($conn->query($sql) !== TRUE) {
        die("Error creating default user: " . $conn->error);
    }
}

// Get user ID from session or default to 1 for testing
session_start();
$user_id = $_SESSION['user_id'] ?? 1;

// Fetch user data from database
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update user settings in database
    $name = $_POST['name'];
    $email = $_POST['email'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $age = $_POST['age'];
    $activity_level = $_POST['activity_level'];
    $goal = $_POST['goal'];
    $theme = $_POST['theme'];
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    
    $update_sql = "UPDATE users SET 
                   name = ?, 
                   email = ?, 
                   weight = ?, 
                   height = ?, 
                   age = ?, 
                   activity_level = ?, 
                   goal = ?, 
                   theme = ?, 
                   notifications = ? 
                   WHERE id = ?";
                   
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssddiissii", $name, $email, $weight, $height, $age, $activity_level, $goal, $theme, $notifications, $user_id);
    
    if ($update_stmt->execute()) {
        $success_message = "Settings updated successfully!";
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $error_message = "Error updating settings: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Fitness Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
        }
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }
        .btn-primary {
            background-color: #343a40;
            border-color: #343a40;
        }
        .btn-primary:hover {
            background-color: #23272b;
            border-color: #23272b;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">Fitness Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nutrition.php">Nutrition</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">Settings</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center text-center">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Settings</h4>
                        
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="weight" class="form-label">Weight (kg)</label>
                                    <input type="number" step="0.1" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($user['weight']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="height" class="form-label">Height (cm)</label>
                                    <input type="number" step="0.1" class="form-control" id="height" name="height" value="<?php echo htmlspecialchars($user['height']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="age" class="form-label">Age</label>
                                    <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($user['age']); ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="activity_level" class="form-label">Activity Level</label>
                                    <select class="form-select" id="activity_level" name="activity_level">
                                        <option value="sedentary" <?php echo $user['activity_level'] == 'sedentary' ? 'selected' : ''; ?>>Sedentary</option>
                                        <option value="light" <?php echo $user['activity_level'] == 'light' ? 'selected' : ''; ?>>Light Activity</option>
                                        <option value="moderate" <?php echo $user['activity_level'] == 'moderate' ? 'selected' : ''; ?>>Moderate Activity</option>
                                        <option value="high" <?php echo $user['activity_level'] == 'high' ? 'selected' : ''; ?>>High Activity</option>
                                        <option value="extreme" <?php echo $user['activity_level'] == 'extreme' ? 'selected' : ''; ?>>Extreme Activity</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="goal" class="form-label">Fitness Goal</label>
                                    <select class="form-select" id="goal" name="goal">
                                        <option value="lose" <?php echo $user['goal'] == 'lose' ? 'selected' : ''; ?>>Lose Weight</option>
                                        <option value="maintain" <?php echo $user['goal'] == 'maintain' ? 'selected' : ''; ?>>Maintain Weight</option>
                                        <option value="gain" <?php echo $user['goal'] == 'gain' ? 'selected' : ''; ?>>Gain Weight</option>
                                        <option value="muscle" <?php echo $user['goal'] == 'muscle' ? 'selected' : ''; ?>>Build Muscle</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="light" <?php echo $user['theme'] == 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo $user['theme'] == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="notifications" name="notifications" <?php echo $user['notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notifications">Enable Notifications</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Close database connection
$stmt->close();
if (isset($update_stmt)) {
    $update_stmt->close();
}
$conn->close();
?>