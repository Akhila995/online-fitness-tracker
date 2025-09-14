<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_tracker"; // Using the original database name from the first file

// Create MySQL Connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    die("User not logged in. Please <a href='index.php'>login</a> first.");
}

$username = $_SESSION['username'];

// Handle weight update
if (isset($_POST['update_weight']) && isset($_POST['new_weight'])) {
    $new_weight = floatval($_POST['new_weight']);
    
    if ($new_weight > 0) {
        $stmt = $conn->prepare("UPDATE information SET weight = ? WHERE username = ?");
        $stmt->bind_param("ds", $new_weight, $username);
        $stmt->execute();
        $stmt->close();
        
        // Store the old weight as start weight if not already set
        if (!isset($_SESSION['start_weight'])) {
            $_SESSION['start_weight'] = $weight;
        }
        
        // Redirect back to the diet plan page
        header("Location: dietplan.php?message=weight_updated");
        exit();
    }
}

// Handle tracking updates - FIXED PART
if (isset($_POST['update_tracking'])) {
    $water_intake = isset($_POST['water_intake']) ? intval($_POST['water_intake']) : 0;
    $steps = isset($_POST['steps']) ? intval($_POST['steps']) : 0;
    $sleep_hours = isset($_POST['sleep_hours']) ? floatval($_POST['sleep_hours']) : 0;
    $meals_on_plan = isset($_POST['meals_on_plan']) ? intval($_POST['meals_on_plan']) : 0;
    
    // Store in session for now (in a real app, you'd save to database)
    $_SESSION['tracking'] = [
        'water_intake' => $water_intake,
        'steps' => $steps,
        'sleep_hours' => $sleep_hours,
        'meals_on_plan' => $meals_on_plan,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    header("Location: dietplan.php?message=tracking_updated");
    exit();
}

// Handle meal plan customization
if (isset($_POST['customize_plan'])) {
    $meal_preferences = isset($_POST['meal_preferences']) ? $_POST['meal_preferences'] : [];
    $dietary_restrictions = isset($_POST['dietary_restrictions']) ? $_POST['dietary_restrictions'] : [];
    
    // Store preferences in session (in a real app, you'd save to database)
    $_SESSION['meal_preferences'] = $meal_preferences;
    $_SESSION['dietary_restrictions'] = $dietary_restrictions;
    
    header("Location: dietplan.php?message=plan_customized");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("SELECT age, gender, height, weight, activity_level FROM information WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($age, $gender, $height, $weight, $activity_level);
$stmt->fetch();
$stmt->close();

// Default activity level if not set
if (!isset($activity_level)) {
    $activity_level = "moderate";
}

// Calculate BMI
$height_m = $height / 100; // Convert cm to meters
$bmi = $weight / ($height_m * $height_m);

// Calculate BMR (Basal Metabolic Rate) using Mifflin-St Jeor Equation
if (strtolower($gender) == "male") {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
} else {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
}

// Calculate TDEE (Total Daily Energy Expenditure)
$activity_factors = [
    "sedentary" => 1.2,
    "light" => 1.375,
    "moderate" => 1.55,
    "active" => 1.725,
    "very active" => 1.9
];

$factor = isset($activity_factors[strtolower($activity_level)]) ? $activity_factors[strtolower($activity_level)] : 1.55;
$tdee = round($bmr * $factor);

// Determine Diet Plan
$diet_plan = "";
$bmi_category = "";

if ($bmi < 18.5) {
    $bmi_category = "Underweight";
    $diet_plan = "Your BMI indicates that you are underweight. Focus on gaining healthy weight by increasing healthy fats and protein in your diet.";
    $calorie_goal = $tdee + 500; // Caloric surplus for weight gain
    $meal_plan = [
        "Breakfast" => "Oatmeal with banana and almonds, scrambled eggs, whole milk",
        "Mid-morning" => "Protein smoothie with banana, peanut butter, and milk",
        "Lunch" => "Grilled chicken, quinoa, and avocado salad with olive oil dressing",
        "Afternoon Snack" => "Greek yogurt with honey and mixed nuts",
        "Dinner" => "Salmon with sweet potatoes and steamed vegetables",
        "Evening Snack" => "Peanut butter on whole grain toast with banana slices"
    ];
    $diet_color = "warning";
} elseif ($bmi >= 18.5 && $bmi < 24.9) {
    $bmi_category = "Healthy Weight";
    $diet_plan = "You have a healthy weight. Maintain a balanced diet with proteins, carbs, and healthy fats.";
    $calorie_goal = $tdee; // Maintenance calories
    $meal_plan = [
        "Breakfast" => "Whole-grain toast with avocado and boiled eggs",
        "Mid-morning" => "Apple with a handful of mixed nuts",
        "Lunch" => "Brown rice with grilled chicken and colorful vegetables",
        "Afternoon Snack" => "Hummus with carrot and cucumber sticks",
        "Dinner" => "Baked fish with quinoa and roasted vegetables",
        "Evening Snack" => "Fruit smoothie with berries and Greek yogurt"
    ];
    $diet_color = "success";
} elseif ($bmi >= 25 && $bmi < 30) {
    $bmi_category = "Overweight";
    $diet_plan = "Your BMI suggests you are overweight. Focus on a moderate calorie deficit with high-protein, nutrient-dense foods.";
    $calorie_goal = $tdee - 300; // Moderate deficit for weight loss
    $meal_plan = [
        "Breakfast" => "Egg whites and spinach omelet, green tea",
        "Mid-morning" => "Low-fat Greek yogurt with berries",
        "Lunch" => "Grilled salmon with steamed broccoli and small portion of quinoa",
        "Afternoon Snack" => "Celery sticks with hummus",
        "Dinner" => "Lean chicken breast with stir-fried vegetables (no rice)",
        "Evening Snack" => "Cottage cheese with cucumber slices"
    ];
    $diet_color = "warning";
} else {
    $bmi_category = "Obese";
    $diet_plan = "Your BMI indicates obesity. A structured weight loss plan with physician supervision is recommended. Focus on high-protein, low-carb, fiber-rich foods.";
    $calorie_goal = $tdee - 500; // Larger deficit for weight loss
    $meal_plan = [
        "Breakfast" => "Protein shake with spinach and berries (no banana)",
        "Mid-morning" => "Hard-boiled egg with cucumber slices",
        "Lunch" => "Large salad with grilled chicken, olive oil and vinegar dressing",
        "Afternoon Snack" => "Small handful of almonds and celery sticks",
        "Dinner" => "Baked white fish with large portion of non-starchy vegetables",
        "Evening Snack" => "Sugar-free gelatin or herbal tea"
    ];
    $diet_color = "danger";
}

// Apply meal preferences and dietary restrictions if set
if (isset($_SESSION['meal_preferences']) || isset($_SESSION['dietary_restrictions'])) {
    // In a real app, you would modify the meal plan based on these preferences
    // This is just a placeholder to show how it would work
    $customized = true;
}

// Create array of dietary tips
$dietary_tips = [
    "Stay hydrated by drinking at least 8 glasses of water daily",
    "Limit processed foods and added sugars",
    "Include protein with every meal to promote satiety",
    "Eat a variety of colorful vegetables for essential nutrients",
    "Don't skip meals, especially breakfast",
    "Consider meal prepping to maintain consistency"
];

// Get tracking data from session if available
if (isset($_SESSION['tracking'])) {
    $tracking = $_SESSION['tracking'];
    $water_intake = $tracking['water_intake'];
    $steps = $tracking['steps'];
    $sleep_hours = $tracking['sleep_hours'];
    $meals_on_plan = $tracking['meals_on_plan'];
} else {
    $water_intake = 0;
    $steps = 0;
    $sleep_hours = 0;
    $meals_on_plan = 0;
}

// Track water intake and other measurements
$track_items = [
    "Water intake (glasses)" => "$water_intake/8",
    "Daily steps" => "$steps/10,000",
    "Sleep hours" => "$sleep_hours/8",
    "Meals on plan" => "$meals_on_plan/6"
];

// Calculate percentages for progress bars
$water_percent = min(100, round(($water_intake / 8) * 100));
$steps_percent = min(100, round(($steps / 10000) * 100));
$sleep_percent = min(100, round(($sleep_hours / 8) * 100));
$meals_percent = min(100, round(($meals_on_plan / 6) * 100));

// Weekly progress
if (!isset($_SESSION['start_weight']) || $_SESSION['start_weight'] <= 0) {
    // Set current weight as start weight if not set
    $_SESSION['start_weight'] = $weight;
}
$weight_change = $weight - $_SESSION['start_weight'];

// Get success message if any
$message = "";
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'weight_updated':
            $message = "Weight updated successfully!";
            break;
        case 'tracking_updated':
            $message = "Daily tracking updated successfully!";
            break;
        case 'plan_customized':
            $message = "Meal plan customized successfully!";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personalized Diet Plan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --dark-color: #5a5c69;
            --light-color: #f8f9fc;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--dark-color);
        }
        
        .container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .header-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 12px rgba(78, 115, 223, 0.25);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .header-card h1 {
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 0;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 15px 25px;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .card-body {
            padding: 20px 25px;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding-bottom: 10px;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .meal-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .meal-time {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, #224abe 0%, var(--primary-color) 100%);
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 8px rgba(34, 74, 190, 0.3);
        }
        
        .tip-list {
            list-style-type: none;
            padding-left: 0;
        }
        
        .tip-list li {
            padding: 8px 0;
            position: relative;
            padding-left: 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .tip-list li:last-child {
            border-bottom: none;
        }
        
        .tip-list li:before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--secondary-color);
        }
        
        .water-tracker, .nutrition-chart {
            height: 250px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .donut-chart {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary-color) 0% 30%, 
                var(--secondary-color) 30% 55%, 
                var(--warning-color) 55% 70%, 
                var(--info-color) 70% 100%
            );
            position: relative;
        }
        
        .donut-chart::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
        }
        
        .chart-label {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .chart-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }
        
        .chart-text {
            font-size: 0.8rem;
            color: var(--dark-color);
            opacity: 0.7;
        }
        
        .legend {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
            width: 100%;
            justify-content: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 15px;
            font-size: 0.8rem;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: 5px;
        }
        
        .progress-item {
            margin-bottom: 15px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }
        
        .bmi-indicator {
            position: relative;
            height: 15px;
            background: linear-gradient(to right, #4cc0c0 0%, #4cc0c0 18.5%, #36a2eb 18.5%, #36a2eb 25%, #ffcd56 25%, #ffcd56 30%, #ff6384 30%, #ff6384 100%);
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .bmi-pointer {
            position: absolute;
            top: -10px;
            width: 2px;
            height: 35px;
            background-color: black;
            transform: translateX(-50%);
        }
        
        .bmi-pointer::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid black;
        }
        
        .bmi-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.7rem;
            color: var(--dark-color);
        }
        
        .alert-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
        }
        
        .alert-floating.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header-card h1 {
                font-size: 1.8rem;
            }
            
            .card-header {
                padding: 12px 15px;
            }
            
            .card-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<?php if ($message): ?>
