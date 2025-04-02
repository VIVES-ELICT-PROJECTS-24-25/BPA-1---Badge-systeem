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
    // Fetch all users
    $query = "SELECT * FROM user ORDER BY Naam, Voornaam";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>