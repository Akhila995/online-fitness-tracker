<?php
// Database connection
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Empty password for default XAMPP setup
$dbname = "fitness_tracker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session to get logged in user ID
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // For testing purposes, we'll set a default user ID
    // In production, you would redirect to login page
    $_SESSION['user_id'] = 1; // Assuming user ID 1 exists for testing
    $_SESSION['username'] = 'ammudu'; // Default test username
    // Uncomment below for production
    // header("Location: login.php");
    // exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if nutrition_entries table exists, if not create it
$sql = "SHOW TABLES LIKE 'nutrition_entries'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE nutrition_entries (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        entry_date DATE NOT NULL,
        meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
        food_name VARCHAR(255) NOT NULL,
        calories INT(11) NOT NULL,
        protein DECIMAL(10,2) DEFAULT 0.00,
        carbs DECIMAL(10,2) DEFAULT 0.00,
        fat DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);
    
    // Add sample entries for testing
    $sql = "INSERT INTO nutrition_entries (user_id, entry_date, meal_type, food_name, calories, protein, carbs, fat) VALUES 
            (1, CURDATE(), 'breakfast', 'Oatmeal with Berries', 320, 10.5, 45.2, 8.5),
            (1, CURDATE(), 'lunch', 'Grilled Chicken Salad', 450, 35, 15, 25.5),
            (1, CURDATE(), 'dinner', 'Salmon with Vegetables', 550, 42, 18, 28),
            (1, CURDATE() - INTERVAL 1 DAY, 'breakfast', 'Yogurt with Granola', 280, 15, 35, 10),
            (1, CURDATE() - INTERVAL 1 DAY, 'lunch', 'Turkey Sandwich', 400, 30, 45, 12),
            (1, CURDATE() - INTERVAL 1 DAY, 'dinner', 'Pasta with Tomato Sauce', 520, 18, 80, 10)";
    $conn->query($sql);
}

