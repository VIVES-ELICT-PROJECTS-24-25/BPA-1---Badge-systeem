<?php
/**
 * API endpoint: Update Print Status
 * 
 * Updates the status of a print job in the database
 * Required POST parameters:
 *   - token: For authentication
 *   - reservation_id: ID of the reservation to update
 *   - completed: Boolean indicating if the print is completed
 */

// Set JSON content type and allow CORS for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// For debugging - remove in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define API security token
$expected_token = 'SUlhsg673GSbgsJYS6352jkdaLK';

// Check if request method is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Alleen POST requests toegestaan']);
    exit;
}

// Get POST data (handle both JSON and form data)
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

// If JSON parsing failed, try $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// Check authentication
if (!isset($data['token']) || $data['token'] !== $expected_token) {
    echo json_encode(['error' => 'Ongeldige authenticatie']);
    exit;
}

// Check required parameters
if (!isset($data['reservation_id']) || !isset($data['completed'])) {
    echo json_encode(['error' => 'Ontbrekende vereiste velden (reservation_id of completed)']);
    exit;
}

// Connect to database
require_once '../db_connection.php';

// Check if connection was successful
if (!isDatabaseConnected()) {
    echo json_encode(['error' => getDatabaseError()]);
    exit;
}

// Process the request
try {
    $reservation_id = intval($data['reservation_id']);
    $completed = filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN);
    
    if ($completed) {
        $query = "
            UPDATE Reservatie 
            SET print_completed = 1, 
                print_end_time = NOW(),
                last_update = NOW()
            WHERE Reservatie_ID = ?
        ";
    } else {
        $query = "
            UPDATE Reservatie 
            SET last_update = NOW()
            WHERE Reservatie_ID = ?
        ";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$reservation_id]);
    
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => $completed ? "Print #{$reservation_id} gemarkeerd als voltooid" : "Print #{$reservation_id} bijgewerkt"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Geen reservering gevonden met ID #{$reservation_id}"
        ]);
    }
    
} catch (Exception $e) {
    error_log("[API] Query error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database query error: ' . $e->getMessage()
    ]);
}
?>