<div class="alert alert-success alert-floating" role="alert" id="alert-message">
    <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="container">
    <!-- Header Card -->
    <div class="header-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>Your Personalized Diet Plan</h1>
                <p class="mb-0">Created especially for <?php echo htmlspecialchars($username); ?></p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="suggestion.php" class="btn btn-light rounded-pill">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- User Info Card -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-user-circle me-2"></i> Profile Information
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span>Username:</span>
                        <span class="fw-bold"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="info-item">
                        <span>Age:</span>
                        <span class="fw-bold"><?php echo $age; ?> years</span>
                    </div>
                    <div class="info-item">
                        <span>Gender:</span>
                        <span class="fw-bold"><?php echo ucfirst($gender); ?></span>
                    </div>
                    <div class="info-item">
                        <span>Height:</span>
                        <span class="fw-bold"><?php echo $height; ?> cm</span>
                    </div>
                    <div class="info-item">
                        <span>Weight:</span>
                        <span class="fw-bold"><?php echo $weight; ?> kg</span>
                    </div>
                    <div class="info-item">
                        <span>Activity Level:</span>
                        <span class="fw-bold"><?php echo ucfirst($activity_level); ?></span>
                    </div>
                    <div class="info-item">
                        <span>BMR:</span>
                        <span class="fw-bold"><?php echo round($bmr); ?> calories/day</span>
                    </div>
                    <div class="info-item">
                        <span>Daily Calorie Need:</span>
                        <span class="fw-bold"><?php echo $tdee; ?> calories/day</span>
                    </div>
                    <div class="info-item">
                        <span>Recommended Intake:</span>
                        <span class="fw-bold"><?php echo $calorie_goal; ?> calories/day</span>
                    </div>
                </div>
            </div>
            
            <!-- Daily Tracking Card - FIXED PART -->
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-chart-line me-2"></i> Daily Tracking
                </div>
                <div class="card-body">
                    <form action="dietplan.php" method="post">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Water intake (glasses)</span>
                                <span><?php echo $water_intake; ?>/8</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $water_percent; ?>%" aria-valuenow="<?php echo $water_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="mt-1">
                                <input type="range" class="form-range" min="0" max="8" value="<?php echo $water_intake; ?>" name="water_intake" id="water_range">
                            </div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Daily steps</span>
                                <span><?php echo $steps; ?>/10,000</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $steps_percent; ?>%" aria-valuenow="<?php echo $steps_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="mt-1">
                                <input type="range" class="form-range" min="0" max="10000" value="<?php echo $steps; ?>" name="steps" id="steps_range">
                            </div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Sleep hours</span>
                                <span><?php echo $sleep_hours; ?>/8</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $sleep_percent; ?>%" aria-valuenow="<?php echo $sleep_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="mt-1">
                                <input type="range" class="form-range" min="0" max="8" step="0.5" value="<?php echo $sleep_hours; ?>" name="sleep_hours" id="sleep_range">
                            </div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Meals on plan</span>
                                <span><?php echo $meals_on_plan; ?>/6</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $meals_percent; ?>%" aria-valuenow="<?php echo $meals_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="mt-1">
                                <input type="range" class="form-range" min="0" max="6" value="<?php echo $meals_on_plan; ?>" name="meals_on_plan" id="meals_range">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_tracking" class="btn btn-primary w-100 mt-3">Update Tracking</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- BMI and Diet Card -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-weight me-2"></i> BMI Analysis
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="mb-3">Your BMI: <span class="text-<?php echo $diet_color; ?>"><?php echo number_format($bmi, 1); ?></span></h4>
                            <p>Category: <span class="badge bg-<?php echo $diet_color; ?>"><?php echo $bmi_category; ?></span></p>
                            <p><?php echo $diet_plan; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">BMI Scale</h5>
                            <div class="bmi-indicator">
                                <!-- Calculate the position based on the BMI value (as a percentage of 0-40 scale) -->
                                <div class="bmi-pointer" style="left: <?php echo min(100, max(0, ($bmi / 40) * 100)); ?>%;"></div>
                            </div>
                            <div class="bmi-labels">
                                <span>Underweight</span>
                                <span>Normal</span>
                                <span>Overweight</span>
                                <span>Obese</span>
                            </div>
                            <div class="d-flex justify-content-between small mt-1">
                                <span>0</span>
                                <span>18.5</span>
                                <span>25</span>
                                <span>30</span>
                                <span>40</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nutrition Distribution Card -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas fa-chart-pie me-2"></i> Nutrition Distribution
                        </div>
                        <div class="card-body">
                            <div class="nutrition-chart">
                                <div class="donut-chart">
                                    <div class="chart-label">
                                        <p class="chart-value"><?php echo $calorie_goal; ?></p>
                                        <p class="chart-text">calories</p>
                                    </div>
                                </div>
                                <div class="legend">
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: var(--primary-color);"></div>
                                        <span>Protein 30%</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: var(--secondary-color);"></div>
                                        <span>Carbs 25%</span>
                                    </div>
                                    <div class="legend-item">
                                    <div class="legend-color" style="background-color: var(--warning-color);"></div>
                                        <span>Fats 15%</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: var(--info-color);"></div>
                                        <span>Fiber 30%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weight Tracker Card -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas fa-weight me-2"></i> Weight Tracker
                        </div>
                        <div class="card-body">
                            <form action="dietplan.php" method="post">
                                <div class="mb-3">
                                    <label for="new_weight" class="form-label">Update Current Weight</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" class="form-control" id="new_weight" name="new_weight" value="<?php echo $weight; ?>" required>
                                        <span class="input-group-text">kg</span>
                                        <button type="submit" name="update_weight" class="btn btn-primary">Update</button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="mt-4">
                                <h5>Progress Summary</h5>
                                <div class="info-item">
                                    <span>Starting Weight:</span>
                                    <span class="fw-bold"><?php echo $_SESSION['start_weight']; ?> kg</span>
                                </div>
                                <div class="info-item">
                                    <span>Current Weight:</span>
                                    <span class="fw-bold"><?php echo $weight; ?> kg</span>
                                </div>
                                <div class="info-item">
                                    <span>Weight Change:</span>
                                    <span class="fw-bold <?php echo $weight_change <= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $weight_change <= 0 ? '' : '+'; ?><?php echo number_format($weight_change, 1); ?> kg
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Meal Plan Card -->
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-utensils me-2"></i> Daily Meal Plan
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Your <?php echo isset($customized) ? 'Customized' : ''; ?> Meal Plan</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#customizeMealModal">
                            <i class="fas fa-sliders-h me-1"></i> Customize
                        </button>
                    </div>
                    
                    <?php foreach ($meal_plan as $meal_time => $meal_content): ?>
                    <div class="meal-card">
                        <div class="meal-time"><?php echo $meal_time; ?></div>
                        <div><?php echo $meal_content; ?></div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-4">
                        <h5>Dietary Tips</h5>
                        <ul class="tip-list">
                            <?php foreach ($dietary_tips as $tip): ?>
                            <li><?php echo $tip; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customize Meal Plan Modal -->
