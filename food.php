<?php 
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_tracker";  // Corrected database name

// Create MySQL Connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$water_message = "";
$water_taken = 0;

// Handle preference update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['preference'])) {
    $new_preference = $_POST['preference'];
    $update_sql = "UPDATE information SET preference = ? WHERE username = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ss", $new_preference, $username);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Redirect to self (current page) instead of potentially missing file
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle water intake update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['water_intake'])) {
    $water_taken = intval($_POST['water_intake']);
    
    // Store water intake in session for persistence
    $_SESSION['water_intake'] = $water_taken;
} elseif (isset($_SESSION['water_intake'])) {
    // Get saved water intake from session if exists
    $water_taken = $_SESSION['water_intake'];
}

// Fetch user information
$sql = "SELECT height, weight, age, preference FROM information WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $height = $user['height'];
    $weight = $user['weight'];
    $age = $user['age'];
    $preference = $user['preference'];

    // Calculate BMI
    $bmi = round($weight / (($height / 100) * ($height / 100)), 2);
    $recommended_calories = ($bmi < 18.5) ? 2500 : (($bmi >= 18.5 && $bmi <= 24.9) ? 2000 : 1800);

    if ($bmi < 18.5) {
        $category = "Underweight";
        $category_color = "#3498db"; // Blue
    } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
        $category = "Healthy";
        $category_color = "#2ecc71"; // Green
    } else {
        $category = "Overweight";
        $category_color = "#e74c3c"; // Red
    }

    // Water Intake Calculation (30-40 ml per kg body weight)
    $min_water_intake = $weight * 30;
    $max_water_intake = $weight * 40;
    $avg_water_intake = ($min_water_intake + $max_water_intake) / 2;
    
    // Calculate percentage of recommended water consumed
    $water_percentage = ($water_taken > 0) ? round(($water_taken / $avg_water_intake) * 100) : 0;
    
    // Generate water intake message based on consumption
    if ($water_taken > 0) {
        if ($water_percentage < 25) {
            $water_message = "You need to drink more water! You've only had " . $water_percentage . "% of your recommended intake.";
            $water_alert_class = "alert-danger";
            $water_icon = "🚨";
            $progress_color = "#ff7675"; // Light red
        } elseif ($water_percentage < 50) {
            $water_message = "You're making progress, but still need more water. Currently at " . $water_percentage . "% of your recommended intake.";
            $water_alert_class = "alert-warning";
            $water_icon = "⚠️";
            $progress_color = "#fdcb6e"; // Yellow
        } elseif ($water_percentage < 75) {
            $water_message = "Good job! You're at " . $water_percentage . "% of your recommended water intake. Keep going!";
            $water_alert_class = "alert-info";
            $water_icon = "👍";
            $progress_color = "#74b9ff"; // Light blue
        } elseif ($water_percentage < 100) {
            $water_message = "Almost there! You've had " . $water_percentage . "% of your recommended water intake.";
            $water_alert_class = "alert-primary";
            $water_icon = "🎯";
            $progress_color = "#0984e3"; // Blue
        } else {
            $water_message = "Excellent! You've met or exceeded your recommended water intake for the day!";
            $water_alert_class = "alert-success";
            $water_icon = "🎉";
            $progress_color = "#00b894"; // Green
        }
    }

    // Macronutrient breakdown based on calorie needs (in grams)
    $carbs = round(0.5 * $recommended_calories / 4); // 50% of calories from carbs
    $protein = round(0.3 * $recommended_calories / 4); // 30% from protein
    $fats = round(0.2 * $recommended_calories / 9); // 20% from fats
} else {
    die("No user data found. Please update your personal information.");
}
$stmt->close();
$conn->close();

// Meal Plans Based on Category & Preference
$meal_plans = [
    "Vegetarian" => [
        "Breakfast 🥣" => "Oatmeal with nuts, Banana 🍌, Milk 🥛",
        "Lunch 🍛" => "Paneer Curry, Brown Rice 🍚, Lentils",
        "Snacks 🍪" => "Nuts, Yogurt 🍦, Cheese 🧀",
        "Dinner 🍽" => "Veggie Stir-fry, Quinoa, Salad 🥗"
    ],
    "Non-Vegetarian" => [
        "Breakfast 🍞" => "Scrambled Eggs 🍳, Whole Grain Bread 🥖",
        "Lunch 🐟" => "Salmon, Steamed Veggies, Brown Rice 🍚",
        "Snacks 🥜" => "Protein Bar, Nuts 🌰",
        "Dinner 🥩" => "Lean Beef, Quinoa, Grilled Asparagus"
    ]
];

