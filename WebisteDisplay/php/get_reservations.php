<?php
// Include database connection
require_once 'db_connection.php';

// Get the user ID from the query parameter
$userId = $_GET['userId'] ?? 0;

// Validate input
if (empty($userId) || !is_numeric($userId)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige gebruiker ID']);
    exit;
}

try {
    // Prepare and execute the query to get active reservations for the user
    $stmt = $conn->prepare("
        SELECT r.Reservatie_ID, r.Printer_ID, r.DATE_TIME_RESERVATIE,
               r.PRINT_START, r.PRINT_END, r.Comment, r.Pincode, r.filament_id,
               p.Versie_Toestel as printer_name,
               f.Type as filament_type, f.Kleur as filament_color
        FROM reservatie r
        LEFT JOIN printer p ON r.Printer_ID = p.Printer_ID
        LEFT JOIN filament f ON r.filament_id = f.id
        WHERE r.User_ID = ? AND r.PRINT_END > NOW()
        ORDER BY r.PRINT_START ASC
    ");
    $stmt->execute([$userId]);
    
    // Fetch all reservations
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the reservations
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>