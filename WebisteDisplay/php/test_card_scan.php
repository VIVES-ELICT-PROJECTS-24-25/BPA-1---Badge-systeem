<?php
// Include Firebase configuration
require_once 'firebase_config.php';

// Display test page
header('Content-Type: text/html');
echo "<h1>RFID Card Scan Test</h1>";

if (!isFirebaseConnected()) {
    echo "<p style='color: red;'>Firebase connection failed: " . getFirebaseError() . "</p>";
    exit;
}

// Get database reference
$database = getFirebaseDatabase();

try {
    // Reference to the rfid_latest_scan node
    $reference = $database->getReference('rfid_latest_scan');
    
    // Get the data
    $snapshot = $reference->getSnapshot();
    $data = $snapshot->getValue();
    
    echo "<h2>Latest RFID Data</h2>";
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($data['id'])) {
        echo "<p>Card ID: <strong>" . $data['id'] . "</strong></p>";
        
        // Check database for this card
        require_once 'db_connection.php';
        
        if (!isDatabaseConnected()) {
            echo "<p style='color: red;'>Database connection failed: " . getDatabaseError() . "</p>";
            exit;
        }
        
        $stmt = $conn->prepare("
            SELECT u.User_ID, u.Voornaam, u.Naam, u.Type, v.rfidkaartnr, v.Vives_id 
            FROM user u 
            JOIN vives v ON u.User_ID = v.User_ID 
            WHERE v.rfidkaartnr = ?
        ");
        $stmt->execute([$data['id']]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<p style='color: green;'>User found in database: " . $user['Voornaam'] . " " . $user['Naam'] . "</p>";
            echo "<pre>" . json_encode($user, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<p style='color: red;'>No user found with card ID: " . $data['id'] . "</p>";
            
            // Show all card IDs in the database for comparison
            $allCards = $conn->query("SELECT u.Voornaam, u.Naam, v.rfidkaartnr FROM user u JOIN vives v ON u.User_ID = v.User_ID")->fetchAll();
            
            echo "<h3>All cards in database:</h3>";
            echo "<ul>";
            foreach ($allCards as $card) {
                echo "<li>" . $card['Voornaam'] . " " . $card['Naam'] . ": <strong>" . $card['rfidkaartnr'] . "</strong></li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>No card ID found in latest scan data.</p>";
    }
    
    // Add test simulation form
    echo "<h2>Simulate Card Scan</h2>";
    echo "<form method='post' action='simulate_card_scan.php'>";
    echo "Card ID: <input type='text' name='card_id' value='62b09b02'>";
    echo "<input type='submit' value='Simulate Scan'>";
    echo "</form>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error accessing Firebase: " . $e->getMessage() . "</p>";
}
?>