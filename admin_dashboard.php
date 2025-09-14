<?php
// Start with error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session before any output
session_start();

// Check if the admin is logged in
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    // Redirect to admin login page if not logged in
    header("Location: admin.php");
    exit();
}

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

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return ($result && $result->num_rows > 0);
}

// Function to check if a column exists in a table
function columnExists($conn, $tableName, $columnName) {
    $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return ($result && $result->num_rows > 0);
}

// Create registration table if it doesn't exist
if (!tableExists($conn, 'registration')) {
    $create_registration = "CREATE TABLE `registration` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(50) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    )";
    $conn->query($create_registration);
}

// Add created_at column if it doesn't exist
if (tableExists($conn, 'registration') && !columnExists($conn, 'registration', 'created_at')) {
    $add_column = "ALTER TABLE `registration` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP";
    $conn->query($add_column);
}

// Create users table if it doesn't exist
if (!tableExists($conn, 'users')) {
    $create_users = "CREATE TABLE `users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(50) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    )";
    $conn->query($create_users);
}

// Create programs table if it doesn't exist
if (!tableExists($conn, 'programs')) {
    $create_programs = "CREATE TABLE `programs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `status` ENUM('active', 'inactive', 'draft') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    )";
    $conn->query($create_programs);
}

// Create workouts table if it doesn't exist
if (!tableExists($conn, 'workouts')) {
    $create_workouts = "CREATE TABLE `workouts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `title` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `duration` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    )";
    $conn->query($create_workouts);
}

// Get total number of registered users
$total_users = 0;
if (tableExists($conn, 'registration')) {
    $user_count_query = "SELECT COUNT(*) as total_users FROM registration";
    $user_count_result = $conn->query($user_count_query);
    if ($user_count_result && $user_count_result->num_rows > 0) {
        $row = $user_count_result->fetch_assoc();
        $total_users = $row["total_users"];
    }
}

// Get number of users registered this week
$new_users = 0;
if (tableExists($conn, 'registration') && columnExists($conn, 'registration', 'created_at')) {
    $new_users_query = "SELECT COUNT(*) as new_users FROM registration WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $new_users_result = $conn->query($new_users_query);
    if ($new_users_result && $new_users_result->num_rows > 0) {
        $row = $new_users_result->fetch_assoc();
        $new_users = $row["new_users"];
    }
}

// Get active programs
$active_programs = 0;
if (tableExists($conn, 'programs') && columnExists($conn, 'programs', 'status')) {
    $programs_query = "SELECT COUNT(*) as active_programs FROM programs WHERE status = 'active'";
    $programs_result = $conn->query($programs_query);
    if ($programs_result && $programs_result->num_rows > 0) {
        $row = $programs_result->fetch_assoc();
        $active_programs = $row["active_programs"];
    }
}

// Get total workouts
$total_workouts = 0;
if (tableExists($conn, 'workouts')) {
    $workouts_query = "SELECT COUNT(*) as total_workouts FROM workouts";
    $workouts_result = $conn->query($workouts_query);
    if ($workouts_result && $workouts_result->num_rows > 0) {
        $row = $workouts_result->fetch_assoc();
        $total_workouts = $row["total_workouts"];
    }
}

// Get list of users
$users_result = null;
if (tableExists($conn, 'registration')) {
    $fields = ["id", "username", "email"];
    if (columnExists($conn, 'registration', 'created_at')) {
        $fields[] = "created_at";
    }
    
    $fields_str = implode(", ", $fields);
    $users_query = "SELECT $fields_str FROM registration ORDER BY id DESC LIMIT 10";
    $users_result = $conn->query($users_query);
}

// Handle user deletion if requested
$success_message = "";
$error_message = "";

if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user']) && tableExists($conn, 'registration')) {
    $user_id = (int)$_GET['delete_user'];
    $delete_stmt = $conn->prepare("DELETE FROM registration WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "User deleted successfully!";
        // Redirect to refresh the page and avoid resubmission
        header("Location: admin_dashboard.php?success=deleted");
        exit();
    } else {
        $error_message = "Error deleting user: " . $conn->error;
    }
    $delete_stmt->close();
}

if (isset($_GET['success']) && $_GET['success'] == 'deleted') {
    $success_message = "User deleted successfully!";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Fitness Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f8fafc;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #341F97;
            color: white;
            padding-top: 20px;
        }

        .sidebar-brand {
            padding: 10px 20px;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            padding: 10px 20px;
            margin-bottom: 5px;
            border-left: 3px solid transparent;
        }

        .sidebar-menu li.active {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .card-dashboard {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            border-left: 4px solid #341F97;
        }

        .card-dashboard h3 {
            margin-top: 0;
            font-size: 18px;
            color: #444;
        }

        .card-dashboard p {
            font-size: 24px;
            font-weight: bold;
            color: #341F97;
            margin: 10px 0 0;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 5px;
            font-size: 14px;
        }

        .btn-view {
            background: #3498db;
            color: white;
        }

        .btn-edit {
            background: #2ecc71;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.9;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-brand {
                padding: 10px;
                font-size: 18px;
            }
            .sidebar-menu span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-dumbbell"></i> Fitness Pro
    </div>
    <ul class="sidebar-menu">
        <li class="active">
            <a href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-calendar-alt"></i>
                <span>Programs</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <li>
            <a href="admin_logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <h1>Admin Dashboard</h1>
        <div class="user-info">
            <img src="https://via.placeholder.com/40" alt="Admin">
            <div>
                <div><?php echo htmlspecialchars($_SESSION["admin_username"]); ?></div>
                <small>Administrator</small>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
        <div class="alert alert-success">User deleted successfully!</div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3">
            <div class="card-dashboard">
                <h3>Total Users</h3>
                <p><?php echo $total_users; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-dashboard">
                <h3>Active Programs</h3>
                <p><?php echo $active_programs; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-dashboard">
                <h3>New Users (This Week)</h3>
                <p><?php echo $new_users; ?></p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-dashboard">
                <h3>Total Workouts</h3>
                <p><?php echo $total_workouts; ?></p>
            </div>
        </div>
    </div>

    <div class="table-container mt-4">
        <h2>Recent Users</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Registration Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users_result && $users_result->num_rows > 0): ?>
                    <?php while($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user["id"]); ?></td>
                            <td><?php echo htmlspecialchars($user["username"]); ?></td>
                            <td><?php echo htmlspecialchars($user["email"]); ?></td>
                            <td><?php echo htmlspecialchars($user["created_at"]); ?></td>
                            <td>
                                <a href="#" class="action-btn btn-view"><i class="fas fa-eye"></i></a>
                                <a href="#" class="action-btn btn-edit"><i class="fas fa-edit"></i></a>
                                <a href="admin_dashboard.php?delete_user=<?php echo $user["id"]; ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this user?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>