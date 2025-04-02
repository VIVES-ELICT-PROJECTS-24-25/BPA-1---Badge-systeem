<?php
// Include Firebase configuration
require_once 'firebase_config.php';

// Get card ID from POST data
$cardId = $_POST['card_id'] ?? '';

if (empty($cardId)) {
    header('Location: test_card_scan.php?error=no_card_id');
    exit;
}

if (!isFirebaseConnected()) {
    header('Location: test_card_scan.php?error=firebase_not_connected');
    exit;
}

// Get database reference
$database = getFirebaseDatabase();

try {
    // Reference to the rfid_latest_scan node
    $reference = $database->getReference('rfid_latest_scan');
    
    // Update with new card ID
    $reference->set([
        'id' => $cardId,
        'timestamp' => time()
    ]);
    
    header('Location: test_card_scan.php?success=1');
    
} catch (Exception $e) {
    header('Location: test_card_scan.php?error=' . urlencode($e->getMessage()));
}
?>