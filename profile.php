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
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$messageType = "";
$username = $_SESSION['username'];
// Determine the column names in the users table
$user_columns = [];
$columnsResult = $conn->query("SHOW COLUMNS FROM users");
while ($column = $columnsResult->fetch_assoc()) {
    $user_columns[] = $column['Field'];
}

// Figure out column names for the users table
$name_column = in_array('fullname', $user_columns) ? 'fullname' : 
              (in_array('name', $user_columns) ? 'name' : 
              (in_array('username', $user_columns) ? 'username' : 'full_name'));

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update basic profile info
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $gender = $_POST['gender'];
        $birthdate = $_POST['birthdate'];
        $height = floatval($_POST['height']);
        $phone = trim($_POST['phone']);
        
        // Build the SQL query based on available columns
        $sql = "UPDATE users SET ";
        $types = "";
        $params = [];
        
        if (in_array($name_column, $user_columns)) {
            $sql .= "$name_column = ?, ";
            $types .= "s";
            $params[] = $fullname;
        }
        
        if (in_array('email', $user_columns)) {
            $sql .= "email = ?, ";
            $types .= "s";
            $params[] = $email;
        }
        
        if (in_array('gender', $user_columns)) {
            $sql .= "gender = ?, ";
            $types .= "s";
            $params[] = $gender;
        }
        
        if (in_array('birthdate', $user_columns)) {
            $sql .= "birthdate = ?, ";
            $types .= "s";
            $params[] = $birthdate;
        }
        
        if (in_array('height', $user_columns)) {
            $sql .= "height = ?, ";
            $types .= "d";
            $params[] = $height;
        }
        
        if (in_array('phone', $user_columns)) {
            $sql .= "phone = ?, ";
            $types .= "s";
            $params[] = $phone;
        }
        
        // Remove the trailing comma and space
        $sql = rtrim($sql, ", ");
        
        $sql .= " WHERE id = ?";
        $types .= "i";
        $params[] = $user_id;
        
        $stmt = $conn->prepare($sql);
        
        // Create the parameter binding array
        $bindParams = array($types);
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        
        // Call bind_param with the unpacked array
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $messageType = "success";
            
            // Update session data if appropriate
            if ($name_column == 'username') {
                $_SESSION['username'] = $fullname;
            }
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $message = "Error updating profile: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    } elseif (isset($_POST['update_password'])) {
        // Update password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        // Fix: Check if $user_data exists before accessing array key
        if ($user_data && password_verify($current_password, $user_data['password'])) {
            if ($new_password === $confirm_password) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update the password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password updated successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error updating password: " . $stmt->error;
                    $messageType = "danger";
                }
                $stmt->close();
            } else {
                $message = "New passwords do not match!";
                $messageType = "danger";
            }
        } else {
            $message = "Current password is incorrect or user not found!";
            $messageType = "danger";
        }
    } elseif (isset($_POST['update_preferences'])) {
        // Update user preferences
        $weight_unit = $_POST['weight_unit'];
        $height_unit = $_POST['height_unit'];
        $distance_unit = $_POST['distance_unit'];
        $theme = $_POST['theme'];
        $notification_preference = isset($_POST['notification_preference']) ? 1 : 0;
        
        // Check if preferences table exists
        $result = $conn->query("SHOW TABLES LIKE 'user_preferences'");
        if ($result->num_rows == 0) {
            // Create preferences table if it doesn't exist
            $conn->query("CREATE TABLE user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                weight_unit VARCHAR(10) DEFAULT 'kg',
                height_unit VARCHAR(10) DEFAULT 'cm',
                distance_unit VARCHAR(10) DEFAULT 'km',
                theme VARCHAR(20) DEFAULT 'light',
                notification_enabled TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )");
        }
        
        // Check if user has preferences already
        $stmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            // Update existing preferences
            $sql = "UPDATE user_preferences SET 
                    weight_unit = ?, 
                    height_unit = ?, 
                    distance_unit = ?, 
                    theme = ?, 
                    notification_enabled = ?
                    WHERE user_id = ?";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $weight_unit, $height_unit, $distance_unit, $theme, $notification_preference, $user_id);
        } else {
            // Insert new preferences
            $sql = "INSERT INTO user_preferences 
                    (user_id, weight_unit, height_unit, distance_unit, theme, notification_enabled) 
                    VALUES (?, ?, ?, ?, ?, ?)";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssi", $user_id, $weight_unit, $height_unit, $distance_unit, $theme, $notification_preference);
        }
        
        if ($stmt->execute()) {
            $message = "Preferences updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating preferences: " . $stmt->error;
            $messageType = "danger";
        }
        $stmt->close();
    } elseif (isset($_POST['upload_avatar'])) {
        // Handle profile picture upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            // Verify file extension
            if (in_array(strtolower($filetype), $allowed)) {
                // Create uploads directory if it doesn't exist
                $upload_dir = "uploads/avatars/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Create unique filename
                $new_filename = $user_id . '_' . time() . '.' . $filetype;
                $upload_path = $upload_dir . $new_filename;
                
                // Upload file
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // Check if avatar column exists
                    if (in_array('avatar', $user_columns)) {
                        // Update avatar path in database
                        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        $stmt->bind_param("si", $upload_path, $user_id);
                        
                        if ($stmt->execute()) {
                            $message = "Profile picture uploaded successfully!";
                            $messageType = "success";
                            
                            // Update user data
                            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $user = $result->fetch_assoc();
                        } else {
                            $message = "Error updating database: " . $stmt->error;
                            $messageType = "danger";
                        }
                        $stmt->close();
                    } else {
                        $message = "Avatar column doesn't exist in users table.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Error uploading file!";
                    $messageType = "danger";
                }
            } else {
                $message = "Invalid file type. Please upload a JPG, JPEG, PNG, or GIF file.";
                $messageType = "danger";
            }
        } else {
            $message = "Please select a file to upload.";
            $messageType = "warning";
        }
    }
}

