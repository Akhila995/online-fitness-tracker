<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

// Check if suggestions table exists, if not create it
$check_table = $conn->query("SHOW TABLES LIKE 'suggestions'");
if ($check_table->num_rows == 0) {
    $create_table = "CREATE TABLE suggestions (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL,
        suggestion_type VARCHAR(20),
        suggestion_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (username) REFERENCES information(username)
    )";
    
    if (!$conn->query($create_table)) {
        die("Error creating table: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { 
            background: url('sugg1.jpg') no-repeat center center/cover;
            text-align: center; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .container { 
            display: flex; 
            flex-wrap: wrap; 
            justify-content: center; 
            gap: 30px; 
        }
        .card { 
            margin: 20px; 
            padding: 20px; 
            width: 18rem; 
        }
        .user-info {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="user-info">
        <h4>Welcome, <?php echo htmlspecialchars($username); ?>!</h4>
    </div>

    <h1 class="text-danger mt-4">Your Personalized Guide to a Healthier You</h1>
    <div class="container">
        <form method="POST">
            <div class="card bg-warning text-white">
                <h3>Food Recommendations</h3>
                <p>Click to get food suggestions.</p>
                <button type="submit" name="food" class="btn btn-danger">Get Food Suggestion</button>
            </div>
        </form>
        
        <form method="POST">
            <div class="card bg-danger text-white">
                <h3>Workout Recommendations</h3>
                <p>Click to get workout suggestions.</p>
                <button type="submit" name="workout" class="btn btn-dark">Get Workout Suggestion</button>
            </div>
        </form>
        
        <form method="POST">
            <div class="card bg-success text-white">
                <h3>Diet Plans</h3>
                <p>Click to get personalized diet plans.</p>
                <button type="submit" name="diet" class="btn btn-dark">Get Diet Plan</button>
            </div>
        </form>
        
        <form method="POST">
            <div class="card bg-primary text-white">
                <h3>Fitness Goals</h3>
                <p>Click to set your fitness goals.</p>
                <button type="submit" name="goals" class="btn btn-dark">Set Fitness Goals</button>
            </div>
        </form>
    </div>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['food'])) {
            header("Location: food.php");
            exit(); 
        } elseif (isset($_POST['workout'])) {
            header("Location: workoutrecommendation.php");
            exit();
        } elseif (isset($_POST['diet'])) {
            header("Location: dietplan.php");
            exit();
        } elseif (isset($_POST['goals'])) {
            header("Location: fitnessgoal.php");
            exit();
        }
    }
    ?>
</body>
</html>