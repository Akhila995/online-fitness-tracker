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
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Handle form submissions based on action
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$message = '';
$messageType = '';

// Check if the description column exists
$checkColumnQuery = "SHOW COLUMNS FROM fitness_goals LIKE 'description'";
$columnExists = $conn->query($checkColumnQuery)->num_rows > 0;

// Add description column if it doesn't exist
if (!$columnExists) {
    $alterTableQuery = "ALTER TABLE fitness_goals ADD COLUMN description TEXT";
    $conn->query($alterTableQuery);
}

// Check if the completed_at column exists
$checkCompletedAtColumnQuery = "SHOW COLUMNS FROM fitness_goals LIKE 'completed_at'";
$completedAtColumnExists = $conn->query($checkCompletedAtColumnQuery)->num_rows > 0;

// Add completed_at column if it doesn't exist
if (!$completedAtColumnExists) {
    $alterTableQuery = "ALTER TABLE fitness_goals ADD COLUMN completed_at DATETIME NULL";
    $conn->query($alterTableQuery);
}

// Add Goal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $goal_type = $_POST['goal_type'];
    $target_value = $_POST['target_value'];
    $current_value = isset($_POST['current_value']) ? $_POST['current_value'] : 0;
    $deadline = $_POST['deadline'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    
    // Get the column names from the fitness_goals table
    $tableInfoQuery = "SHOW COLUMNS FROM fitness_goals";
    $tableInfoResult = $conn->query($tableInfoQuery);
    $columns = [];
    while ($columnInfo = $tableInfoResult->fetch_assoc()) {
        $columns[] = $columnInfo['Field'];
    }
    
    // Build the query based on existing columns
    $fields = "user_id, goal_type, target_value, current_value, deadline, status, created_at";
    $values = "?, ?, ?, ?, ?, 'In Progress', NOW()";
    $types = "iddds";
    $params = array($user_id, $goal_type, $target_value, $current_value, $deadline);
    
    // Add description only if the column exists
    if (in_array('description', $columns)) {
        $fields .= ", description";
        $values .= ", ?";
        $types .= "s";
        $params[] = $description;
    }
    
    $sql = "INSERT INTO fitness_goals ($fields) VALUES ($values)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $message = "Goal added successfully!";
        $messageType = "success";
        $action = 'view'; // Redirect to view all
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "danger";
    }
    $stmt->close();
}

// Update Goal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'update') {
    $goal_id = $_POST['goal_id'];
    $goal_type = $_POST['goal_type'];
    $target_value = $_POST['target_value'];
    $current_value = $_POST['current_value'];
    $deadline = $_POST['deadline'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $status = $_POST['status'];
    
    // Get the column names from the fitness_goals table
    $tableInfoQuery = "SHOW COLUMNS FROM fitness_goals";
    $tableInfoResult = $conn->query($tableInfoQuery);
    $columns = [];
    while ($columnInfo = $tableInfoResult->fetch_assoc()) {
        $columns[] = $columnInfo['Field'];
    }
    
    // Build the update SQL based on existing columns
    $updateFields = "goal_type = ?, target_value = ?, current_value = ?, deadline = ?, status = ?";
    $types = "sddss";
    $params = array($goal_type, $target_value, $current_value, $deadline, $status);
    
    // Add description only if the column exists
    if (in_array('description', $columns)) {
        $updateFields .= ", description = ?";
        $types .= "s";
        $params[] = $description;
    }
    
    // If status is changed to Completed, update the completed_at date
    if ($status == 'Completed') {
        $completed_at = date('Y-m-d H:i:s');
        $updateFields .= ", completed_at = ?";
        $types .= "s";
        $params[] = $completed_at;
    } else {
        $updateFields .= ", completed_at = NULL";
    }
    
    // Add the WHERE clause parameters
    $types .= "ii";
    $params[] = $goal_id;
    $params[] = $user_id;
    
    $sql = "UPDATE fitness_goals SET $updateFields WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $message = "Goal updated successfully!";
        $messageType = "success";
        $action = 'view'; // Redirect to view all after update
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "danger";
    }
    $stmt->close();
}

// Delete Goal
if ($action == 'delete' && isset($_GET['id'])) {
    $goal_id = $_GET['id'];
    
    $sql = "DELETE FROM fitness_goals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $goal_id, $user_id);
    
    if ($stmt->execute()) {
        $message = "Goal deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "danger";
    }
    $stmt->close();
    $action = 'view'; // Go back to view all
}