// Get user preferences
$preferences = [
    'weight_unit' => 'kg',
    'height_unit' => 'cm',
    'distance_unit' => 'km',
    'theme' => 'light',
    'notification_enabled' => 1
];

// First check if the table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'user_preferences'")->num_rows > 0;

if ($tableExists) {
    // Table exists, now we can query it
    $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $pref_data = $result->fetch_assoc();
            $preferences = [
                'weight_unit' => $pref_data['weight_unit'],
                'height_unit' => $pref_data['height_unit'],
                'distance_unit' => $pref_data['distance_unit'],
                'theme' => $pref_data['theme'],
                'notification_enabled' => $pref_data['notification_enabled']
            ];
        }
        $stmt->close();
    }
}

// Fetch user stats
$stats = [
    'total_workouts' => 0,
    'total_distance' => 0,
    'total_calories' => 0,
    'avg_steps' => 0,
    'goal_completion_rate' => 0,
    'joined_days' => 0
];

// Check tables and their column names
function checkTableColumn($conn, $tableName, $defaultColumnName) {
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
    if (!$tableExists) {
        return [false, $defaultColumnName];
    }
    
    // Check column names
    $columnsResult = $conn->query("SHOW COLUMNS FROM $tableName");
    $columnFound = false;
    $columnName = $defaultColumnName;
    
    while ($column = $columnsResult->fetch_assoc()) {
        if ($column['Field'] == $defaultColumnName) {
            $columnFound = true;
            break;
        }
        if ($column['Field'] == 'userId') {
            $columnName = 'userId';
            $columnFound = true;
            break;
        }
    }
    
    return [$columnFound, $columnName];
}

// Check workouts table
list($workoutsExists, $workoutsUserIdColumn) = checkTableColumn($conn, 'workouts', 'user_id');
if ($workoutsExists) {
    // Calculate total workouts
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM workouts WHERE $workoutsUserIdColumn = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stats['total_workouts'] = $row['count'];
        $stmt->close();
    }
    
    // Check if distance column exists
    $distanceExists = $conn->query("SHOW COLUMNS FROM workouts LIKE 'distance'")->num_rows > 0;
    if ($distanceExists) {
        $stmt = $conn->prepare("SELECT SUM(distance) as total FROM workouts WHERE $workoutsUserIdColumn = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['total_distance'] = $row['total'] ? round($row['total'], 2) : 0;
            $stmt->close();
        }
    }
    
    // Check if calories column exists
    $caloriesExists = $conn->query("SHOW COLUMNS FROM workouts LIKE 'calories'")->num_rows > 0;
    if ($caloriesExists) {
        $stmt = $conn->prepare("SELECT SUM(calories) as total FROM workouts WHERE $workoutsUserIdColumn = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['total_calories'] = $row['total'] ? round($row['total']) : 0;
            $stmt->close();
        }
    }
}

