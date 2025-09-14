<?php
session_start();

// Database Credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_tracker";

// Create MySQL Connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";
$age = $gender = $height = $weight = "";
$username = "";

// Ensure User is Logged In
if (!isset($_SESSION['username'])) {
    header("Location: reg.php"); // Redirect to registration page if not logged in
    exit();
}

$username = $_SESSION['username'];

// Fetch User Details from the Database
$sql = "SELECT age, gender, height, weight FROM information WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($age, $gender, $height, $weight);
    $stmt->fetch();
} else {
    $error = "No user found.";
}

$stmt->close();
$conn->close();

// Calculate BMI
$bmi = round($weight / (($height / 100) * ($height / 100)), 2);

// Determine the category based on BMI
if ($bmi < 16.0) {
    $category = "Severely Underweight";
} elseif ($bmi >= 16.0 && $bmi < 18.5) {
    $category = "Underweight";
} elseif ($bmi >= 18.5 && $bmi <= 24.9) {
    $category = "Healthy";
} elseif ($bmi >= 25.0 && $bmi <= 29.9) {
    $category = "Overweight";
} elseif ($bmi >= 30.0 && $bmi <= 34.9) {
    $category = "Obese";
} else {
    $category = "Severely Obese";
}

// Define the workout options with updated video links
$workout_options = [
    "Cardio & HIIT" => "https://www.youtube.com/embed/ml6cT4AZdqI",
    "Yoga for Flexibility" => "https://www.youtube.com/embed/v7AYKMP6rOE",
    "Walking & Strength" => "https://www.youtube.com/embed/z1fcRRZN7Lo",  // Updated link
    "Walking & Strength Combo" => "https://www.youtube.com/embed/9FBIaqr7TjQ",  // Updated link
];

// Define music options (Example music links)
$fitness_music = [
    "Upbeat Workout Music" => "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3",
    "Calm Meditation Music" => "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3",
    "High-Energy Music" => "https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3",
];

// Define Spotify music links with the playlist URL
$spotify_music = [
    "Spotify Playlist" => "https://open.spotify.com/",
];

// Define Calories to Burn Based on BMI
$calories_to_burn = 0;
if ($bmi < 16.0) {
    $calories_to_burn = 2500;
} elseif ($bmi >= 16.0 && $bmi < 18.5) {
    $calories_to_burn = 2200;
} elseif ($bmi >= 18.5 && $bmi <= 24.9) {
    $calories_to_burn = 2000;
} elseif ($bmi >= 25.0 && $bmi <= 29.9) {
    $calories_to_burn = 1800;
} elseif ($bmi >= 30.0 && $bmi <= 34.9) {
    $calories_to_burn = 1600;
} else {
    $calories_to_burn = 1400;
}