// Fetch single goal for edit/view
$goal = null;
if (($action == 'edit' || $action == 'view_single') && isset($_GET['id'])) {
    $goal_id = $_GET['id'];
    
    $sql = "SELECT * FROM fitness_goals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $goal_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $goal = $result->fetch_assoc();
    } else {
        $message = "Goal not found!";
        $messageType = "danger";
        $action = 'view';
    }
    $stmt->close();
}

// Get all goals
function getAllGoals($conn, $user_id, $sort = 'deadline', $order = 'ASC') {
    $valid_sorts = ['deadline', 'created_at', 'goal_type', 'status'];
    $valid_orders = ['ASC', 'DESC'];
    
    // Validate sort and order parameters
    if (!in_array($sort, $valid_sorts)) $sort = 'deadline';
    if (!in_array($order, $valid_orders)) $order = 'ASC';
    
    $sql = "SELECT * FROM fitness_goals WHERE user_id = ? ORDER BY $sort $order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $goals = [];
    while ($row = $result->fetch_assoc()) {
        $goals[] = $row;
    }
    $stmt->close();
    return $goals;
}

// Calculate goal stats
function calculateGoalStats($goals) {
    $stats = [
        'total' => count($goals),
        'completed' => 0,
        'in_progress' => 0,
        'overdue' => 0,
        'abandoned' => 0
    ];
    
    foreach ($goals as $goal) {
        if ($goal['status'] == 'Completed') {
            $stats['completed']++;
        } elseif ($goal['status'] == 'In Progress') {
            $stats['in_progress']++;
            
            // Check if overdue
            $deadline = new DateTime($goal['deadline']);
            $today = new DateTime();
            if ($today > $deadline) {
                $stats['overdue']++;
            }
        } elseif ($goal['status'] == 'Abandoned') {
            $stats['abandoned']++;
        }
    }
    
    // Calculate completion rate
    $stats['completion_rate'] = ($stats['total'] > 0) ? 
        round(($stats['completed'] / $stats['total']) * 100) : 0;
    
    return $stats;
}