// Check daily_activity table
list($activityExists, $activityUserIdColumn) = checkTableColumn($conn, 'daily_activity', 'user_id');
if ($activityExists) {
    // Check if steps column exists
    $stepsExists = $conn->query("SHOW COLUMNS FROM daily_activity LIKE 'steps'")->num_rows > 0;
    if ($stepsExists) {
        $stmt = $conn->prepare("SELECT AVG(steps) as average FROM daily_activity WHERE $activityUserIdColumn = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['avg_steps'] = $row['average'] ? round($row['average']) : 0;
            $stmt->close();
        }
    }
}

// Check fitness_goals table
list($goalsExists, $goalsUserIdColumn) = checkTableColumn($conn, 'fitness_goals', 'user_id');
if ($goalsExists) {
    // Check if status column exists
    $statusExists = $conn->query("SHOW COLUMNS FROM fitness_goals LIKE 'status'")->num_rows > 0;
    if ($statusExists) {
        $stmt = $conn->prepare("SELECT 
                            COUNT(*) as total_goals,
                            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_goals
                            FROM fitness_goals 
                            WHERE $goalsUserIdColumn = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stats['goal_completion_rate'] = $row['total_goals'] > 0 ? 
                round(($row['completed_goals'] / $row['total_goals']) * 100) : 0;
            $stmt->close();
        }
    }
}

