<?php
session_start();
header('Content-Type: application/json');

$db_host = "localhost"; // Vervang evt. door uw database host
$db_name = "vives_user";  // Vervang door uw database naam
$db_user = "root";      // Vervang door uw database gebruikersnaam
$db_pass = "";          // Vervang door uw database wachtwoord

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM gebruikers WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Sessiebeveiliging
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ongeldige gebruikersnaam of wachtwoord']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
