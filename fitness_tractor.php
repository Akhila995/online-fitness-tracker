<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_tracker";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating users table: " . $conn->error);
}

$sql = "CREATE TABLE IF NOT EXISTS workout_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    workout_date DATE NOT NULL,
    workout_type VARCHAR(50) NOT NULL,
    duration INT(11) NOT NULL,
    calories_burned INT(11),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating workout_logs table: " . $conn->error);
}

$sql = "CREATE TABLE IF NOT EXISTS weight_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    log_date DATE NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating weight_logs table: " . $conn->error);
}

// Session start
session_start();

// User registration
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $password);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! Please log in.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['error'] = "Registration failed. Username may already exist.";
    }
    
    $stmt->close();
}

// User login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
        }
    } else {
        $_SESSION['error'] = "Invalid username.";
    }
    
    $stmt->close();
}

// Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Add workout log
if (isset($_POST['add_workout'])) {
    $user_id = $_SESSION['user_id'];
    $workout_date = $_POST['workout_date'];
    $workout_type = $_POST['workout_type'];
    $duration = $_POST['duration'];
    $calories_burned = $_POST['calories_burned'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("INSERT INTO workout_logs (user_id, workout_date, workout_type, duration, calories_burned, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $workout_date, $workout_type, $duration, $calories_burned, $notes);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Workout logged successfully!";
    } else {
        $_SESSION['error'] = "Error logging workout.";
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Add weight log
if (isset($_POST['add_weight'])) {
    $user_id = $_SESSION['user_id'];
    $log_date = $_POST['log_date'];
    $weight = $_POST['weight'];
    
    $stmt = $conn->prepare("INSERT INTO weight_logs (user_id, log_date, weight) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $user_id, $log_date, $weight);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Weight logged successfully!";
    } else {
        $_SESSION['error'] = "Error logging weight.";
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Delete workout log
if (isset($_GET['delete_workout'])) {
    $id = $_GET['delete_workout'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM workout_logs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Workout deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting workout.";
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Delete weight log
if (isset($_GET['delete_weight'])) {
    $id = $_GET['delete_weight'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM weight_logs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Weight log deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting weight log.";
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get workout logs for logged in user
$workout_logs = [];
$weight_logs = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get workout logs
    $stmt = $conn->prepare("SELECT * FROM workout_logs WHERE user_id = ? ORDER BY workout_date DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $workout_logs[] = $row;
    }
    
    $stmt->close();
    
    // Get weight logs
    $stmt = $conn->prepare("SELECT * FROM weight_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $weight_logs[] = $row;
    }
    
    $stmt->close();
    
    // Get weight data for chart
    $weight_data = [];
    $stmt = $conn->prepare("SELECT log_date, weight FROM weight_logs WHERE user_id = ? ORDER BY log_date ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $weight_data[] = [
            'date' => $row['log_date'],
            'weight' => $row['weight']
        ];
    }
    
    $stmt->close();
    
    // Get workout data for chart
    $workout_data = [];
    $stmt = $conn->prepare("SELECT workout_date, SUM(duration) as total_duration FROM workout_logs WHERE user_id = ? GROUP BY workout_date ORDER BY workout_date ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $workout_data[] = [
            'date' => $row['workout_date'],
            'duration' => $row['total_duration']
        ];
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Tracker</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .tracker-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .workout-card, .weight-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid #0d6efd;
        }
        .weight-card {
            border-left: 5px solid #198754;
        }
        .chart-container {
            margin-top: 20px;
            height: 300px;
        }
        .header {
            background: linear-gradient(to right, #0d6efd, #0dcaf0);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        .btn-fitness {
            background-color: #0d6efd;
            color: white;
        }
        .btn-weight {
            background-color: #198754;
            color: white;
        }
        .form-label {
            font-weight: 500;
        }
        .progress {
            height: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header text-center">
            <h1>FitTracker</h1>
            <p class="mb-0">Track your fitness journey all in one place</p>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login/Register Section -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="tracker-container">
                        <ul class="nav nav-pills mb-3" id="auth-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="login-tab" data-bs-toggle="pill" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">Login</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="register-tab" data-bs-toggle="pill" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">Register</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="auth-tabContent">
                            <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="login-username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="login-username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="login-password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="login-password" name="password" required>
                                    </div>
                                    <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="register-username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="register-username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="register-password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="register-password" name="password" required>
                                    </div>
                                    <button type="submit" name="register" class="btn btn-success w-100">Register</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Main Fitness Tracker App -->
            <div class="row">
                <div class="col-md-12 mb-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h4>
                    <a href="?logout=1" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="tracker-container">
                        <ul class="nav nav-pills mb-3" id="main-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="pill" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">Dashboard</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="workout-tab" data-bs-toggle="pill" data-bs-target="#workout" type="button" role="tab" aria-controls="workout" aria-selected="false">Log Workout</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="weight-tab" data-bs-toggle="pill" data-bs-target="#weight" type="button" role="tab" aria-controls="weight" aria-selected="false">Log Weight</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">History</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="main-tabContent">
                            <!-- Dashboard Tab -->
                            <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Weight Progress</h5>
                                            <canvas id="weightChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Workout Duration</h5>
                                            <canvas id="workoutChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white">
                                                Recent Workouts
                                            </div>
                                            <div class="card-body">
                                                <?php if (count($workout_logs) > 0): ?>
                                                    <?php foreach (array_slice($workout_logs, 0, 3) as $log): ?>
                                                        <div class="workout-card">
                                                            <h6><?php echo htmlspecialchars($log['workout_type']); ?></h6>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($log['workout_date']); ?></div>
                                                            <div><?php echo htmlspecialchars($log['duration']); ?> minutes</div>
                                                            <?php if (!empty($log['calories_burned'])): ?>
                                                                <div><?php echo htmlspecialchars($log['calories_burned']); ?> calories</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-center">No workouts logged yet. Start by logging your first workout!</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-success text-white">
                                                Recent Weight Logs
                                            </div>
                                            <div class="card-body">
                                                <?php if (count($weight_logs) > 0): ?>
                                                    <?php foreach (array_slice($weight_logs, 0, 3) as $log): ?>
                                                        <div class="weight-card">
                                                            <h6><?php echo htmlspecialchars($log['weight']); ?> kg</h6>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($log['log_date']); ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-center">No weight logs yet. Start tracking your weight!</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Log Workout Tab -->
                            <div class="tab-pane fade" id="workout" role="tabpanel" aria-labelledby="workout-tab">
                                <h4 class="mb-4">Log Your Workout</h4>
                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="workout-date" class="form-label">Date</label>
                                            <input type="date" class="form-control" id="workout-date" name="workout_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="workout-type" class="form-label">Workout Type</label>
                                            <select class="form-select" id="workout-type" name="workout_type" required>
                                                <option value="">Select workout type</option>
                                                <option value="Running">Running</option>
                                                <option value="Walking">Walking</option>
                                                <option value="Cycling">Cycling</option>
                                                <option value="Swimming">Swimming</option>
                                                <option value="Weight Training">Weight Training</option>
                                                <option value="HIIT">HIIT</option>
                                                <option value="Yoga">Yoga</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="duration" class="form-label">Duration (minutes)</label>
                                            <input type="number" class="form-control" id="duration" name="duration" min="1" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="calories-burned" class="form-label">Calories Burned (optional)</label>
                                            <input type="number" class="form-control" id="calories-burned" name="calories_burned" min="0">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                    </div>
                                    <button type="submit" name="add_workout" class="btn btn-fitness">Log Workout</button>
                                </form>
                            </div>
                            
                            <!-- Log Weight Tab -->
                            <div class="tab-pane fade" id="weight" role="tabpanel" aria-labelledby="weight-tab">
                                <h4 class="mb-4">Log Your Weight</h4>
                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="log-date" class="form-label">Date</label>
                                            <input type="date" class="form-control" id="log-date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="weight" class="form-label">Weight (kg)</label>
                                            <input type="number" step="0.1" class="form-control" id="weight" name="weight" min="1" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="add_weight" class="btn btn-weight">Log Weight</button>
                                </form>
                            </div>
                            
                            <!-- History Tab -->
                            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                                <ul class="nav nav-pills mb-3" id="history-tab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="workout-history-tab" data-bs-toggle="pill" data-bs-target="#workout-history" type="button" role="tab" aria-selected="true">Workout History</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="weight-history-tab" data-bs-toggle="pill" data-bs-target="#weight-history" type="button" role="tab" aria-selected="false">Weight History</button>
                                    </li>
                                </ul>
                                <div class="tab-content" id="history-tabContent">
                                    <!-- Workout History -->
                                    <div class="tab-pane fade show active" id="workout-history" role="tabpanel" aria-labelledby="workout-history-tab">
                                        <h5 class="mb-3">Your Workout History</h5>
                                        <?php if (count($workout_logs) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Type</th>
                                                            <th>Duration</th>
                                                            <th>Calories</th>
                                                            <th>Notes</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($workout_logs as $log): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($log['workout_date']); ?></td>
                                                                <td><?php echo htmlspecialchars($log['workout_type']); ?></td>
                                                                <td><?php echo htmlspecialchars($log['duration']); ?> min</td>
                                                                <td><?php echo !empty($log['calories_burned']) ? htmlspecialchars($log['calories_burned']) : '-'; ?></td>
                                                                <td><?php echo !empty($log['notes']) ? htmlspecialchars($log['notes']) : '-'; ?></td>
                                                                <td>
                                                                    <a href="?delete_workout=<?php echo $log['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this workout?')">Delete</a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">No workout records found. Start logging your workouts!</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Weight History -->
                                    <div class="tab-pane fade" id="weight-history" role="tabpanel" aria-labelledby="weight-history-tab">
                                        <h5 class="mb-3">Your Weight History</h5>
                                        <?php if (count($weight_logs) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Weight (kg)</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($weight_logs as $log): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($log['log_date']); ?></td>
                                                                <td><?php echo htmlspecialchars($log['weight']); ?></td>
                                                                <td>
                                                                    <a href="?delete_weight=<?php echo $log['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this weight log?')">Delete</a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">No weight records found. Start logging your weight!</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Initialize Charts -->
            <script>
                // Weight Chart
                const weightCtx = document.getElementById('weightChart').getContext('2d');
                const weightChart = new Chart(weightCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_column($weight_data, 'date')); ?>,
                        datasets: [{
                            label: 'Weight (kg)',
                            data: <?php echo json_encode(array_column($weight_data, 'weight')); ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.2)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                });
                
                // Workout Chart
                const workoutCtx = document.getElementById('workoutChart').getContext('2d');
                const workoutChart = new Chart(workoutCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($workout_data, 'date')); ?>,
                        datasets: [{
                            label: 'Duration (minutes)',
                            data: <?php echo json_encode(array_column($workout_data, 'duration')); ?>,
                            backgroundColor: 'rgba(13, 110, 253, 0.2)',
                            // Workout Chart
const workoutCtx = document.getElementById('workoutChart').getContext('2d');
const workoutChart = new Chart(workoutCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($workout_data, 'date')); ?>,
        datasets: [{
            label: 'Duration (minutes)',
            data: <?php echo json_encode(array_column($workout_data, 'duration')); ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.2)',
            borderColor: 'rgba(13, 110, 253, 1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
<?php endif; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>