// Calculate days since joining
if (isset($user['created_at'])) {
    $join_date = new DateTime($user['created_at']);
    $today = new DateTime();
    $stats['joined_days'] = $today->diff($join_date)->days;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile | Fitness Tracker</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: bold;
            color: white;
        }
        .profile-header {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .avatar-initials {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #5768f3;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: bold;
            border: 5px solid #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .avatar-edit {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 40px;
            height: 40px;
            background-color: #5768f3;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        .avatar-edit:hover {
            background-color: #4050e6;
        }
        .profile-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: transform 0.3s;
        }
        .profile-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            border-radius: 10px 10px 0 0;
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        }
        .nav-pills .nav-link.active {
            background-color: #5768f3;
        }
        .nav-pills .nav-link {
            color: #495057;
        }
        .form-label {
            font-weight: 500;
        }
        .user-badge {
            background-color: rgba(87, 104, 243, 0.2);
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
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .profile-header h4 {
            margin-bottom: 5px;
        }
        .profile-header .text-muted {
            margin-bottom: 15px;
        }
        .activity-badge {
            font-size: 2rem;
            color: #5768f3;
            margin-bottom: 10px;
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
                        <a class="nav-link" href="nutrition.php">Nutrition</a>
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
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
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
        <!-- Alert messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header text-center">
            <div class="avatar-container">
                <?php if (isset($user['avatar']) && !empty($user['avatar']) && file_exists($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile Picture" class="avatar">
                <?php else: ?>
                    <div class="avatar-initials">
                        
            <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="avatar-edit" data-bs-toggle="modal" data-bs-target="#avatarModal">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <h4><?php echo htmlspecialchars($username); ?></h4>
            <p class="text-muted"><?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?></p>
            <div class="mb-3">
                <span class="badge bg-primary"><?php echo isset($user['gender']) ? htmlspecialchars($user['gender']) : 'Not specified'; ?></span>
                <span class="badge bg-info">Member for <?php echo $stats['joined_days']; ?> days</span>
            </div>
        </div>
        
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="profile-card stat-card">
                    <div class="activity-badge">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_workouts']; ?></div>
                    <div class="stat-label">Total Workouts</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="profile-card stat-card">
                    <div class="activity-badge">
                        <i class="fas fa-road"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_distance']; ?> <?php echo $preferences['distance_unit']; ?></div>
                    <div class="stat-label">Total Distance</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="profile-card stat-card">
                    <div class="activity-badge">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_calories']; ?></div>
                    <div class="stat-label">Total Calories Burned</div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="profile-card stat-card">
                    <div class="activity-badge">
                        <i class="fas fa-shoe-prints"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['avg_steps']; ?></div>
                    <div class="stat-label">Average Daily Steps</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="profile-card stat-card">
                    <div class="activity-badge">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['goal_completion_rate']; ?>%</div>
                    <div class="stat-label">Goal Completion Rate</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="profile-card stat-card">
                    <div class="activity-badge">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>
        </div>
        
        <!-- Profile Content -->
        <div class="profile-card">
            <div class="card-header">
                <ul class="nav nav-pills" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-selected="true">Personal Info</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-selected="false">Change Password</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab" aria-selected="false">Preferences</button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                        <form action="" method="post">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="fullname" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="birthdate" class="form-label">Birth Date</label>
                                    <input type="date" class="form-control" id="birthdate" name="birthdate" value="<?php echo $user['birthdate'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Choose...</option>
                                        <option value="Male" <?php echo (isset($user['gender']) && $user['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($user['gender']) && $user['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($user['gender']) && $user['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                        <option value="Prefer not to say" <?php echo (isset($user['gender']) && $user['gender'] === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="height" class="form-label">Height (<?php echo $preferences['height_unit']; ?>)</label>
                                    <input type="number" class="form-control" id="height" name="height" step="0.01" value="<?php echo $user['height'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Password Tab -->
                    <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                            <div class="mb-3">
    <label for="new_password" class="form-label">New Password</label>
    <input type="password" class="form-control" id="new_password" name="new_password" required>
</div>

<div class="mb-3">
    <label for="confirm_password" class="form-label">Confirm New Password</label>
    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
</div>

<div class="d-grid gap-2 d-md-flex justify-content-md-end">
    <button type="submit" name="update_password" class="btn btn-primary">
        <i class="fas fa-key me-2"></i>Update Password
    </button>
</div>
</form>
</div>

<!-- Preferences Tab -->
<div class="tab-pane fade" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
    <form action="" method="post">
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="weight_unit" class="form-label">Weight Unit</label>
                <select class="form-select" id="weight_unit" name="weight_unit">
                    <option value="kg" <?php echo ($preferences['weight_unit'] === 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                    <option value="lb" <?php echo ($preferences['weight_unit'] === 'lb') ? 'selected' : ''; ?>>Pounds (lb)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="height_unit" class="form-label">Height Unit</label>
                <select class="form-select" id="height_unit" name="height_unit">
                    <option value="cm" <?php echo ($preferences['height_unit'] === 'cm') ? 'selected' : ''; ?>>Centimeters (cm)</option>
                    <option value="in" <?php echo ($preferences['height_unit'] === 'in') ? 'selected' : ''; ?>>Inches (in)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="distance_unit" class="form-label">Distance Unit</label>
                <select class="form-select" id="distance_unit" name="distance_unit">
                    <option value="km" <?php echo ($preferences['distance_unit'] === 'km') ? 'selected' : ''; ?>>Kilometers (km)</option>
                    <option value="mi" <?php echo ($preferences['distance_unit'] === 'mi') ? 'selected' : ''; ?>>Miles (mi)</option>
                </select>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="theme" class="form-label">Theme</label>
                <select class="form-select" id="theme" name="theme">
                    <option value="light" <?php echo ($preferences['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo ($preferences['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="notification_preference" name="notification_preference" <?php echo ($preferences['notification_enabled'] === 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notification_preference">Enable Notifications</label>
                </div>
            </div>
        </div>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <button type="submit" name="update_preferences" class="btn btn-primary">
                <i class="fas fa-cog me-2"></i>Save Preferences
            </button>
        </div>
    </form>
</div>
</div>
</div>
</div>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="avatarModalLabel">Update Profile Picture</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <form action="" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="avatar" class="form-label">Select Image</label>
                <input class="form-control" type="file" id="avatar" name="avatar" accept="image/*" required>
                <div class="form-text">JPG, JPEG, PNG, or GIF. Max file size: 2MB.</div>
            </div>
            <div class="d-grid">
                <button type="submit" name="upload_avatar" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i>Upload
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<!-- Bootstrap JS and Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get current tab from URL or localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const savedTab = localStorage.getItem('activeProfileTab');
    
    // Set active tab based on URL parameter or saved preference
    if (tabParam) {
        activateTab(tabParam);
    } else if (savedTab) {
        activateTab(savedTab);
    }
    
    // Add event listener for tab changes
    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            const id = event.target.id.replace('-tab', '');
            localStorage.setItem('activeProfileTab', id);
        });
    });
    
    function activateTab(tabId) {
        const tabElement = document.getElementById(tabId + '-tab');
        if (tabElement) {
            const tab = new bootstrap.Tab(tabElement);
            tab.show();
        }
    }
});
</script>
</body>
</html>