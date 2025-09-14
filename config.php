<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fitness_tracker"; // Make sure this matches what you created

// Create MySQL Connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>