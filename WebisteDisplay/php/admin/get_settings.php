<?php
// Placeholder for actual settings
// In a real application, these would be stored in a database or config file

// Return some dummy settings for demonstration
echo json_encode([
    'success' => true,
    'settings' => [
        'firebase_url' => 'https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app',
        'scan_timeout' => 30,
        'reservations_days' => 7,
        'school_name' => 'VIVES Hogeschool'
    ]
]);
?>