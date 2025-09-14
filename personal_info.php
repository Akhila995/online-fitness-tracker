<?php
session_start();
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

// Check if information table exists, if not create it
$check_table = $conn->query("SHOW TABLES LIKE 'information'");
if ($check_table->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table = "CREATE TABLE information (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL UNIQUE,
        age INT(3),
        gender VARCHAR(10),
        height VARCHAR(10),
        weight VARCHAR(10),
        profile_photo VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($create_table)) {
        die("Error creating table: " . $conn->error);
    }
}

$error = "";
$success = "";
$age = $gender = $height = $weight = $profile_photo = "";
$username = "";

// Check if user is coming from login page and set the username from session
if (isset($_SESSION["user"]) && !isset($_POST['check_user'])) {
    $username = $_SESSION["user"];
    
    // Get user info if available
    $stmt = $conn->prepare("SELECT age, gender, height, weight, profile_photo FROM information WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($age, $gender, $height, $weight, $profile_photo);
        $stmt->fetch();
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_user'])) {
    $username = trim($_POST['username']);
    $_SESSION['username'] = $username;

    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT age, gender, height, weight, profile_photo FROM information WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($age, $gender, $height, $weight, $profile_photo);
            $stmt->fetch(); 
        } else {
            // Allow new users to enter their details instead of showing an error
            $error = ""; 
            $age = "";
            $gender = "";
            $height = "";
            $weight = "";
            $profile_photo = "";
        }

        $stmt->close();
    } else {
        $error = "Please enter a username.";
    }
}

// When user submits personal details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_details'])) {
    $username = $_POST['username'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $height = $_POST['height'];
    $weight = $_POST['weight'];

    // Create uploads directory if it doesn't exist
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }

    // Handle profile photo upload
    $profile_photo = "";
    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "uploads/";
        $file_name = time() . '_' . basename($_FILES['profile_photo']['name']);
        $profile_photo = $target_dir . $file_name;
        
        // Check if file is an actual image
        $check = getimagesize($_FILES['profile_photo']['tmp_name']);
        if($check !== false) {
            move_uploaded_file($_FILES['profile_photo']['tmp_name'], $profile_photo);
        } else {
            $error = "File is not an image.";
            $profile_photo = "";
        }
    }

    if(empty($error)) {
        $stmt = $conn->prepare("SELECT username FROM information WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Update the existing user details
            $update_query = "UPDATE information SET age=?, gender=?, height=?, weight=?";
            $param_types = "isss";
            $params = array($age, $gender, $height, $weight);
            
            // Only update profile photo if a new one was uploaded
            if (!empty($_FILES['profile_photo']['name'])) {
                $update_query .= ", profile_photo=?";
                $param_types .= "s";
                $params[] = $profile_photo;
            }
            
            $update_query .= " WHERE username=?";
            $param_types .= "s";
            $params[] = $username;
            
            $stmt = $conn->prepare($update_query);
            // Use callback to bind parameters dynamically
            $stmt->bind_param($param_types, ...$params);
        } else {
            // Insert new user details
            if(empty($profile_photo)) {
                $profile_photo = "default-profile.png"; // Default image
            }
            
            $stmt = $conn->prepare("INSERT INTO information (username, age, gender, height, weight, profile_photo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissss", $username, $age, $gender, $height, $weight, $profile_photo);
        }

        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            
            // Create suggestion table if it doesn't exist
            $check_suggestion_table = $conn->query("SHOW TABLES LIKE 'suggestions'");
            if ($check_suggestion_table->num_rows == 0) {
                $create_suggestion_table = "CREATE TABLE suggestions (
                    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(30) NOT NULL,
                    suggestion_type VARCHAR(20),
                    suggestion_text TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (username) REFERENCES information(username)
                )";
                $conn->query($create_suggestion_table);
            }
            
            // Create suggestion.php file if it doesn't exist
            if (!file_exists('suggestion.php')) {
                // Create a basic suggestion.php file
                $suggestion_content = '<?php
session_start();
include "config.php";
echo "<h1>Fitness Suggestions</h1>";
echo "<p>This is a placeholder for the suggestions page.</p>";
echo "<a href=\'dashboard.php\'>Go to Dashboard</a>";
?>';
                file_put_contents('suggestion.php', $suggestion_content);
            }
            
            $success = "Information saved successfully!";
            header("Location: suggestion.php");
            exit();
        } else {
            $error = "Error saving details: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f8ff;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            background: white;
        }
        .hero-image {
            width: 100%;
            height: auto;
            border-radius: 10px;
        }
        .profile-pic-container {
            text-align: center;
            margin-bottom: 15px;
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            cursor: pointer;
        }
        .hidden-file-input {
            display: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row align-items-center">
        <div class="col-md-6">
            <img src="pe.png" alt="Health App" class="hero-image" onerror="this.src='https://placehold.co/600x400?text=Fitness+Pro'">
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="text-primary text-center">Personal Information</h2>
                    <?php if (!empty($error)) : ?>
                        <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success text-center"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <!-- Username Input -->
                    <?php if (empty($username)) : ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Enter Username</label>
                            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                        <button type="submit" name="check_user" class="btn btn-primary w-100">Check Details</button>
                    </form>
                    <?php endif; ?>

                    <?php if (!empty($username)) : ?>
                        <!-- Auto-Filled Form -->
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

                            <div class="profile-pic-container">
                                <label for="profile_photo">
                                    <img id="profile-pic-preview" src="<?php echo !empty($profile_photo) ? $profile_photo : 'default-profile.png'; ?>" class="profile-pic" alt="Profile Picture" onerror="this.src='https://placehold.co/120x120?text=Profile'">
                                </label>
                                <input type="file" class="hidden-file-input" name="profile_photo" id="profile_photo" accept="image/*" onchange="previewProfilePic(event)">
                                <p class="small text-muted">Click on the image to change profile picture</p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Age</label>
                                <input type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($age); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select your gender</option>
                                    <option value="Male" <?php if ($gender == 'Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if ($gender == 'Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if ($gender == 'Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Height (in cm)</label>
                                <input type="number" class="form-control" name="height" value="<?php echo htmlspecialchars($height); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Weight (in kg)</label>
                                <input type="number" class="form-control" name="weight" value="<?php echo htmlspecialchars($weight); ?>" required>
                            </div>
                            <button type="submit" name="save_details" class="btn btn-success w-100">Save & Continue</button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function previewProfilePic(event) {
        if (event.target.files && event.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('profile-pic-preview');
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }
    }
</script>

</body>
</html>