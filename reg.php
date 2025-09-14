<?php
// Core configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "fitness_tracker");
if ($conn->connect_error) {
    die("<script>alert('Database Connection Failed: " . addslashes($conn->connect_error) . "');</script>");
}

// Initialize variables
$error = "";
$verificationStarted = isset($_SESSION['verification_started']) && $_SESSION['verification_started'];
$verificationCode = ""; // To display verification code since we won't email it

// Helper functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Process initial registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST["verificationCode"])) {
    $username = trim($_POST["regUsername"]);
    $email = trim($_POST["regEmail"]);
    $password = trim($_POST["regPassword"]);

    // Validate input
    if (strlen($username) < 5 || preg_match('/[^a-zA-Z0-9]/', $username)) {
        $error = "Username must be at least 5 characters long and contain only letters and numbers.";
    } elseif (!validateEmail($email)) {
        $error = "Invalid email address. Please provide a valid email.";
    } elseif (strlen($password) < 8 || !preg_match('/[!@#$%^&*]/', $password)) {
        $error = "Password must be at least 8 characters with at least one special character.";
    } else {
        // Check for existing username/email
        $stmt = $conn->prepare("SELECT username FROM registration WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Username or email already registered.";
        } else {
            // Generate verification code and store user data
            $verificationCode = substr(str_shuffle("0123456789"), 0, 6);
            $_SESSION['temp_user'] = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'verification_code' => $verificationCode
            ];
            
            // Instead of sending email, just show the verification code in the interface
            $_SESSION['verification_started'] = true;
            $verificationStarted = true;
        }
        $stmt->close();
    }
}

