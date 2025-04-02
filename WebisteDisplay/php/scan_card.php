<?php
// Include Firebase configuration
require_once 'firebase_config.php';
require_once 'db_connection.php';

// Function to listen for new card scans
function listenForCardScans() {
    global $database;
    
    // Check if Firebase is initialized
    if (!isFirebaseConnected()) {
        return [
            'success' => false, 
            'message' => getFirebaseError()
        ];
    }
    
    // Check if database is connected
    if (!isDatabaseConnected()) {
        return [
            'success' => false, 
            'message' => getDatabaseError()
        ];
    }
    
    try {
        // Reference to the rfid_scans node
        $reference = $database->getReference('rfid_scans');
        
        // Get the latest entry
        $snapshot = $reference->limitToLast(1)->getSnapshot();
        $data = $snapshot->getValue();
        
        if (empty($data)) {
            return [
                'success' => false,
                'message' => 'Geen recente kaartscans gevonden'
            ];
        }
        
        // Get the last entry
        $lastScan = end($data);
        $cardId = $lastScan['card_id'] ?? '';
        
        // Remove the processed entry
        $key = key($data);
        $reference->getChild($key)->remove();
        
        // If we have a card ID, verify it in the database
        if (!empty($cardId)) {
            // Verify card in database
            $stmt = $conn->prepare("
                SELECT u.User_ID, u.Voornaam, u.Naam, u.Type, v.rfidkaartnr, v.Vives_id 
                FROM user u 
                JOIN vives v ON u.User_ID = v.User_ID 
                WHERE v.rfidkaartnr = ?
            ");
            $stmt->execute([$cardId]);
            
            if ($user = $stmt->fetch()) {
                // Update user's last login
                $updateStmt = $conn->prepare("
                    UPDATE user 
                    SET LaatsteAanmelding = NOW(), HuidigActief = 1 
                    WHERE User_ID = ?
                ");
                $updateStmt->execute([$user['User_ID']]);
                
                return [
                    'success' => true,
                    'user' => $user
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Kaart niet gevonden in het systeem'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Ongeldige kaart ID ontvangen'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Firebase fout: ' . $e->getMessage()
        ];
    }
}

// If this file is accessed directly, return JSON response
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('Content-Type: application/json');
    echo json_encode(listenForCardScans());
}
?>