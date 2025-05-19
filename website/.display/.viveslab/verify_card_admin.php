<?php
// Start the session
session_start();

// Check for admin access
$is_admin = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == 1 && 
           isset($_SESSION['user']['Type']) && $_SESSION['user']['Type'] === 'beheerder';

// Redirect non-admin users
if (!$is_admin) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Toegang geweigerd'
    ]);
    exit();
}

// Include database connection
require_once 'db_connection.php';

// Log function for debugging
function logError($message) {
    error_log("[verify_card_admin.php] " . $message);
}

try {
    // Read the raw POST data
    $rawData = file_get_contents('php://input');
    
    // Parse JSON data
    $data = json_decode($rawData, true);
    
    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Ongeldige JSON data: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    // Check request type
    $requestType = isset($data['type']) ? $data['type'] : '';
    
    // Handle different request types
    switch($requestType) {
        case 'verifyCard':
            // Extract card ID
            $cardId = isset($data['cardId']) ? trim($data['cardId']) : '';
            
            if (empty($cardId)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Geen kaart ID ontvangen'
                ]);
                exit;
            }
            
            // Check if card exists in database - FIXED: lowercase 'opleidingen' table name
            $query = "
                SELECT u.*, v.rfidkaartnr, v.Vives_id, o.naam as opleiding_naam, v.Type as user_type, v.opleiding_id
                FROM User u 
                JOIN Vives v ON u.User_ID = v.User_ID 
                LEFT JOIN opleidingen o ON v.opleiding_id = o.id
                WHERE v.rfidkaartnr = ?
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$cardId]);
            
            // Check if a user was found
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Card exists - return user info
                echo json_encode([
                    'success' => true,
                    'exists' => true,
                    'user' => $user
                ]);
            } else {
                // Card not found
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'message' => 'Kaart niet gevonden in het systeem'
                ]);
            }
            break;
            
        case 'verifyVivesId':
            // Extract VIVES ID
            $vivesId = isset($data['vivesId']) ? trim($data['vivesId']) : '';
            
            if (empty($vivesId)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Geen VIVES nummer ontvangen'
                ]);
                exit;
            }
            
            // Check if VIVES ID exists in database - FIXED: lowercase 'opleidingen' table name
            $query = "
                SELECT u.*, v.rfidkaartnr, v.Vives_id, o.naam as opleiding_naam, v.Type as user_type, v.opleiding_id
                FROM User u 
                JOIN Vives v ON u.User_ID = v.User_ID 
                LEFT JOIN opleidingen o ON v.opleiding_id = o.id
                WHERE v.Vives_id = ?
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$vivesId]);
            
            // Check if a user was found
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // User exists with this VIVES ID
                echo json_encode([
                    'success' => true,
                    'exists' => true,
                    'user' => $user
                ]);
            } else {
                // User not found with this VIVES ID
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'message' => 'Geen gebruiker gevonden met dit VIVES nummer'
                ]);
            }
            break;
            
        case 'saveCard':
            // Extract data
            $cardId = isset($data['cardId']) ? trim($data['cardId']) : '';
            $vivesId = isset($data['vivesId']) ? trim($data['vivesId']) : '';
            $userId = isset($data['userId']) ? intval($data['userId']) : 0;
            
            if (empty($cardId) || (empty($vivesId) && empty($userId))) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Ontbrekende gegevens: kaart ID of gebruikersgegevens'
                ]);
                exit;
            }
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                if (!empty($userId)) {
                    // Update user by User_ID
                    $updateStmt = $conn->prepare("UPDATE Vives SET rfidkaartnr = ? WHERE User_ID = ?");
                    $updateStmt->execute([$cardId, $userId]);
                } else {
                    // Update user by VIVES ID
                    $updateStmt = $conn->prepare("UPDATE Vives SET rfidkaartnr = ? WHERE Vives_id = ?");
                    $updateStmt->execute([$cardId, $vivesId]);
                }
                
                // Check if any rows were updated
                if ($updateStmt->rowCount() > 0) {
                    $conn->commit();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Kaart succesvol toegevoegd aan gebruiker'
                    ]);
                } else {
                    $conn->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Geen gebruiker gevonden om te koppelen aan deze kaart'
                    ]);
                }
            } catch (PDOException $e) {
                $conn->rollBack();
                throw $e; // Re-throw for outer catch block
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ongeldig verzoektype'
            ]);
            break;
    }
} catch (PDOException $e) {
    logError("PDO Exception: " . $e->getMessage());
    
    // Handle database errors
    echo json_encode([
        'success' => false,
        'message' => 'Database fout: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    logError("General Exception: " . $e->getMessage());
    
    // Handle general errors
    echo json_encode([
        'success' => false,
        'message' => 'Fout: ' . $e->getMessage()
    ]);
}
?>