<?php
/**
 * Database connection manager for MaakLab API
 * 
 * This file establishes connection to the MaakLab database and provides
 * helper functions for connection status checking.
 */

// Display errors during development (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database configuration
$host = "ID462020_maaklab.db.webhosting.be";
$dbname = "ID462020_badgesysteem";
$username = "ID462020_badgesysteem";
$password = "kS8M607q97p82Gs079Ck";
$port = 3306; // Standaard MySQL poort

// Connection variables
$conn = null;
$dbConnected = false;
$dbErrorMessage = "";

try {
    // Set connection timeout to 5 seconds to prevent long hangs
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];

    // Create a PDO connection with explicit port
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password, $options);
    
    // Test the connection with a simple query
    $testQuery = $conn->query("SELECT 1");
    
    $dbConnected = true;
    error_log("[API] Database connection successful");
    
} catch (PDOException $e) {
    $errorCode = $e->getCode();
    
    // Extract more meaningful error message based on error code
    switch ($errorCode) {
        case 1044: // Access denied error
            $dbErrorMessage = "Database toegang geweigerd. Controleer gebruikersnaam en wachtwoord.";
            break;
        case 1045: // Wrong username/password
            $dbErrorMessage = "Ongeldige database inloggegevens.";
            break;
        case 1049: // Unknown database
            $dbErrorMessage = "Database '$dbname' bestaat niet.";
            break;
        case 2002: // Server not found/connection refused
            $dbErrorMessage = "Kan geen verbinding maken met database server. Controleer host en poort.";
            break;
        default:
            $dbErrorMessage = "Database verbindingsfout: " . $e->getMessage();
    }
    
    error_log("[API] Database connection error: " . $e->getMessage());
    
    // For API requests, if this is being directly accessed, return JSON error
    if (strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
        // Only return JSON error if not being included by another script
        if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $dbErrorMessage]);
            exit;
        }
    }
}

/**
 * Check if database connection is established
 * @return bool True if connected, false otherwise
 */
function isDatabaseConnected() {
    global $dbConnected;
    return $dbConnected;
}

/**
 * Get database error message if any
 * @return string Error message
 */
function getDatabaseError() {
    global $dbErrorMessage;
    return $dbErrorMessage;
}

/**
 * Get the database connection object
 * @return PDO|null PDO connection object or null if not connected
 */
function getDatabaseConnection() {
    global $conn;
    return $conn;
}
?>