$selected_plan = $meal_plans[$preference];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Recommendations 🎉</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #3b82f6;
            --accent-color: #60a5fa;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #0ea5e9;
            --border-radius: 12px;
            --box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f9ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            color: var(--dark-color);
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            max-width: 800px;
        }
        
        h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            text-align: center;
            color: var(--dark-color);
        }
        
        h2 {
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        h3 {
            font-size: 1.7rem;
            margin: 1.2rem 0;
            position: relative;
        }
        
        h3:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--accent-color);
            border-radius: 3px;
        }
        
        .user-stats {
            display: flex;
            justify-content: space-between;
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card {
            text-align: center;
            padding: 10px;
            flex: 1;
            border-right: 1px solid #e2e8f0;
        }
        
        .stat-card:last-child {
            border-right: none;
        }
        
        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 5px 0;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .meal-card {
            border: none;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-top: 15px;
            background: var(--light-color);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .meal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .meal-card h5 {
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--primary-color);
        }
        
        .chart-container {
            width: 100%;
            max-width: 400px;
            margin: auto;
            padding: 15px;
            background: var(--light-color);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .back-btn {
            display: block;
            width: 100%;
            margin-top: 25px;
            text-align: center;
            padding: 10px;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Water Tracking Styles */
        .water-container {
            border: none;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #e0f7fa, #e3f2fd);
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .water-container::before {
            content: '';
            position: absolute;
            right: -20px;
            bottom: -20px;
            width: 150px;
            height: 150px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="%2360a5fa40" d="M256 8C119.043 8 8 119.083 8 256c0 136.997 111.043 248 248 248s248-111.003 248-248C504 119.083 392.957 8 256 8zm0 448c-110.532 0-200-89.431-200-200 0-110.495 89.472-200 200-200 110.491 0 200 89.471 200 200 0 110.53-89.431 200-200 200zm0-338c23.196 0 42 18.804 42 42s-18.804 42-42 42-42-18.804-42-42 18.804-42 42-42zm56 254c0 6.627-5.373 12-12 12h-88c-6.627 0-12-5.373-12-12v-24c0-6.627 5.373-12 12-12h12v-64h-12c-6.627 0-12-5.373-12-12v-24c0-6.627 5.373-12 12-12h64c6.627 0 12 5.373 12 12v100h12c6.627 0 12
        
        5.373 12 12v24zm-108-74c-17.673 0-32 14.327-32 32s14.327 32 32 32 32-14.327 32-32-14.327-32-32-32z"></path></svg>') no-repeat;
            opacity: 0.2;
        }
        
        .water-progress-container {
            width: 100%;
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 10px;
            margin: 15px 0;
            overflow: hidden;
            position: relative;
        }
        
        .water-progress-bar {
            height: 100%;
            background: linear-gradient(to right, #48b1bf, #06beb6);
            border-radius: 10px;
            transition: width 0.5s ease-in-out;
        }
        
        .water-controls {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .water-input {
            flex: 1;
            padding: 10px;
            border: 2px solid #dbeafe;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
        }
        
        .water-btn {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            flex: 0 0 auto;
        }
        
        .water-btn:hover {
            box-shadow: 0 4px 12px rgba(79, 172, 254, 0.5);
            transform: translateY(-2px);
        }
        
        .preference-form {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 10px;
        }
        
        /* Animation for water icon */
        @keyframes wave {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            50% { transform: rotate(0deg); }
            75% { transform: rotate(5deg); }
            100% { transform: rotate(0deg); }
        }
        
        .water-icon {
            animation: wave 2s infinite;
            display: inline-block;
            font-size: 1.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Nutrition & Hydration Dashboard 🍎</h2>
        
        <!-- User Stats -->
        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-label">Height</div>
                <div class="stat-value"><?php echo $height; ?> cm</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Weight</div>
                <div class="stat-value"><?php echo $weight; ?> kg</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Age</div>
                <div class="stat-value"><?php echo $age; ?> years</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">BMI</div>
                <div class="stat-value"><?php echo $bmi; ?></div>
                <div>
                    <span class="category-badge" style="background-color: <?php echo $category_color; ?>">
                        <?php echo $category; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Water Tracking Section -->
        <div class="water-container">
            <h3><span class="water-icon">💧</span> Water Tracker</h3>
            <p class="text-center">Recommended water intake: <?php echo round($min_water_intake/1000, 1); ?>-<?php echo round($max_water_intake/1000, 1); ?> liters per day</p>
            
            <?php if (!empty($water_message)): ?>
                <div class="alert <?php echo $water_alert_class; ?> mt-3">
                    <span><?php echo $water_icon; ?></span> <?php echo $water_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="water-progress-container">
                <div class="water-progress-bar" style="width: <?php echo min($water_percentage, 100); ?>%; background-color: <?php echo $progress_color; ?>"></div>
            </div>
            <p class="text-center"><?php echo $water_taken; ?> ml of <?php echo round($avg_water_intake); ?> ml</p>
            
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="water-controls">
                    <input type="number" name="water_intake" class="water-input" placeholder="Enter water intake in ml" min="0" value="<?php echo $water_taken; ?>">
                    <button type="submit" class="water-btn">Update</button>
                </div>
            </form>
        </div>
        
        <!-- Calorie & Macronutrient Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-body text-center">
                        <h4 class="card-title">Daily Calories</h4>
                        <p class="display-4 fw-bold"><?php echo $recommended_calories; ?></p>
                        <p class="text-muted">Recommended daily calorie intake</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="chart-container">
                    <canvas id="macrosChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Preference Selection Form -->
        <div class="preference-form">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="preference" id="vegetarian" value="Vegetarian" <?php echo ($preference == "Vegetarian") ? "checked" : ""; ?>>
                    <label class="btn btn-outline-success" for="vegetarian">Vegetarian 🥗</label>
                    
                    <input type="radio" class="btn-check" name="preference" id="non-vegetarian" value="Non-Vegetarian" <?php echo ($preference == "Non-Vegetarian") ? "checked" : ""; ?>>
                    <label class="btn btn-outline-danger" for="non-vegetarian">Non-Vegetarian 🥩</label>
                </div>
                <button type="submit" class="btn btn-primary ms-2">Update Preference</button>
            </form>
        </div>
        
        <!-- Meal Plan Section -->
        <h3 class="mt-4">Your Meal Plan</h3>
        <div class="row">
            <?php foreach ($selected_plan as $meal => $foods): ?>
            <div class="col-md-6 mb-3">
                <div class="meal-card">
                    <h5><?php echo $meal; ?></h5>
                    <p><?php echo $foods; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Macronutrient Breakdown -->
        <h3 class="mt-4">Recommended Macronutrients</h3>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card meal-card">
                    <h5>Carbohydrates</h5>
                    <p class="display-6"><?php echo $carbs; ?>g</p>
                    <p class="text-muted">50% of calories</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card meal-card">
                    <h5>Protein</h5>
                    <p class="display-6"><?php echo $protein; ?>g</p>
                    <p class="text-muted">30% of calories</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card meal-card">
                    <h5>Fats</h5>
                    <p class="display-6"><?php echo $fats; ?>g</p>
                    <p class="text-muted">20% of calories</p>
                </div>
            </div>
        </div>
        
        <a href="suggestion.php" class="btn btn-secondary back-btn">Back</a>
    </div>
    
    <script>
        // Macronutrient Chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('macrosChart').getContext('2d');
            var macrosChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Carbs (50%)', 'Protein (30%)', 'Fats (20%)'],
                    datasets: [{
                        data: [50, 30, 20],
                        backgroundColor: [
                            '#4facfe',
                            '#00f2fe',
                            '#0ea5e9'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    family: 'Poppins',
                                    size: 12
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        });
    </script>
</body>
</html>