// Process verification code submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["verificationCode"])) {
    $submittedCode = trim($_POST["verificationCode"]);
    
    if (isset($_SESSION['temp_user']) && $_SESSION['temp_user']['verification_code'] === $submittedCode) {
        // Insert verified user into database
        $username = $_SESSION['temp_user']['username'];
        $email = $_SESSION['temp_user']['email'];
        $hashedPassword = password_hash($_SESSION['temp_user']['password'], PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO registration (username, email, password, is_verified) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $username, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            // Clean up session and redirect
            unset($_SESSION['temp_user']);
            unset($_SESSION['verification_started']);
            echo "<script>alert('Registration successful! Your account has been created.'); window.location.href='signin.php';</script>";
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Pro - Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-image: url('r1.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
        }
        .overlay {
            background: rgba(0, 0, 0, 0.7);
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
        }
        .container {
            position: relative;
            z-index: 2;
            margin-top: 50px;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        h2 {
            font-weight: bold;
            color: #333;
        }
        .btn-primary {
            background: linear-gradient(45deg, #ff7300, #ff3e00);
            border: none;
            font-size: 18px;
            padding: 10px;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #e06200, #d13b00);
        }
        .form-label { font-weight: bold; color: #555; }
        .form-control {
            border: 2px solid #ff7300;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-control:focus {
            border-color: #ff3e00;
            box-shadow: 0 0 5px #ff7300;
        }
        .hint { font-size: 12px; color: #777; text-align: left; margin-top: 5px; }
        .verification-note {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #495057;
            text-align: left;
        }
        .verification-input {
            font-size: 24px;
            letter-spacing: 10px;
            text-align: center;
        }
        .text-link {
            color: #ff7300 !important;
            font-weight: bold;
        }
        .password-strength-meter {
            height: 10px;
            background-color: #ddd;
            margin-top: 5px;
            border-radius: 5px;
        }
        .password-strength-meter div {
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s;
        }
        .password-container { position: relative; }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: #666;
        }
        .verification-code-display {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #333;
        }
    </style>
</head>
<body>
<div class="overlay"></div>
<div class="container">
    <h2>Register for Fitness Pro</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($verificationStarted): ?>
        <!-- Verification Form -->
        <div class="verification-note">
            <p><strong>Account Verification</strong></p>
            <p>Normally, we would send a verification code to your email. Since email is not available, please use the code below:</p>
        </div>
        
        <div class="verification-code-display">
            <?php echo htmlspecialchars($_SESSION['temp_user']['verification_code']); ?>
        </div>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="verificationCode" class="form-label">Verification Code</label>
                <input type="text" class="form-control verification-input" id="verificationCode" name="verificationCode" maxlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify & Complete Registration</button>
        </form>
    <?php else: ?>
        <!-- Initial Registration Form -->
        <form method="POST" action="" id="registrationForm">
            <div class="mb-3 text-start">
                <label for="regUsername" class="form-label">Username</label>
                <input type="text" class="form-control" id="regUsername" name="regUsername" required>
                <div class="hint">5+ characters, letters and numbers only</div>
            </div>
            
            <div class="mb-3 text-start">
                <label for="regEmail" class="form-label">Email</label>
                <input type="email" class="form-control" id="regEmail" name="regEmail" required>
                <div class="hint">For account identification only (emails are disabled)</div>
            </div>

            <div class="mb-3 text-start">
                <label for="regPassword" class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" id="regPassword" name="regPassword" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility()">👁️</span>
                </div>
                <div class="password-strength-meter">
                    <div id="strength-meter" style="width: 0%; background-color: #dc3545;"></div>
                </div>
                <div id="password-strength" class="hint" style="color: #dc3545;">Password strength: Too weak</div>
                <div class="hint">8+ characters with at least one special character (!@#$%^&*)</div>
            </div>

            <div class="mb-3 text-start">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirmPassword" required>
                <div id="password-match" class="hint"></div>
            </div>

            <div class="mb-3 form-check text-start">
                <input type="checkbox" class="form-check-input" id="termsCheck" required>
                <label class="form-check-label" for="termsCheck" style="color: #555;">I agree to the <a href="#" class="text-link">Terms & Conditions</a></label>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="registerBtn">Register</button>
        </form>
    <?php endif; ?>
    
    <p style="margin-top: 20px; color: #555;">Already have an account? <a href="signin.php" class="text-link">Sign In</a></p>
</div>

<script>
    // Password strength meter
    document.getElementById('regPassword').addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let width = 0;
        let color = "#dc3545"; 
        let text = "Too weak";
        
        if (password.length >= 8) strength += 1;
        if (password.match(/[A-Z]/)) strength += 1;
        if (password.match(/[0-9]/)) strength += 1;
        if (password.match(/[!@#$%^&*]/)) strength += 1;
        
        switch(strength) {
            case 0: width = 10; color = "#dc3545"; text = "Too weak"; break;
            case 1: width = 25; color = "#dc3545"; text = "Weak"; break;
            case 2: width = 50; color = "#ffc107"; text = "Medium"; break;
            case 3: width = 75; color = "#20c997"; text = "Strong"; break;
            case 4: width = 100; color = "#198754"; text = "Very strong"; break;
        }
        
        document.getElementById('strength-meter').style.width = width + "%";
        document.getElementById('strength-meter').style.backgroundColor = color;
        document.getElementById('password-strength').textContent = "Password strength: " + text;
        document.getElementById('password-strength').style.color = color;
    });
    
    // Check password match
    document.getElementById('confirmPassword').addEventListener('input', function() {
        const password = document.getElementById('regPassword').value;
        const confirmPassword = this.value;
        const matchElement = document.getElementById('password-match');
        
        if (confirmPassword === '') {
            matchElement.textContent = '';
        } else if (password === confirmPassword) {
            matchElement.textContent = 'Passwords match ✓';
            matchElement.style.color = '#198754';
        } else {
            matchElement.textContent = 'Passwords do not match ✗';
            matchElement.style.color = '#dc3545';
        }
    });
    
    // Form validation
    document.getElementById('registrationForm')?.addEventListener('submit', function(event) {
        const password = document.getElementById('regPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (password !== confirmPassword) {
            event.preventDefault();
            alert('Passwords do not match! Please check and try again.');
        }
    });
    
    // Toggle password visibility
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('regPassword');
        const toggleIcon = document.querySelector('.toggle-password');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.textContent = '👁️‍🗨️';
        } else {
            passwordInput.type = 'password';
            toggleIcon.textContent = '👁️';
        }
    }
</script>
</body>
</html>