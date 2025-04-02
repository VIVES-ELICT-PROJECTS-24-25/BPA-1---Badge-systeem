<?php
// Include database connection
require_once '../db_connection.php';

// Get the card ID from query parameter
$cardId = $_GET['cardId'] ?? '';

// Validate input
if (empty($cardId)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    // Check if the card exists in the database
    $stmt = $conn->prepare("
        SELECT v.*, u.Voornaam, u.Naam, u.Emailadres 
        FROM vives v 
        JOIN user u ON v.User_ID = u.User_ID 
        WHERE v.rfidkaartnr = ?
    ");
    $stmt->execute([$cardId]);
    
    if ($user = $stmt->fetch()) {
        // Card exists, return user info
        echo json_encode([
            'exists' => true,
            'user' => $user
        ]);
    } else {
        // Card not found
        echo json_encode(['exists' => false]);
    }
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
?>