<?php
header('Content-Type: application/json');

$db_host = "localhost";
$db_name = "vives_user";
$db_user = "root";
$db_pass = "";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Alleen POST requests zijn toegestaan']);
    exit;
}

$voornaam = $_POST['voornaam'] ?? null;
$naam = $_POST['naam'] ?? null;
$email = $_POST['email'] ?? null;
$rnummer = $_POST['rnummer'] ?? null;
$studierichting = $_POST['studierichting'] ?? null;
$password = $_POST['password'] ?? null;

if (!$voornaam || !$naam || !$email || !$rnummer || !$studierichting || !$password) {
    echo json_encode(['success' => false, 'message' => 'Alle velden zijn verplicht']);
    exit;
}

// Wachtwoord hashen
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO users (voornaam, naam, email, r_nummer, studierichting, wachtwoord, created_at) 
        VALUES (:voornaam, :naam, :email, :rnummer, :studierichting, :wachtwoord, NOW())
        ON DUPLICATE KEY UPDATE 
            voornaam = VALUES(voornaam), 
            naam = VALUES(naam), 
            email = VALUES(email), 
            r_nummer = VALUES(r_nummer), 
            studierichting = VALUES(studierichting), 
            wachtwoord = VALUES(wachtwoord), 
            updated_at = NOW()
    ");

    $stmt->execute([
        ':voornaam' => $voornaam,
        ':naam' => $naam,
        ':email' => $email,
        ':rnummer' => $rnummer,
        ':studierichting' => $studierichting,
        ':wachtwoord' => $hashedPassword
    ]);

    echo json_encode(['success' => true, 'message' => 'Registratie voltooid']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
