<?php
// Include Firebase configuration
require_once 'firebase_config.php';

// Test Firebase connection
header('Content-Type: text/html');
echo "<h1>Firebase Connection Test</h1>";

if (isFirebaseConnected()) {
    echo "<p style='color: green;'>Firebase connection successful!</p>";
    
    // Try to read from the database
    try {
        $database = getFirebaseDatabase();
        $reference = $database->getReference('test');
        $reference->set([
            'timestamp' => time(),
            'message' => 'Test connection successful'
        ]);
        
        echo "<p>Successfully wrote to Firebase database.</p>";
        
        // Read back the data
        $snapshot = $reference->getSnapshot();
        $value = $snapshot->getValue();
        
        echo "<p>Read data from Firebase: " . json_encode($value) . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error accessing Firebase database: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>Firebase connection failed: " . getFirebaseError() . "</p>";
    
    // Check service account file
    $serviceAccountPath = __DIR__ . '/../maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';
    echo "<p>Service account path: " . $serviceAccountPath . "</p>";
    echo "<p>File exists: " . (file_exists($serviceAccountPath) ? "Yes" : "No") . "</p>";
    
    if (file_exists($serviceAccountPath)) {
        $fileContent = file_get_contents($serviceAccountPath);
        $jsonData = json_decode($fileContent, true);
        
        echo "<p>File structure valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? "Yes" : "No - " . json_last_error_msg()) . "</p>";
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<p>Project ID: " . ($jsonData['project_id'] ?? 'Not found') . "</p>";
            echo "<p>Has private key: " . (isset($jsonData['private_key']) ? "Yes" : "No") . "</p>";
        }
    }
    
    // Check composer installation
    echo "<h2>PHP Info</h2>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
    echo "<p>Firebase PHP SDK Installed: " . (class_exists('Kreait\\Firebase\\Factory') ? "Yes" : "No") . "</p>";
}
?>