// Get sort parameters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'deadline';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Get all goals for view all
$goals = getAllGoals($conn, $user_id, $sort, $order);
$goalStats = calculateGoalStats($goals);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Goals | Fitness Tracker</title>
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
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            border-radius: 10px 10px 0 0;
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        }
        .goal-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .goal-item:last-child {
            border-bottom: none;
        }
        .progress-container {
            height: 8px;
            margin: 8px 0;
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            border-radius: 5px;
        }
        .tab-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .sort-link {
            color: #212529;
            text-decoration: none;
        }
        .sort-link:hover {
            color: #5768f3;
        }
        .sort-active {
            color: #5768f3;
            font-weight: bold;
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
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .total-goals { color: #5768f3; }
        .completed-goals { color: #28a745; }
        .in-progress-goals { color: #ffc107; }
        .overdue-goals { color: #dc3545; }
        .abandoned-goals { color: #6c757d; }
        .filter-dropdown {
            width: 130px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand">Fitness Tracker</a>
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
                            <li><a class="dropdown-item" href="profile.php" data-bs-toggle="modal" data-bs-target="#profileModal">Profile</a></li>
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
        
        <!-- Page header and action buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Fitness Goals</h1>
            <div>
                <?php if ($action != 'add'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Goal
                    </a>
                <?php else: ?>
                    <a href="?action=view" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Goals
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($action == 'view'): ?>
            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md col-6">
                    <div class="dashboard-card stat-card">
                        <div class="stat-value total-goals"><?php echo $goalStats['total']; ?></div>
                        <div class="stat-label">Total Goals</div>
                    </div>
                </div>
                <div class="col-md col-6">
                    <div class="dashboard-card stat-card">
                        <div class="stat-value completed-goals"><?php echo $goalStats['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md col-6">
                    <div class="dashboard-card stat-card">
                        <div class="stat-value in-progress-goals"><?php echo $goalStats['in_progress']; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                </div>
                <div class="col-md col-6">
                    <div class="dashboard-card stat-card">
                        <div class="stat-value overdue-goals"><?php echo $goalStats['overdue']; ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>
                <div class="col-md col-6">
                    <div class="dashboard-card stat-card">
                        <div class="stat-value abandoned-goals"><?php echo $goalStats['abandoned']; ?></div>
                        <div class="stat-label">Abandoned</div>
                    </div>
                </div>
            </div>
            
            <!-- Goals Table Card -->
            <div class="dashboard-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Goals</h5>
                    <div class="d-flex align-items-center">
                        <div class="dropdown me-2">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle filter-dropdown" type="button" id="statusFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Status Filter
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="statusFilterDropdown">
                                <li><a class="dropdown-item" href="#" data-filter="all">All Goals</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="in-progress">In Progress</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="completed">Completed</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="overdue">Overdue</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="abandoned">Abandoned</a></li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle filter-dropdown" type="button" id="goalTypeFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Goal Type
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="goalTypeFilterDropdown">
                                <li><a class="dropdown-item" href="#" data-type-filter="all">All Types</a></li>
                                <li><a class="dropdown-item" href="#" data-type-filter="steps">Steps</a></li>
                                <li><a class="dropdown-item" href="#" data-type-filter="weight">Weight</a></li>
                                <li><a class="dropdown-item" href="#" data-type-filter="calories">Calories</a></li>
                                <li><a class="dropdown-item" href="#" data-type-filter="water">Water</a></li>
                                <li><a class="dropdown-item" href="#" data-type-filter="exercise">Exercise</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($goals)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted">You haven't set any goals yet.</p>
                            <a href="?action=add" class="btn btn-primary">Create Your First Goal</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>
                                            <a href="?sort=goal_type&order=<?php echo ($sort == 'goal_type' && $order == 'ASC') ? 'DESC' : 'ASC'; ?>" class="sort-link <?php echo ($sort == 'goal_type') ? 'sort-active' : ''; ?>">
                                                Goal Type
                                                <?php if ($sort == 'goal_type'): ?>
                                                    <i class="fas fa-sort-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Target</th>
                                        <th>Current</th>
                                        <th>Progress</th>
                                        <th>
                                            <a href="?sort=status&order=<?php echo ($sort == 'status' && $order == 'ASC') ? 'DESC' : 'ASC'; ?>" class="sort-link <?php echo ($sort == 'status') ? 'sort-active' : ''; ?>">
                                                Status
                                                <?php if ($sort == 'status'): ?>
                                                    <i class="fas fa-sort-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort=deadline&order=<?php echo ($sort == 'deadline' && $order == 'ASC') ? 'DESC' : 'ASC'; ?>" class="sort-link <?php echo ($sort == 'deadline') ? 'sort-active' : ''; ?>">
                                                Deadline
                                                <?php if ($sort == 'deadline'): ?>
                                                    <i class="fas fa-sort-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($goals as $g): 
                                        // Calculate progress percentage
                                        $progress = ($g['target_value'] > 0) ? 
                                            min(100, max(0, ($g['current_value'] / $g['target_value']) * 100)) : 0;
                                        
                                        // Calculate days remaining or overdue
                                        $deadline = new DateTime($g['deadline']);
                                        $today = new DateTime();
                                        $days_remaining = $today->diff($deadline)->days;
                                        $is_overdue = $today > $deadline && $g['status'] == 'In Progress';
                                        
                                        // Format goal type for display
                                        $goal_type_display = ucfirst($g['goal_type']);
                                        
                                        // Set CSS classes for filtering
                                        $filter_classes = 'goal-row ' . strtolower($g['goal_type']);
                                        if ($g['status'] == 'Completed') {
                                            $filter_classes .= ' completed';
                                        } elseif ($g['status'] == 'Abandoned') {
                                            $filter_classes .= ' abandoned';
                                        } elseif ($is_overdue) {
                                            $filter_classes .= ' overdue in-progress';
                                        } else {
                                            $filter_classes .= ' in-progress';
                                        }
                                    ?>
                                        <tr class="<?php echo $filter_classes; ?>">
                                            <td><?php echo htmlspecialchars($goal_type_display); ?></td>
                                            <td><?php echo htmlspecialchars($g['target_value']); ?></td>
                                            <td><?php echo htmlspecialchars($g['current_value']); ?></td>
                                            <td>
                                                <div class="progress-container">
                                                    <div class="progress-bar <?php echo $is_overdue ? 'bg-danger' : 'bg-primary'; ?>" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <small><?php echo round($progress); ?>%</small>
                                            </td>
                                            <td>
                                                <span class="badge <?php 
                                                    if ($g['status'] == 'Completed') echo 'bg-success';
                                                    elseif ($g['status'] == 'Abandoned') echo 'bg-secondary';
                                                    elseif ($is_overdue) echo 'bg-danger';
                                                    else echo 'bg-warning';
                                                ?>">
                                                    <?php echo $is_overdue && $g['status'] == 'In Progress' ? 'Overdue' : htmlspecialchars($g['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($g['deadline'])); ?>
                                                <?php if ($g['status'] == 'In Progress'): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $is_overdue ? 'Overdue by ' . $days_remaining . ' days' : $days_remaining . ' days left'; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?action=view_single&id=<?php echo $g['id']; ?>" class="btn btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?action=edit&id=<?php echo $g['id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" data-id="<?php echo $g['id']; ?>" class="btn btn-outline-danger delete-goal" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($action == 'add'): ?>
            <!-- Add Goal Form -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">Add New Goal</h5>
                </div>
                <div class="card-body">
                    <form action="?action=add" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="goalType" class="form-label">Goal Type</label>
                                <select class="form-select" id="goalType" name="goal_type" required>
                                    <option value="" selected disabled>Select a goal type</option>
                                    <option value="steps">Steps</option>
                                    <option value="weight">Weight</option>
                                    <option value="calories">Calories</option>
                                    <option value="water">Water</option>
                                    <option value="exercise">Exercise</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="deadline" class="form-label">Deadline</label>
                                <input type="date" class="form-control" id="deadline" name="deadline" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="targetValue" class="form-label">Target Value</label>
                                <input type="number" class="form-control" id="targetValue" name="target_value" required step="0.01">
                                <div class="form-text">For steps, calories, etc.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currentValue" class="form-label">Current Value (Optional)</label>
                                <input type="number" class="form-control" id="currentValue" name="current_value" step="0.01" value="0">
                                <div class="form-text">Your starting point (defaults to 0)</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Add details about your goal..."></textarea>
                            <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Add details about your goal..."></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="?action=view" class="btn btn-outline-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Goal</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($action == 'edit'): ?>
            <!-- Edit Goal Form -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Goal</h5>
                </div>
                <div class="card-body">
                    <?php if ($goal): ?>
                        <form action="?action=update" method="post">
                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="goalType" class="form-label">Goal Type</label>
                                    <select class="form-select" id="goalType" name="goal_type" required>
                                        <option value="steps" <?php echo ($goal['goal_type'] == 'steps') ? 'selected' : ''; ?>>Steps</option>
                                        <option value="weight" <?php echo ($goal['goal_type'] == 'weight') ? 'selected' : ''; ?>>Weight</option>
                                        <option value="calories" <?php echo ($goal['goal_type'] == 'calories') ? 'selected' : ''; ?>>Calories</option>
                                        <option value="water" <?php echo ($goal['goal_type'] == 'water') ? 'selected' : ''; ?>>Water</option>
                                        <option value="exercise" <?php echo ($goal['goal_type'] == 'exercise') ? 'selected' : ''; ?>>Exercise</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="deadline" class="form-label">Deadline</label>
                                    <input type="date" class="form-control" id="deadline" name="deadline" required value="<?php echo date('Y-m-d', strtotime($goal['deadline'])); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="targetValue" class="form-label">Target Value</label>
                                    <input type="number" class="form-control" id="targetValue" name="target_value" required step="0.01" value="<?php echo $goal['target_value']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="currentValue" class="form-label">Current Value</label>
                                    <input type="number" class="form-control" id="currentValue" name="current_value" step="0.01" value="<?php echo $goal['current_value']; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($goal['description']) ? htmlspecialchars($goal['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="In Progress" <?php echo ($goal['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo ($goal['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Abandoned" <?php echo ($goal['status'] == 'Abandoned') ? 'selected' : ''; ?>>Abandoned</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="?action=view" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Goal</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($action == 'view_single'): ?>
            <!-- View Single Goal -->
            <?php if ($goal): ?>
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo ucfirst($goal['goal_type']); ?> Goal Details</h5>
                        <div>
                            <a href="?action=edit&id=<?php echo $goal['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <a href="#" data-id="<?php echo $goal['id']; ?>" class="btn btn-sm btn-outline-danger ms-2 delete-goal" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">
                                <i class="fas fa-trash me-1"></i>Delete
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Target Value</h6>
                                <p class="lead"><?php echo $goal['target_value']; ?></p>
                                
                                <h6>Current Value</h6>
                                <p class="lead"><?php echo $goal['current_value']; ?></p>
                                
                                <h6>Status</h6>
                                <p>
                                    <span class="badge <?php 
                                        if ($goal['status'] == 'Completed') echo 'bg-success';
                                        elseif ($goal['status'] == 'Abandoned') echo 'bg-secondary';
                                        else {
                                            $deadline = new DateTime($goal['deadline']);
                                            $today = new DateTime();
                                            echo ($today > $deadline) ? 'bg-danger' : 'bg-warning';
                                        }
                                    ?>">
                                        <?php 
                                            if ($goal['status'] == 'In Progress') {
                                                $deadline = new DateTime($goal['deadline']);
                                                $today = new DateTime();
                                                echo ($today > $deadline) ? 'Overdue' : 'In Progress';
                                            } else {
                                                echo $goal['status']; 
                                            }
                                        ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>Created</h6>
                                <p><?php echo date('F j, Y', strtotime($goal['created_at'])); ?></p>
                                
                                <h6>Deadline</h6>
                                <p><?php echo date('F j, Y', strtotime($goal['deadline'])); ?></p>
                                
                                <?php if ($goal['status'] == 'Completed' && isset($goal['completed_at'])): ?>
                                    <h6>Completed On</h6>
                                    <p><?php echo date('F j, Y', strtotime($goal['completed_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php
                        // Calculate progress percentage
                        $progress = ($goal['target_value'] > 0) ? 
                            min(100, max(0, ($goal['current_value'] / $goal['target_value']) * 100)) : 0;
                        ?>
                        
                        <h6>Progress</h6>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%;" 
                                 aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($progress); ?>%
                            </div>
                        </div>
                        
                        <?php if (isset($goal['description']) && !empty($goal['description'])): ?>
                            <h6>Description</h6>
                            <p><?php echo nl2br(htmlspecialchars($goal['description'])); ?></p>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="?action=view" class="btn btn-outline-secondary">Back to Goals</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this goal? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                        <h4 class="mt-3"><?php echo htmlspecialchars($username); ?></h4>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6>Goal Completion Rate</h6>
                            <p class="lead"><?php echo $goalStats['completion_rate']; ?>%</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Active Goals</h6>
                            <p class="lead"><?php echo $goalStats['in_progress']; ?></p>
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

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        // Set up delete confirmation
        document.addEventListener('DOMContentLoaded', function() {
            // Handle delete confirmation
            const deleteLinks = document.querySelectorAll('.delete-goal');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const goalId = this.getAttribute('data-id');
                    const confirmDeleteLink = document.getElementById('confirmDelete');
                    confirmDeleteLink.href = `?action=delete&id=${goalId}`;
                });
            });
            
            // Status filtering
            const statusFilters = document.querySelectorAll('[data-filter]');
            statusFilters.forEach(filter => {
                filter.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filterValue = this.getAttribute('data-filter');
                    const goalRows = document.querySelectorAll('.goal-row');
                    
                    goalRows.forEach(row => {
                        if (filterValue === 'all') {
                            row.style.display = '';
                        } else {
                            row.style.display = row.classList.contains(filterValue) ? '' : 'none';
                        }
                    });
                    
                    // Update dropdown button text
                    const dropdownButton = document.getElementById('statusFilterDropdown');
                    dropdownButton.innerText = this.innerText;
                });
            });
            
            // Goal type filtering
            const typeFilters = document.querySelectorAll('[data-type-filter]');
            typeFilters.forEach(filter => {
                filter.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filterValue = this.getAttribute('data-type-filter');
                    const goalRows = document.querySelectorAll('.goal-row');
                    
                    goalRows.forEach(row => {
                        if (filterValue === 'all') {
                            row.style.display = '';
                        } else {
                            row.style.display = row.classList.contains(filterValue) ? '' : 'none';
                        }
                    });
                    
                    // Update dropdown button text
                    const dropdownButton = document.getElementById('goalTypeFilterDropdown');
                    dropdownButton.innerText = this.innerText;
                });
            });
        });
    </script>
</body>
</html>
