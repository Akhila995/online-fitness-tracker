<?php
// Start session
session_start();

// Database connection
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

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Set user identification based on available session data
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    // If we don't have user_id but have username, create a temporary ID
    $username = $_SESSION['username'];
    $_SESSION['user_id'] = mt_rand(1000, 9999); // Temporary random ID
    $user_id = $_SESSION['user_id'];
}

// Make sure we have a username
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Check if the fitness_goals table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'fitness_goals'");
if ($table_check->num_rows === 0) {
    $create_table_sql = "CREATE TABLE fitness_goals (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        goal_type VARCHAR(50) NOT NULL,
        target_value DECIMAL(10,2) NOT NULL,
        current_value DECIMAL(10,2) DEFAULT 0,
        deadline DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table_sql) === TRUE) {
        $success_message = "Fitness goals tracking system initialized!";
    } else {
        $error_message = "Error creating tracking system: " . $conn->error;
    }
}

// Handle goal creation
if (isset($_POST['add_goal'])) {
    $goal_type = $_POST['goal_type'];
    $target_value = $_POST['target_value'];
    $deadline = $_POST['deadline'];
    
    try {
        // Check if a similar goal already exists (same type for the user)
        $check_stmt = $conn->prepare("SELECT id FROM fitness_goals WHERE user_id = ? AND goal_type = ?");
        $check_stmt->bind_param("is", $user_id, $goal_type);
        $check_stmt->execute();
        $duplicate_result = $check_stmt->get_result();
        
        if ($duplicate_result->num_rows > 0) {
            // A similar goal exists, update it instead of creating a new one
            $existing_goal = $duplicate_result->fetch_assoc();
            $goal_id = $existing_goal['id'];
            
            $update_stmt = $conn->prepare("UPDATE fitness_goals SET target_value = ?, deadline = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("dsi", $target_value, $deadline, $goal_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Goal updated successfully!";
            } else {
                $error_message = "Error updating goal: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            // No similar goal exists, create a new one
            $insert_stmt = $conn->prepare("INSERT INTO fitness_goals (user_id, goal_type, target_value, deadline, current_value, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $insert_stmt->bind_param("isds", $user_id, $goal_type, $target_value, $deadline);
            
            if ($insert_stmt->execute()) {
                $success_message = "Goal added successfully!";
            } else {
                $error_message = "Error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } catch (Exception $e) {
        $error_message = "Exception: " . $e->getMessage();
    }
}

// Handle goal updates
if (isset($_POST['update_goal'])) {
    $goal_id = $_POST['goal_id'];
    $current_value = $_POST['current_value'];
    
    try {
        $update_stmt = $conn->prepare("UPDATE fitness_goals SET current_value = ?, last_updated = NOW() WHERE id = ? AND user_id = ?");
        $update_stmt->bind_param("dii", $current_value, $goal_id, $user_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Progress updated successfully!";
        } else {
            $error_message = "Error: " . $update_stmt->error;
        }
        $update_stmt->close();
    } catch (Exception $e) {
        $error_message = "Exception: " . $e->getMessage();
    }
}

// Handle goal deletion
if (isset($_POST['delete_goal'])) {
    $goal_id = $_POST['goal_id'];
    
    try {
        $delete_stmt = $conn->prepare("DELETE FROM fitness_goals WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $goal_id, $user_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Goal deleted successfully!";
        } else {
            $error_message = "Error: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    } catch (Exception $e) {
        $error_message = "Exception: " . $e->getMessage();
    }
}

// Get user's current goals - Using prepared statement
try {
    $goals_stmt = $conn->prepare("SELECT * FROM fitness_goals WHERE user_id = ? ORDER BY created_at DESC");
    $goals_stmt->bind_param("i", $user_id);
    $goals_stmt->execute();
    $goals_result = $goals_stmt->get_result();
    $goals_stmt->close();
} catch (Exception $e) {
    $error_message = "Error retrieving goals: " . $e->getMessage();
    $goals_result = null;
}

// Get time of day for greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Goals - Fitness Tracker</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .main-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .main-header {
            background-color: #0d6efd;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .progress {
            height: 25px;
            margin-bottom: 15px;
        }
        .nav-username {
            font-weight: bold;
            margin-left: 10px;
        }
        .goal-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }
        .goal-card:hover {
            transform: translateY(-5px);
        }
        .goal-stats {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        .goal-type-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        /* Custom avatar circle styling */
        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 8px;
            font-weight: bold;
        }
        /* User welcome section styling */
        .user-welcome {
            background: linear-gradient(135deg, #0d6efd, #5e99f5);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.2);
        }
        .user-welcome h3 {
            margin-bottom: 10px;
            font-weight: 600;
        }
        .user-welcome p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <!-- Simplified Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Fitness Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
    <li class="nav-item">
        <a class="nav-link" href="index.php">Dashboard</a>
    </li>
    <li class="nav-item">
                        <a class="nav-link active" href="nutrition.php">Nutrition</a>
    </li>
</ul>
                
                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle text-light d-flex align-items-center" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
                        <div class="rounded-circle bg-primary d-flex justify-content-center align-items-center avatar-circle">
                            <span class="text-light">
                                <?php 
                                // Display first letter of username as avatar
                                $firstLetter = isset($username) ? strtoupper(substr($username, 0, 1)) : 'U';
                                echo htmlspecialchars($firstLetter);
                                ?>
                            </span>
                        </div>
                        <span class="ms-1"><?php echo htmlspecialchars($username); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown" style="min-width: 200px;">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><a class="dropdown-item" href="ref.html">Reference</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php" id="logout-link">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- User Welcome Section -->
        <div class="user-welcome">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="bg-white rounded-circle p-3 d-flex justify-content-center align-items-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-person-circle" style="font-size: 2rem; color: #0d6efd;"></i>
                    </div>
                </div>
                <div class="col">
                    <h3><?php echo $greeting; ?>, <?php echo htmlspecialchars($username); ?>!</h3>
                    <p>Let's track your fitness journey and achieve your goals today.</p>
                </div>
            </div>
        </div>

        <!-- Fitness Goals Header -->
        <div class="main-header text-center">
            <h2><i class="bi bi-trophy"></i> Fitness Goals</h2>
            <p>Set, track, and achieve your fitness goals</p>
        </div>
        
        <!-- Goals Stats Overview -->
        <div class="main-container">
            <h4 class="mb-3">Goals Overview</h4>
            <div class="row goal-stats">
                <?php
                // Initialize counters
                $total_goals = 0;
                $completed_goals = 0;
                $in_progress_goals = 0;
                $upcoming_goals = 0;
                
                // Count goals by status
                if (isset($goals_result) && $goals_result && $goals_result->num_rows > 0) {
                    // Reset the result pointer
                    $goals_result->data_seek(0);
                    
                    while ($goal = $goals_result->fetch_assoc()) {
                        $total_goals++;
                        
                        // Calculate progress percentage
                        $progress = 0;
                        if ($goal['target_value'] > 0) {
                            $progress = min(100, ($goal['current_value'] / $goal['target_value']) * 100);
                        }
                        
                        if ($progress >= 100) {
                            $completed_goals++;
                        } else if ($progress > 0) {
                            $in_progress_goals++;
                        } else {
                            $upcoming_goals++;
                        }
                    }
                    
                    // Reset the result pointer again for later use
                    $goals_result->data_seek(0);
                }
                ?>
                
                <div class="col-md-3 stat-item">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title text-center"><?php echo $total_goals; ?></h3>
                            <p class="card-text text-center text-muted">Total Goals</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 stat-item">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title text-center text-success"><?php echo $completed_goals; ?></h3>
                            <p class="card-text text-center text-muted">Completed</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 stat-item">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title text-center text-primary"><?php echo $in_progress_goals; ?></h3>
                            <p class="card-text text-center text-muted">In Progress</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 stat-item">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title text-center text-warning"><?php echo $upcoming_goals; ?></h3>
                            <p class="card-text text-center text-muted">Not Started</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add Goal Form -->
        <div class="main-container">
            <h4 class="mb-3">Add New Goal</h4>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="goalForm">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="goal_type" class="form-label">Goal Type</label>
                        <select class="form-select" id="goal_type" name="goal_type" required>
                            <option value="" selected disabled>Select a goal type</option>
                            <option value="weight">Weight Management</option>
                            <option value="steps">Steps</option>
                            <option value="calories">Calories</option>
                            <option value="cardio">Cardio Duration</option>
                            <option value="water_intake">Water Intake</option>
                            <option value="body_fat">Body Fat Percentage</option>
                            <option value="strength">Strength Training</option>
                            <option value="custom">Custom Goal</option>
                        </select>
                        <small class="form-text text-info mt-1">
                            <i class="bi bi-info-circle"></i> Only one goal per type is allowed. Adding a new goal of the same type will update the existing one.
                        </small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="target_value" class="form-label">Target Value</label>
                        <input type="number" class="form-control" id="target_value" name="target_value" step="0.01" required>
                        <small class="form-text text-muted" id="value-hint">Enter your target value</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="deadline" class="form-label">Target Date</label>
                        <input type="date" class="form-control" id="deadline" name="deadline" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <button type="submit" name="add_goal" class="btn btn-primary">Add/Update Goal</button>
            </form>
        </div>

        <!-- Current Goals -->
        <div class="main-container">
            <h4 class="mb-3">Current Goals</h4>
            
            <?php if (isset($goals_result) && $goals_result && $goals_result->num_rows > 0): ?>
                <div class="row">
                    <?php while($goal = $goals_result->fetch_assoc()): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card goal-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="card-title d-flex align-items-center mb-0">
                                            <?php 
                                            // Get goal icon based on type
                                            $icon_class = 'bi-bullseye';
                                            switch($goal['goal_type']) {
                                                case 'weight':
                                                    $icon_class = 'bi-clipboard-data';
                                                    break;
                                                case 'steps':
                                                    $icon_class = 'bi-person-walking';
                                                    break;
                                                case 'calories':
                                                    $icon_class = 'bi-fire';
                                                    break;
                                                case 'cardio':
                                                    $icon_class = 'bi-heart-pulse';
                                                    break;
                                                case 'water_intake':
                                                    $icon_class = 'bi-droplet';
                                                    break;
                                                case 'body_fat':
                                                    $icon_class = 'bi-percent';
                                                    break;
                                                case 'strength':
                                                    $icon_class = 'bi-lightning';
                                                    break;
                                            }
                                            
                                            $goal_name = str_replace('_', ' ', $goal['goal_type']);
                                            echo "<i class='bi " . $icon_class . " goal-type-icon'></i>" . ucwords($goal_name);
                                            ?>
                                        </h5>
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this goal?');">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <button type="submit" name="delete_goal" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <?php 
                                    // Calculate progress percentage
                                    $progress = 0;
                                    if ($goal['target_value'] > 0) {
                                        $progress = min(100, ($goal['current_value'] / $goal['target_value']) * 100);
                                    }
                                    
                                    // Set progress bar color based on progress
                                    $bar_class = 'bg-primary';
                                    if ($progress >= 100) {
                                        $bar_class = 'bg-success';
                                    } else if ($progress < 25) {
                                        $bar_class = 'bg-danger';
                                    } else if ($progress < 50) {
                                        $bar_class = 'bg-warning';
                                    }
                                    
                                    // Determine units based on goal type
                                    $units = "";
                                    switch ($goal['goal_type']) {
                                        case 'weight':
                                            $units = "kg";
                                            break;
                                        case 'steps':
                                            $units = "steps";
                                            break;
                                        case 'calories':
                                            $units = "kcal";
                                            break;
                                        case 'cardio':
                                            $units = "minutes";
                                            break;
                                        case 'water_intake':
                                            $units = "liters";
                                            break;
                                        case 'body_fat':
                                            $units = "%";
                                            break;
                                        case 'strength':
                                            $units = "kg";
                                            break;
                                        default:
                                            $units = "";
                                    }
                                    ?>
                                    
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Current: <?php echo $goal['current_value'] . ' ' . $units; ?></small>
                                        <small>Target: <?php echo $goal['target_value'] . ' ' . $units; ?></small>
                                    </div>
                                    
                                    <div class="progress" role="progressbar">
                                        <div class="progress-bar <?php echo $bar_class; ?>" style="width: <?php echo $progress; ?>%" 
                                            aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo round($progress); ?>%
                                        </div>
                                    </div>
                                    
                                    <div class="card-text">
                                        <small class="text-muted">
                                            Deadline: <?php echo date('M j, Y', strtotime($goal['deadline'])); ?>
                                            (<?php 
                                                $deadline = new DateTime($goal['deadline']);
                                                $today = new DateTime();
                                                $interval = $today->diff($deadline);
                                                if ($deadline < $today) {
                                                    echo '<span class="text-danger">Overdue by ' . $interval->days . ' days</span>';
                                                } else {
                                                    echo $interval->days . ' days remaining';
                                                }
                                            ?>)
                                        </small>
                                    </div>
                                    
                                    <!-- Update progress form -->
                                    <div class="mt-3">
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="update-form">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <div class="input-group">
                                                <input type="number" step="0.01" class="form-control form-control-sm" 
                                                    name="current_value" placeholder="Update progress" 
                                                    value="<?php echo $goal['current_value']; ?>" required>
                                                <button type="submit" name="update_goal" class="btn btn-sm btn-outline-primary">
                                                    Update
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">Last updated: 
                                            <?php 
                                                if (isset($goal['last_updated'])) {
                                                    echo date('M j, Y g:i A', strtotime($goal['last_updated']));
                                                } else {
                                                    echo "Not yet updated";
                                                }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> You don't have any fitness goals yet. Start by adding a new goal above!
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Motivational Quote -->
        <div class="main-container text-center">
        <?php
            // Array of motivational fitness quotes
            $quotes = [
                ["quote" => "The body achieves what the mind believes.", "author" => "Napoleon Hill"],
                ["quote" => "Once you learn to quit, it becomes a habit.", "author" => "Vince Lombardi"],
                ["quote" => "The only bad workout is the one that didn't happen.", "author" => "Unknown"],
                ["quote" => "It's not about having time, it's about making time.", "author" => "Unknown"],
                ["quote" => "The difference between try and triumph is a little umph.", "author" => "Marvin Phillips"],
                ["quote" => "Strength does not come from the physical capacity. It comes from an indomitable will.", "author" => "Mahatma Gandhi"],
                ["quote" => "The only place where success comes before work is in the dictionary.", "author" => "Vidal Sassoon"],
                ["quote" => "Your health is an investment, not an expense.", "author" => "Unknown"],
                ["quote" => "Take care of your body. It's the only place you have to live.", "author" => "Jim Rohn"],
                ["quote" => "Strive for progress, not perfection.", "author" => "Unknown"]
            ];
            
            // Select random quote
            $random_quote = $quotes[array_rand($quotes)];
            ?>
            
            <div class="card bg-light">
                <div class="card-body py-4">
                    <h5 class="card-title"><i class="bi bi-quote"></i> Motivation for Today</h5>
                    <blockquote class="blockquote mb-0">
                        <p><?php echo $random_quote['quote']; ?></p>
                        <footer class="blockquote-footer"><?php echo $random_quote['author']; ?></footer>
                    </blockquote>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light mt-5 py-3">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Fitness Tracker. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script>
        // Change hint text based on goal type selection
        document.getElementById('goal_type').addEventListener('change', function() {
            const goalType = this.value;
            const hintElement = document.getElementById('value-hint');
            let hintText = 'Enter your target value';
            
            switch(goalType) {
                case 'weight':
                    hintText = 'Enter target weight in kg';
                    break;
                case 'steps':
                    hintText = 'Enter daily step count target';
                    break;
                case 'calories':
                    hintText = 'Enter daily calorie target in kcal';
                    break;
                case 'cardio':
                    hintText = 'Enter cardio duration in minutes';
                    break;
                case 'water_intake':
                    hintText = 'Enter daily water intake in liters';
                    break;
                case 'body_fat':
                    hintText = 'Enter target body fat percentage';
                    break;
                case 'strength':
                    hintText = 'Enter target weight to lift in kg';
                    break;
                case 'custom':
                    hintText = 'Enter your custom target value';
                    break;
            }
            
            hintElement.textContent = hintText;
        });
        
        // Confirm logout
        document.getElementById('logout-link').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        window.addEventListener('load', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>