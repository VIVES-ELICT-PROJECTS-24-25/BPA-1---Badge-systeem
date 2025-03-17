<?php
session_start();
header('Content-Type: application/json');

// Debug received data
file_put_contents('debug_log.txt', 
    date('Y-m-d H:i:s') . " POST data: " . 
    print_r($_POST, true) . "\n", 
    FILE_APPEND);

$db_host = "localhost";
$db_name = "vives_user";
$db_user = "root";
$db_pass = "";

// Hardcoded gebruikersnaam en wachtwoord
$hardcoded_username = 'vives';
$hardcoded_password = 'student';

// Debug comparison
file_put_contents('debug_log.txt', 
    "Comparing: '" . ($_POST['username'] ?? '') . "' with '$hardcoded_username' and '" . 
    ($_POST['password'] ?? '') . "' with '$hardcoded_password'\n", 
    FILE_APPEND);

try {
    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        file_put_contents('debug_log.txt', "Missing username or password\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Gebruikersnaam en wachtwoord zijn vereist']);
        exit;
    }

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
   
    // Debug after sanitization
    file_put_contents('debug_log.txt', 
        "After sanitization: username='$username', password='$password'\n", 
        FILE_APPEND);

    // Hardcoded verificatie without trim for debugging
    if ($username === $hardcoded_username && $password === $hardcoded_password) {
        file_put_contents('debug_log.txt', "Hardcoded login successful\n", FILE_APPEND);
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true]);
        exit;
   
    }
    
    file_put_contents('debug_log.txt', "Hardcoded login failed, trying database\n", FILE_APPEND);

    // Rest of your code...
    // ...

    echo json_encode(['success' => false, 'message' => 'Ongeldige gebruikersnaam of wachtwoord']);

} catch (PDOException $e) {
    file_put_contents('debug_log.txt', "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}