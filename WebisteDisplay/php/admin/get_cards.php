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
    // Fetch all cards with user info
    $query = "
        SELECT v.*, u.Voornaam, u.Naam, o.naam as opleiding_naam
        FROM vives v
        JOIN user u ON v.User_ID = u.User_ID
        LEFT JOIN opleidingen o ON v.opleiding_id = o.id
        ORDER BY u.Voornaam, u.Naam
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $cards = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'cards' => $cards
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>