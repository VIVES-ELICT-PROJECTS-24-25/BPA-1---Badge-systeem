<?php
// Include database connection
require_once '../db_connection.php';

// Check if database is connected
if (!isDatabaseConnected()) {
    echo json_encode([
        'success' => false,
        'message' => getDatabaseError()
    ]);
    exit;
}

try {
    // Fetch all printers
    $query = "SELECT * FROM printer ORDER BY Printer_ID";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $printers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'printers' => $printers
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>