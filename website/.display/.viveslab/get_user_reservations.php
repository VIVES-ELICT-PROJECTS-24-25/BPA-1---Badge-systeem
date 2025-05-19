<?php
// Include database connection
require_once 'db_connection.php';

// Stel PHP in om alle fouten te laten zien (ontwikkeling)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log functie
function logError($message) {
    error_log("[get_user_reservations.php] " . $message);
}

// Check if user ID is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Geen gebruikers-ID opgegeven'
    ]);
    logError("No user ID provided");
    exit;
}

$userId = intval($_GET['user_id']);
logError("Fetching reservations for user ID: " . $userId);

try {
    // Check database connection
    if (!isDatabaseConnected()) {
        echo json_encode([
            'success' => false,
            'message' => 'Database verbindingsfout: ' . getDatabaseError()
        ]);
        logError("Database connection failed: " . getDatabaseError());
        exit;
    }
    
    // Get current datetime
    $now = date('Y-m-d H:i:s');
    
    // Query to get active and upcoming reservations with printer info
    $query = "
        SELECT r.*, f.Type as filament_type, f.Kleur as filament_color, p.Versie_Toestel
        FROM Reservatie r
        LEFT JOIN Filament f ON r.filament_id = f.id
        LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.User_ID = ? 
        AND r.PRINT_END >= ? 
        ORDER BY r.PRINT_START ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId, $now]);
    
    $reservations = $stmt->fetchAll();
    logError("Found " . count($reservations) . " active/upcoming reservations");
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
    
} catch (PDOException $e) {
    logError("PDO Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("General Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>