<div class="modal fade" id="customizeMealModal" tabindex="-1" aria-labelledby="customizeMealModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customizeMealModalLabel">Customize Your Meal Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dietplan.php" method="post">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Meal Preferences</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="meal_preferences[]" value="high_protein" id="highProtein" 
                                    <?php echo (isset($_SESSION['meal_preferences']) && in_array('high_protein', $_SESSION['meal_preferences'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="highProtein">High Protein</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="meal_preferences[]" value="low_carb" id="lowCarb"
                                    <?php echo (isset($_SESSION['meal_preferences']) && in_array('low_carb', $_SESSION['meal_preferences'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="lowCarb">Low Carb</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="meal_preferences[]" value="low_calorie" id="lowCalorie"
                                    <?php echo (isset($_SESSION['meal_preferences']) && in_array('low_calorie', $_SESSION['meal_preferences'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="lowCalorie">Low Calorie</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="meal_preferences[]" value="high_fiber" id="highFiber"
                                    <?php echo (isset($_SESSION['meal_preferences']) && in_array('high_fiber', $_SESSION['meal_preferences'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="highFiber">High Fiber</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Dietary Restrictions</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="dietary_restrictions[]" value="vegetarian" id="vegetarian"
                                    <?php echo (isset($_SESSION['dietary_restrictions']) && in_array('vegetarian', $_SESSION['dietary_restrictions'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vegetarian">Vegetarian</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="dietary_restrictions[]" value="vegan" id="vegan"
                                    <?php echo (isset($_SESSION['dietary_restrictions']) && in_array('vegan', $_SESSION['dietary_restrictions'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vegan">Vegan</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="dietary_restrictions[]" value="gluten_free" id="glutenFree"
                                    <?php echo (isset($_SESSION['dietary_restrictions']) && in_array('gluten_free', $_SESSION['dietary_restrictions'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="glutenFree">Gluten Free</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="dietary_restrictions[]" value="dairy_free" id="dairyFree"
                                    <?php echo (isset($_SESSION['dietary_restrictions']) && in_array('dairy_free', $_SESSION['dietary_restrictions'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dairyFree">Dairy Free</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="dietary_restrictions[]" value="nut_free" id="nutFree"
                                    <?php echo (isset($_SESSION['dietary_restrictions']) && in_array('nut_free', $_SESSION['dietary_restrictions'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="nutFree">Nut Free</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="customize_plan" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update the values displayed when using the range inputs
    document.getElementById('water_range').addEventListener('input', function() {
        document.querySelector('.progress-item:nth-child(1) .progress-label span:last-child').textContent = this.value + '/8';
        document.querySelector('.progress-item:nth-child(1) .progress-bar').style.width = (this.value / 8 * 100) + '%';
    });
    
    document.getElementById('steps_range').addEventListener('input', function() {
        document.querySelector('.progress-item:nth-child(2) .progress-label span:last-child').textContent = this.value + '/10,000';
        document.querySelector('.progress-item:nth-child(2) .progress-bar').style.width = (this.value / 10000 * 100) + '%';
    });
    
    document.getElementById('sleep_range').addEventListener('input', function() {
        document.querySelector('.progress-item:nth-child(3) .progress-label span:last-child').textContent = this.value + '/8';
        document.querySelector('.progress-item:nth-child(3) .progress-bar').style.width = (this.value / 8 * 100) + '%';
    });
    
    document.getElementById('meals_range').addEventListener('input', function() {
        document.querySelector('.progress-item:nth-child(4) .progress-label span:last-child').textContent = this.value + '/6';
        document.querySelector('.progress-item:nth-child(4) .progress-bar').style.width = (this.value / 6 * 100) + '%';
    });
    
    // Show and hide alert message
    <?php if ($message): ?>
    const alertMessage = document.getElementById('alert-message');
    alertMessage.classList.add('show');
    setTimeout(function() {
        alertMessage.classList.remove('show');
    }, 3000);
    <?php endif; ?>
</script>
</body>
</html>