// Handle form submission for adding new food entries
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_entry'])) {
        $entry_date = $_POST['entry_date'];
        $meal_type = $_POST['meal_type'];
        $food_name = $_POST['food_name'];
        $calories = $_POST['calories'];
        $protein = $_POST['protein'];
        $carbs = $_POST['carbs'];
        $fat = $_POST['fat'];
        
        $sql = "INSERT INTO nutrition_entries (user_id, entry_date, meal_type, food_name, calories, protein, carbs, fat) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issidddd", $user_id, $entry_date, $meal_type, $food_name, $calories, $protein, $carbs, $fat);
        
        if ($stmt->execute()) {
            $success_message = "Food entry added successfully!";
            // Redirect to reload page with fresh data
            header("Location: nutrition.php?success=1");
            exit;
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['delete_entry'])) {
        $entry_id = $_POST['entry_id'];
        
        $sql = "DELETE FROM nutrition_entries WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $entry_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Entry deleted successfully!";
            header("Location: nutrition.php?success=2");
            exit;
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get the selected date (default to today)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Function to get nutrition entries for a specific date
function getNutritionEntries($conn, $user_id, $date) {
    $sql = "SELECT * FROM nutrition_entries WHERE user_id = ? AND entry_date = ? ORDER BY meal_type, created_at";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    $stmt->close();
    return $entries;
}

// Function to get daily totals
function getDailyTotals($entries) {
    $totals = [
        'calories' => 0,
        'protein' => 0,
        'carbs' => 0,
        'fat' => 0
    ];
    
    foreach ($entries as $entry) {
        $totals['calories'] += $entry['calories'];
        $totals['protein'] += $entry['protein'];
        $totals['carbs'] += $entry['carbs'];
        $totals['fat'] += $entry['fat'];
    }
    
    return $totals;
}

// Function to get weekly summary
function getWeeklySummary($conn, $user_id, $end_date) {
    $start_date = date('Y-m-d', strtotime($end_date . ' -6 days'));
    
    $sql = "SELECT entry_date, SUM(calories) as total_calories, 
            SUM(protein) as total_protein, SUM(carbs) as total_carbs, SUM(fat) as total_fat
            FROM nutrition_entries 
            WHERE user_id = ? AND entry_date BETWEEN ? AND ?
            GROUP BY entry_date
            ORDER BY entry_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $summary = [];
    while ($row = $result->fetch_assoc()) {
        $summary[$row['entry_date']] = [
            'calories' => $row['total_calories'],
            'protein' => $row['total_protein'],
            'carbs' => $row['total_carbs'],
            'fat' => $row['total_fat']
        ];
    }
    
    // Fill in missing dates with zeros
    $current_date = strtotime($start_date);
    $end_timestamp = strtotime($end_date);
    while ($current_date <= $end_timestamp) {
        $date_str = date('Y-m-d', $current_date);
        if (!isset($summary[$date_str])) {
            $summary[$date_str] = [
                'calories' => 0,
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0
            ];
        }
        $current_date = strtotime('+1 day', $current_date);
    }
    
    // Sort by date
    ksort($summary);
    
    $stmt->close();
    return $summary;
}

// Fetch nutrition entries for the selected date
$entries = getNutritionEntries($conn, $user_id, $selected_date);
$daily_totals = getDailyTotals($entries);

// Get weekly summary
$weekly_summary = getWeeklySummary($conn, $user_id, $selected_date);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutrition Tracker | Fitness Tracker</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .stats-card {
            border-radius: 10px;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .meal-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .meal-card:hover {
            transform: translateY(-5px);
        }
        .meal-header {
            border-radius: 10px 10px 0 0;
            padding: 15px;
            color: white;
        }
        .breakfast-meal {
            background: linear-gradient(135deg, #ff9d6c, #ff7e5f);
        }
        .lunch-meal {
            background: linear-gradient(135deg, #5768f3, #1e3beb);
        }
        .dinner-meal {
            background: linear-gradient(135deg, #6c3a5c, #3f1f38);
        }
        .snack-meal {
            background: linear-gradient(135deg, #43cea2, #185a9d);
        }
        .date-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .date-nav .date-selector {
            display: flex;
            align-items: center;
        }
        .date-nav .date-selector button {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #5768f3;
            cursor: pointer;
        }
        .date-nav .date-selector span {
            margin: 0 15px;
            font-weight: 500;
            font-size: 1.2rem;
        }
        .macro-pill {
            border-radius: 20px;
            padding: 5px 10px;
            display: inline-block;
            margin-right: 5px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .calories-pill {
            background-color: #ffe0e0;
            color: #ff5555;
        }
        .protein-pill {
            background-color: #e0f0ff;
            color: #5555ff;
        }
        .carbs-pill {
            background-color: #e0ffe0;
            color: #55aa55;
        }
        .fat-pill {
            background-color: #fff0e0;
            color: #ffaa55;
        }
        .section-title {
            border-left: 5px solid #5768f3;
            padding-left: 10px;
            margin: 30px 0 20px;
        }
        .nutrition-form {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .user-badge {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 5px 12px;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
        }
        .avatar-circle {
            background-color: #5768f3;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Fitness Tracker</a>
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
                <div class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-badge">
                                <div class="avatar-circle">
                                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                                </div>
                                <?php echo htmlspecialchars($username); ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a></li>
                            <li><a class="dropdown-item" href="ref.html">Reference</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Success messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    $success_code = $_GET['success'];
                    if ($success_code == 1) echo "Food entry added successfully!";
                    elseif ($success_code == 2) echo "Entry deleted successfully!";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Error messages -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Date Navigation -->
        <div class="date-nav">
            <div class="date-selector">
                <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date . ' -1 day')); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <span>
                    <?php 
                    // Check if selected date is today
                    if ($selected_date == date('Y-m-d')) {
                        echo "Today, ";
                    } elseif ($selected_date == date('Y-m-d', strtotime('-1 day'))) {
                        echo "Yesterday, ";
                    } elseif ($selected_date == date('Y-m-d', strtotime('+1 day'))) {
                        echo "Tomorrow, ";
                    }
                    
                    echo date('F j, Y', strtotime($selected_date)); 
                    ?>
                </span>
                <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date . ' +1 day')); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <form action="" method="get" class="d-flex">
                <input type="date" name="date" class="form-control me-2" value="<?php echo $selected_date; ?>">
                <button type="submit" class="btn btn-primary">Go</button>
            </form>
        </div>
        
        <!-- Daily Nutrition Summary -->
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="stats-card text-center">
                    <div class="stats-number text-danger"><?php echo $daily_totals['calories']; ?></div>
                    <div class="stats-label">Calories</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card text-center">
                    <div class="stats-number text-primary"><?php echo round($daily_totals['protein'], 1); ?>g</div>
                    <div class="stats-label">Protein</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card text-center">
                    <div class="stats-number text-success"><?php echo round($daily_totals['carbs'], 1); ?>g</div>
                    <div class="stats-label">Carbs</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card text-center">
                    <div class="stats-number text-warning"><?php echo round($daily_totals['fat'], 1); ?>g</div>
                    <div class="stats-label">Fat</div>
                </div>
            </div>
        </div>
        
        <!-- Weekly Nutrition Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="stats-card">
                    <h5 class="mb-3">Weekly Nutrition Overview</h5>
                    <canvas id="weeklyChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Meals for the Day -->
        <h2 class="section-title">Meals for <?php echo date('F j, Y', strtotime($selected_date)); ?></h2>
        
        <div class="row">
            <!-- Breakfast Section -->
            <div class="col-md-6 mb-4">
                <div class="meal-card">
                    <div class="meal-header breakfast-meal">
                        <h4><i class="fas fa-coffee me-2"></i> Breakfast</h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $breakfast_found = false;
                        foreach ($entries as $entry) {
                            if ($entry['meal_type'] == 'breakfast') {
                                $breakfast_found = true;
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($entry['food_name']); ?></h5>
                                    <div>
                                        <span class="macro-pill calories-pill"><?php echo $entry['calories']; ?> cal</span>
                                        <span class="macro-pill protein-pill"><?php echo round($entry['protein'], 1); ?>g protein</span>
                                        <span class="macro-pill carbs-pill"><?php echo round($entry['carbs'], 1); ?>g carbs</span>
                                        <span class="macro-pill fat-pill"><?php echo round($entry['fat'], 1); ?>g fat</span>
                                    </div>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                    <button type="submit" name="delete_entry" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this entry?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php 
                            }
                        }
                        
                        if (!$breakfast_found) {
                            echo '<p class="text-muted">No breakfast entries for this day.</p>';
                        }
                        ?>
                        <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addFoodModal" data-meal-type="breakfast">
                            <i class="fas fa-plus me-1"></i> Add Breakfast Item
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Lunch Section -->
            <div class="col-md-6 mb-4">
                <div class="meal-card">
                    <div class="meal-header lunch-meal">
                        <h4><i class="fas fa-utensils me-2"></i> Lunch</h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $lunch_found = false;
                        foreach ($entries as $entry) {
                            if ($entry['meal_type'] == 'lunch') {
                                $lunch_found = true;
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($entry['food_name']); ?></h5>
                                    <div>
                                        <span class="macro-pill calories-pill"><?php echo $entry['calories']; ?> cal</span>
                                        <span class="macro-pill protein-pill"><?php echo round($entry['protein'], 1); ?>g protein</span>
                                        <span class="macro-pill carbs-pill"><?php echo round($entry['carbs'], 1); ?>g carbs</span>
                                        <span class="macro-pill fat-pill"><?php echo round($entry['fat'], 1); ?>g fat</span>
                                    </div>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                    <button type="submit" name="delete_entry" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this entry?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php 
                            }
                        }
                        
                        if (!$lunch_found) {
                            echo '<p class="text-muted">No lunch entries for this day.</p>';
                        }
                        ?>
                        <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addFoodModal" data-meal-type="lunch">
                            <i class="fas fa-plus me-1"></i> Add Lunch Item
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Dinner Section -->
            <div class="col-md-6 mb-4">
                <div class="meal-card">
                    <div class="meal-header dinner-meal">
                        <h4><i class="fas fa-moon me-2"></i> Dinner</h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $dinner_found = false;
                        foreach ($entries as $entry) {
                            if ($entry['meal_type'] == 'dinner') {
                                $dinner_found = true;
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($entry['food_name']); ?></h5>
                                    <div>
                                        <span class="macro-pill calories-pill"><?php echo $entry['calories']; ?> cal</span>
                                        <span class="macro-pill protein-pill"><?php echo round($entry['protein'], 1); ?>g protein</span>
                                        <span class="macro-pill carbs-pill"><?php echo round($entry['carbs'], 1); ?>g carbs</span>
                                        <span class="macro-pill fat-pill"><?php echo round($entry['fat'], 1); ?>g fat</span>
                                    </div>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                    <button type="submit" name="delete_entry" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this entry?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php 
                            }
                        }
                        
                        if (!$dinner_found) {
                            echo '<p class="text-muted">No dinner entries for this day.</p>';
                        }
                        ?>
                        <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addFoodModal" data-meal-type="dinner">
                            <i class="fas fa-plus me-1"></i> Add Dinner Item
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Snacks Section -->
            <div class="col-md-6 mb-4">
                <div class="meal-card">
                    <div class="meal-header snack-meal">
                        <h4><i class="fas fa-cookie-bite me-2"></i> Snacks</h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $snack_found = false;
                        foreach ($entries as $entry) {
                            if ($entry['meal_type'] == 'snack') {
                                $snack_found = true;
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($entry['food_name']); ?></h5>
                                    <div>
                                        <span class="macro-pill calories-pill"><?php echo $entry['calories']; ?> cal</span>
                                        <span class="macro-pill protein-pill"><?php echo round($entry['protein'], 1); ?>g protein</span>
                                        <span class="macro-pill carbs-pill"><?php echo round($entry['carbs'], 1); ?>g carbs</span>
                                        <span class="macro-pill fat-pill"><?php echo round($entry['fat'], 1); ?>g fat</span>
                                    </div>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                    <button type="submit" name="delete_entry" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this entry?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php 
                            }
                        }
                        
                        if (!$snack_found) {
                            echo '<p class="text-muted">No snack entries for this day.</p>';
                        }
                        ?>
                        <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addFoodModal" data-meal-type="snack">
                            <i class="fas fa-plus me-1"></i> Add Snack Item
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Food Modal -->
    <div class="modal fade" id="addFoodModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Food Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="entry_date" value="<?php echo $selected_date; ?>">
                        <div class="mb-3">
                            <label for="meal_type" class="form-label">Meal Type</label>
                            <select class="form-select" id="meal_type" name="meal_type" required>
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch">Lunch</option>
                                <option value="dinner">Dinner</option>
                                <option value="snack">Snack</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="food_name" class="form-label">Food Name</label>
                            <input type="text" class="form-control" id="food_name" name="food_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="calories" class="form-label">Calories</label>
                            <input type="number" class="form-control" id="calories" name="calories" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="protein" class="form-label">Protein (g)</label>
                            <input type="number" step="0.1" class="form-control" id="protein" name="protein" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="carbs" class="form-label">Carbs (g)</label>
                            <input type="number" step="0.1" class="form-control" id="carbs" name="carbs" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="fat" class="form-label">Fat (g)</label>
                            <input type="number" step="0.1" class="form-control" id="fat" name="fat" min="0" required>
                        </div>
                        <button type="submit" name="add_entry" class="btn btn-primary">Add Food</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <div class="avatar-circle mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                    </div>
                    <h4><?php echo htmlspecialchars($username); ?></h4>
                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="mb-3">
                                <span class="text-muted d-block">User ID</span>
                                <strong><?php echo $user_id; ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <span class="text-muted d-block">Account Type</span>
                                <strong>Basic</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="profile.php" class="btn btn-primary">Edit Profile</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set meal type in modal based on which button was clicked
        document.addEventListener('DOMContentLoaded', function() {
            var addFoodModal = document.getElementById('addFoodModal')
            addFoodModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget
                var mealType = button.getAttribute('data-meal-type')
                var mealTypeSelect = addFoodModal.querySelector('#meal_type')
                mealTypeSelect.value = mealType
            })
            
            // Initialize Weekly Chart
            const weeklyChartEl = document.getElementById('weeklyChart').getContext('2d')
            
            // Prepare data for chart
            const weeklyLabels = []
            const caloriesData = []
            const proteinData = []
            const carbsData = []
            const fatData = []
            
            <?php
            foreach ($weekly_summary as $date => $values) {
                echo "weeklyLabels.push('".date('D', strtotime($date))."');\n";
                echo "caloriesData.push(".$values['calories'].");\n";
                echo "proteinData.push(".round($values['protein'], 1).");\n";
                echo "carbsData.push(".round($values['carbs'], 1).");\n";
                echo "fatData.push(".round($values['fat'], 1).");\n";
            }
            ?>
            
            const weeklyChart = new Chart(weeklyChartEl, {
                type: 'bar',
                data: {
                    labels: weeklyLabels,
                    datasets: [
                        {
                            label: 'Calories',
                            data: caloriesData,
                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                            borderColor: 'rgb(255, 99, 132)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Calories'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const dataIndex = context.dataIndex;
                                    return [
                                        'Protein: ' + proteinData[dataIndex] + 'g',
                                        'Carbs: ' + carbsData[dataIndex] + 'g',
                                        'Fat: ' + fatData[dataIndex] + 'g'
                                    ];
                                }
                            }
                        }
                    }
                }
            });
            
            // Add macro toggle buttons
            const chartContainer = document.querySelector('#weeklyChart').parentNode;
            const toggleContainer = document.createElement('div');
            toggleContainer.className = 'btn-group btn-group-sm mt-3';
            toggleContainer.innerHTML = `
                <button type="button" class="btn btn-danger active" data-metric="calories">Calories</button>
                <button type="button" class="btn btn-primary" data-metric="protein">Protein</button>
                <button type="button" class="btn btn-success" data-metric="carbs">Carbs</button>
                <button type="button" class="btn btn-warning" data-metric="fat">Fat</button>
            `;
            chartContainer.appendChild(toggleContainer);
            
            // Handle toggle button clicks
            const toggleButtons = toggleContainer.querySelectorAll('button');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const metric = this.getAttribute('data-metric');
                    
                    // Update active button
                    toggleButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update chart data
                    let chartData;
                    let label;
                    let color;
                    
                    if (metric === 'calories') {
                        chartData = caloriesData;
                        label = 'Calories';
                        color = 'rgb(255, 99, 132)';
                        backgroundColor = 'rgba(255, 99, 132, 0.5)';
                    } else if (metric === 'protein') {
                        chartData = proteinData;
                        label = 'Protein (g)';
                        color = 'rgb(54, 162, 235)';
                        backgroundColor = 'rgba(54, 162, 235, 0.5)';
                    } else if (metric === 'carbs') {
                        chartData = carbsData;
                        label = 'Carbs (g)';
                        color = 'rgb(75, 192, 192)';
                        backgroundColor = 'rgba(75, 192, 192, 0.5)';
                    } else if (metric === 'fat') {
                        chartData = fatData;
                        label = 'Fat (g)';
                        color = 'rgb(255, 159, 64)';
                        backgroundColor = 'rgba(255, 159, 64, 0.5)';
                    }
                    
                    weeklyChart.data.datasets[0].data = chartData;
                    weeklyChart.data.datasets[0].label = label;
                    weeklyChart.data.datasets[0].borderColor = color;
                    weeklyChart.data.datasets[0].backgroundColor = backgroundColor;
                    weeklyChart.options.scales.y.title.text = label;
                    weeklyChart.update();
                });
            });
        });
    </script>
</body>
</html>