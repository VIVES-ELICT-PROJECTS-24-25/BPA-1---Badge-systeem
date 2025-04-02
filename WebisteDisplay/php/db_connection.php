<?php
// Database configuration
$host = "localhost";
$dbname = "ID462020_badgesysteem";
$username = "ID462020_badgesysteem";
$password = "kS8M607q97p82Gs079Ck";

// Connection status
$dbConnected = false;
$dbErrorMessage = "";

try {
    // Create a PDO connection to the database
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $dbConnected = true;
    
} catch (PDOException $e) {
    // If connection fails, store error message
    $dbErrorMessage = "Database Connection Failed: " . $e->getMessage();
}

// Function to check if database connection is established
function isDatabaseConnected() {
    global $dbConnected;
    return $dbConnected;
}

// Function to get database error message if any
function getDatabaseError() {
    global $dbErrorMessage;
    return $dbErrorMessage;
}
?>