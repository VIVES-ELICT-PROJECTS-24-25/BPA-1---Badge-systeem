<?php
// save.php - Script om gebruikersgegevens op te slaan in de database

// Database configuratie
$db_host = "localhost"; // Vervang evt. door uw database host
$db_name = "vives_user";  // Vervang door uw database naam
$db_user = "root";      // Vervang door uw database gebruikersnaam
$db_pass = "";          // Vervang door uw database wachtwoord

// Headers instellen
header('Content-Type: application/json');

// Controleer of het een POST request is
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Alleen POST requests zijn toegestaan']);
    exit;
}

// Ontvang de gegevens uit het formulier
$rfidValue = $_POST['rfidValue'] ?? '';
$voornaam = $_POST['voornaam'] ?? '';
$naam = $_POST['naam'] ?? '';
$rnummer = $_POST['rnummer'] ?? '';
$email = $_POST['email'] ?? '';

// Valideer of alle benodigde velden zijn ingevuld
if (empty($rfidValue) || empty($voornaam) || empty($naam) || empty($rnummer) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Alle velden zijn verplicht']);
    exit;
}

try {
    // Verbinding maken met de database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL query voorbereiden
    $stmt = $pdo->prepare("
        INSERT INTO users (rfid_value, voornaam, naam, r_nummer, email, created_at) 
        VALUES (:rfid, :voornaam, :naam, :rnummer, :email, NOW())
        ON DUPLICATE KEY UPDATE 
            voornaam = :voornaam, 
            naam = :naam, 
            r_nummer = :rnummer, 
            email = :email, 
            updated_at = NOW()
    ");
    
    // Parameters binden
    $stmt->bindParam(':rfid', $rfidValue);
    $stmt->bindParam(':voornaam', $voornaam);
    $stmt->bindParam(':naam', $naam);
    $stmt->bindParam(':rnummer', $rnummer);
    $stmt->bindParam(':email', $email);
    
    // Query uitvoeren
    $stmt->execute();
    
    // Controleer of rij is toegevoegd of bijgewerkt
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Gegevens succesvol opgeslagen']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Geen wijzigingen aangebracht']);
    }
} catch (PDOException $e) {
    // Foutafhandeling
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}