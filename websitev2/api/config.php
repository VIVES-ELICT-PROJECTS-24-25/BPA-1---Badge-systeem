<?php
// Voorkom dubbele session_start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuratie
$host = "ID462020_badgesysteem.db.webhosting.be";
$dbname = "ID462020_badgesysteem";
$username = "ID462020_badgesysteem";
$password = "kS8M607q97p82Gs079Ck";

// Database verbinding maken
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database verbinding mislukt: " . $e->getMessage()]);
    exit();
}

// Helper functie voor API responses
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Authenticatie check
function authenticate() {
    if (!isset($_SESSION['User_ID'])) {
        sendResponse(["error" => "Authenticatie vereist"], 401);
    }
    return $_SESSION['User_ID'];
}

// Beheerder check
function ensureAdmin() {
    if (!isset($_SESSION['User_ID']) || !isset($_SESSION['Type']) || $_SESSION['Type'] != 'beheerder') {
        sendResponse(["error" => "Beheerder rechten vereist"], 403);
    }
    return $_SESSION['User_ID'];
}
?>