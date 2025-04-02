<?php
// Include database connection
require_once 'db_connection.php';

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';

// Validate input
if (empty($code) || strlen($code) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige code']);
    exit;
}

try {
    // Find reservation with this pincode
    $stmt = $conn->prepare("
        SELECT r.Reservatie_ID, r.User_ID, r.Pincode 
        FROM reservatie r 
        WHERE r.Pincode = ? AND 
              r.PRINT_END > NOW()
    ");
    $stmt->execute([$code]);
    
    // Check if a reservation was found
    if ($reservation = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get the user data
        $userStmt = $conn->prepare("
            SELECT u.User_ID, u.Voornaam, u.Naam, u.Type 
            FROM user u 
            WHERE u.User_ID = ?
        ");
        $userStmt->execute([$reservation['User_ID']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        // Update user's last login timestamp
        $updateStmt = $conn->prepare("
            UPDATE user 
            SET LaatsteAanmelding = NOW(), HuidigActief = 1 
            WHERE User_ID = ?
        ");
        $updateStmt->execute([$user['User_ID']]);
        
        // Return success with user data
        echo json_encode([
            'success' => true,
            'user' => $user,
            'reservation' => $reservation
        ]);
    } else {
        // No valid reservation found with this pincode
        echo json_encode([
            'success' => false,
            'message' => 'Ongeldige code of verlopen reservering'
        ]);
    }
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>