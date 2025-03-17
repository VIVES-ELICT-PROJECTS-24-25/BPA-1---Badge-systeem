<?php
header('Content-Type: application/json');

$db_host = "localhost";
$db_name = "vives_user";
$db_user = "root";
$db_pass = "";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Alleen POST requests zijn toegestaan']);
    exit;
}

// Ontvang en valideer de invoer
$voornaam = filter_input(INPUT_POST, 'voornaam', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$naam = filter_input(INPUT_POST, 'naam', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$rnummer = filter_input(INPUT_POST, 'rnummer', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$studierichting = filter_input(INPUT_POST, 'studierichting', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = $_POST['password'] ?? null;

if (!$voornaam || !$naam || !$email || !$rnummer || !$studierichting || !$password) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Alle velden zijn verplicht en moeten correct zijn']);
    exit;
}

// Valideer het e-mailadres
if (!preg_match('/@(?:student\.vives\.be|vives\.be)$/', $email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mailadres moet eindigen op student.vives.be of vives.be']);
    exit;
}

// Wachtwoord validatie
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Wachtwoord moet minimaal 8 tekens lang zijn']);
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
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