// Handle POST data (User selections)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_video = $_POST['workoutChoice'];
    $selected_music = $_POST['musicChoice'];
    $calories_burned = $_POST['caloriesBurned'];
    $remaining_calories = max($calories_to_burn - $calories_burned, 0);
} else {
    $selected_video = reset($workout_options); // Default to first workout option
    $selected_music = reset($fitness_music); // Default to first music option
    $calories_burned = 0;
    $remaining_calories = $calories_to_burn;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Music and Workout Recommendations</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --success-color: #4CAF50;
            --warning-color: #ff9500;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --light-accent: #e0fbfc;
            --gradient-primary: linear-gradient(120deg, #4361ee, #3a0ca3);
            --gradient-secondary: linear-gradient(120deg, #4cc9f0, #4361ee);
        }

        body {
            background: #f0f2f5;
            font-family: 'Poppins', sans-serif;
            color: #333;
            background-image: url('https://via.placeholder.com/1920x1080/f0f2f5/f0f2f5?text=+');
            background-attachment: fixed;
            background-size: cover;
        }
        
        .container {
            margin-top: 60px;
            margin-bottom: 60px;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
            border-top: 5px solid var(--primary-color);
        }
        
        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .alert-info {
            background-color: #e7f3fe;
            border-color: #b3d8ff;
            color: #31708f;
            border-radius: 15px;
            padding: 20px;
            position: relative;
            border-left: 5px solid var(--accent-color);
            transition: all 0.3s ease;
        }
        
        .alert-info:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
            border-radius: 15px;
            padding: 20px;
            position: relative;
            border-left: 5px solid var(--warning-color);
            transition: all 0.3s ease;
        }
        
        .alert-warning:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-success {
            background: var(--gradient-primary);
            border: none;
            padding: 15px 25px;
            font-weight: 600;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-success:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient-secondary);
            transition: all 0.4s ease-in-out;
            z-index: -1;
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.25);
            color: white;
        }
        
        .btn-success:hover:before {
            left: 0;
        }

        .btn-primary {
            background: var(--gradient-secondary);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            margin-bottom: 20px;
        }
        
        .btn-primary:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            transition: all 0.4s ease-in-out;
            z-index: -1;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.25);
            color: white;
        }
        
        .btn-primary:hover:before {
            left: 0;
        }
        
        .video-container, .music-container {
            margin-top: 30px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0px 8px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            border: 1px solid #eee;
            position: relative;
            padding: 20px;
            background-color: white;
        }
        
        .video-container:hover, .music-container:hover {
            transform: translateY(-10px);
            box-shadow: 0px 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        iframe {
            border-radius: 15px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            border: 1px solid #eee;
        }
        
        audio {
            width: 100%;
            border-radius: 30px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            background: var(--light-accent);
            padding: 10px;
        }
        
        audio::-webkit-media-controls-panel {
            background-color: var(--accent-color);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-top: 15px;
            display: block;
            font-size: 16px;
            position: relative;
            padding-left: 25px;
        }
        
        .form-label:before {
            content: "🔹";
            position: absolute;
            left: 0;
            top: 0;
            color: var(--primary-color);
        }
        
        .form-select, .form-control {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px 20px;
            margin: 10px 0;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
            font-size: 16px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
            background-color: white;
        }
        
        h2 {
            font-size: 38px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 25px;
            position: relative;
            text-align: center;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.1);
        }
        
        h2:after {
            content: "";
            display: block;
            width: 100px;
            height: 4px;
            background: var(--gradient-primary);
            margin: 15px auto 0;
            border-radius: 5px;
        }
        
        h3 {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-weight: 600;
            border-bottom: 2px dashed #eee;
            padding-bottom: 10px;
        }
        
        h5 {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
        }
        
        p {
            font-size: 18px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .alert-info, .alert-warning {
            font-weight: 500;
        }
        
        .text-danger {
            color: var(--danger-color) !important;
            font-weight: 600;
        }
        
        .alert-info span, .alert-warning span {
            font-weight: bold;
        }
        
        /* New styles added */
        .user-info {
            background: var(--light-accent);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            border-left: 5px solid var(--primary-color);
        }
        
        .bmi-meter {
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
            position: relative;
        }
        
        .bmi-meter-fill {
            height: 100%;
            width: <?= min(($bmi / 40) * 100, 100) ?>%;
            background: <?php
                if ($bmi < 18.5) echo 'linear-gradient(90deg, #4cc9f0, #3a86ff)';
                elseif ($bmi >= 18.5 && $bmi <= 24.9) echo 'linear-gradient(90deg, #06d6a0, #2ca58d)';
                elseif ($bmi >= 25.0 && $bmi <= 29.9) echo 'linear-gradient(90deg, #ffd166, #ff9e00)';
                else echo 'linear-gradient(90deg, #ef476f, #c9184a)';
            ?>;
            border-radius: 10px;
            transition: width 1s ease-in-out;
        }
        
        .bmi-markers {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }
        
        .bmi-value {
            position: absolute;
            left: <?= min(max(($bmi / 40) * 100, 5), 95) ?>%;
            top: -25px;
            transform: translateX(-50%);
            background: var(--dark-color);
            color: white;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .bmi-value:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid var(--dark-color);
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0px 5px 20px rgba(0, 0, 0, 0.08);
            border-top: 5px solid var(--accent-color);
            transition: all 0.3s ease;
        }
        
        .section:hover {
            transform: translateY(-5px);
            box-shadow: 0px 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 22px;
        }
        
        .section-title i {
            margin-right: 10px;
            background: var(--light-accent);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--primary-color);
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-right: 15px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Animated elements */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        /* Custom category colors */
        .category-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .category-severely-underweight {
            background-color: #4cc9f0;
            color: white;
        }
        
        .category-underweight {
            background-color: #3a86ff;
            color: white;
        }
        
        .category-healthy {
            background-color: #06d6a0;
            color: white;
        }
        
        .category-overweight {
            background-color: #ffd166;
            color: #333;
        }
        
        .category-obese, .category-severely-obese {
            background-color: #ef476f;
            color: white;
        }
        
        /* Custom form styling */
        .custom-input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        .icon-input {
            padding-left: 45px !important;
        }
        
        /* Progress bar for calories */
        .progress-wrapper {
            margin: 20px 0;
        }
        
        .progress-bar {
            height: 15px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            width: <?= min(($calories_burned / $calories_to_burn) * 100, 100) ?>%;
            background: linear-gradient(90deg, #06d6a0, #2ca58d);
            border-radius: 10px;
            transition: width 1s ease-in-out;
        }
        
        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #777;
        }
        
        /* Custom animations */
        @keyframes slideInLeft {
            from { transform: translateX(-50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideInRight {
            from { transform: translateX(50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .slide-in-left {
            animation: slideInLeft 0.5s forwards;
        }
        
        .slide-in-right {
            animation: slideInRight 0.5s forwards;
        }
        
        .fade-in {
            animation: fadeIn 1s forwards;
        }
        
        /* Back button styling */
        .back-button {
            margin-bottom: 20px;
            display: inline-block;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin-top: 30px;
                margin-bottom: 30px;
            }
            
            h2 {
                font-size: 28px;
            }
            
            h3 {
                font-size: 22px;
            }
            
            .profile-avatar {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>



    <h2 class="text-center text-primary fade-in">Personalized Fitness Recommendations</h2>
    
    <div class="user-info slide-in-left">
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>
            <h3 class="mb-0">Welcome, <?= htmlspecialchars($username) ?>!</h3>
        </div>
        
        <div class="section">
            <div class="section-title">
                <i class="fas fa-weight"></i> BMI Analysis
            </div>
            <p>Your BMI is <strong><?= $bmi ?></strong></p>
            <p>Your category is <span class="category-badge category-<?= strtolower(str_replace(' ', '-', $category)) ?>"><?= $category ?></span></p>
            
            <div class="bmi-meter">
                <div class="bmi-value"><?= $bmi ?></div>
                <div class="bmi-meter-fill"></div>
            </div>
            <div class="bmi-markers">
                <span>16</span>
                <span>18.5</span>
                <span>25</span>
                <span>30</span>
                <span>35</span>
                <span>40</span>
            </div>
        </div>
    </div>

    <div class="section slide-in-right">
        <div class="section-title">
            <i class="fas fa-dumbbell"></i> Workout Planner
        </div>
        <form method="POST">
            <div class="custom-input-group">
                <label for="workoutChoice" class="form-label">Choose Your Preferred Workout:</label>
                <i class="fas fa-video input-icon"></i>
                <select name="workoutChoice" id="workoutChoice" class="form-select icon-input" required>
                    <?php foreach ($workout_options as $option => $link): ?>
                        <option value="<?= $link ?>" <?= ($selected_video == $link) ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="custom-input-group">
                <label for="musicChoice" class="form-label">Choose Your Fitness Music:</label>
                <i class="fas fa-music input-icon"></i>
                <select name="musicChoice" id="musicChoice" class="form-select icon-input" required>
                    <?php foreach ($fitness_music as $music => $link): ?>
                        <option value="<?= $link ?>" <?= ($selected_music == $link) ? 'selected' : '' ?>><?= $music ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="custom-input-group">
                <label for="caloriesBurned" class="form-label">Enter Calories Burned:</label>
                <i class="fas fa-fire-alt input-icon"></i>
                <input type="number" name="caloriesBurned" id="caloriesBurned" class="form-control icon-input" value="<?= $calories_burned ?>" required>
            </div>
            
            <!-- Calorie Tracker (Moved here) -->
            <div class="alert alert-info mt-3 pulse-animation">
                <strong>🔥 Calories Remaining:</strong> 
                <span class="text-danger"><?= $remaining_calories ?> calories left to burn.</span>
            </div>
            
            <div class="progress-wrapper">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-labels">
                    <span>0 cal</span>
                    <span>Progress: <?= min(round(($calories_burned / $calories_to_burn) * 100), 100) ?>%</span>
                    <span><?= $calories_to_burn ?> cal</span>
                </div>
            </div>
            
            <!-- Hydration Section (Moved here) -->
            <div class="alert alert-warning mt-3">
                <strong>💧 Water Intake:</strong> Recommended Daily Intake: 
                <span class="text-primary">
                    <?= ($bmi < 18.5) ? "2.5L" : (($bmi <= 24.9) ? "3.0L" : "3.5L") ?>
                </span>
            </div>

            <button type="submit" class="btn btn-success btn-block mt-3">
                <i class="fas fa-sync-alt me-2"></i> Update Workout
            </button>
        </form>
    </div>

    <div class="video-container mt-4 fade-in">
        <div class="section-title">
            <i class="fas fa-play-circle"></i> Watch Your Recommended Workout:
        </div>
        <iframe width="100%" height="400" src="<?= $selected_video ?>" frameborder="0" allowfullscreen></iframe>
    </div>

    <div class="music-container mt-3 fade-in">
        <div class="section-title">
            <i class="fas fa-headphones"></i> Listen to Your Fitness Music:
        </div>
        <audio controls autoplay preload="auto">
            <source src="<?= $selected_music ?>" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>
    </div>

    <div class="section mt-3 slide-in-left">
        <div class="section-title">
            <i class="fab fa-spotify"></i> Spotify Music Links:
        </div>
        <div>
            <?php foreach ($spotify_music as $track => $link): ?>
                <p><strong><?= $track ?>:</strong> <a href="<?= $link ?>" target="_blank"><?= $link ?> <i class="fas fa-external-link-alt"></i></a></p>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="container">
    <!-- Back Button -->
    <a href="suggestion.php" class="btn btn-primary back-button">
        <i class="fas fa-arrow-left me-2"></i> Back to Suggestions
    </a>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>