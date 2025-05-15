<?php
/**
 * API endpoint: Get Reservation Details
 * 
 * Returns details for a specific reservation ID
 * Required parameters: 
 * - token (for authentication)
 * - reservation_id
 */

// Set JSON content type and allow CORS for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Define API security token
$expected_token = 'SUlhsg673GSbgsJYS6352jkdaLK';

// Check if token is provided and valid
$provided_token = isset($_GET['token']) ? $_GET['token'] : '';
if ($provided_token !== $expected_token) {
    echo json_encode(['error' => 'Ongeldige authenticatie']);
    exit;
}

// Check if reservation_id is provided
if (!isset($_GET['reservation_id']) || !is_numeric($_GET['reservation_id'])) {
    echo json_encode(['error' => 'Reservation ID ontbreekt of is ongeldig']);
    exit;
}

$reservation_id = intval($_GET['reservation_id']);

// Now that we're authenticated, include the database connection
require_once '../db_connection.php';  // Gebruik het juiste pad

// Check if connection was successful
if (!isDatabaseConnected()) {
    echo json_encode(['error' => getDatabaseError()]);
    exit;
}

try {
    // Get the reservation details
    $query = "
        SELECT r.*, p.Netwerkadres, p.Versie_Toestel 
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.Reservatie_ID = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$reservation_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode([
            'success' => false,
            'error' => 'Geen reservering gevonden met ID ' . $reservation_id
        ]);
        exit;
    }
    
    // Return success with the record
    echo json_encode([
        'success' => true,
        'data' => $record
    ]);
    
} catch (Exception $e) {
    // Report any errors that occurred during query execution
    echo json_encode([
        'error' => 'Database query error: ' . $e->getMessage()
    ]);
}
?>