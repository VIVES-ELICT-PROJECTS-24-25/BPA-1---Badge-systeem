<?php
// Logbestand instellen
$logfile = __DIR__ . '/verwijder_inactieve_gebruikers.log';
file_put_contents($logfile, "Script gestart op: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Database configuratie direct in het script
$host = "ID462020_badgesysteem.db.webhosting.be";
$dbname = "ID462020_badgesysteem";
$username = "ID462020_badgesysteem";
$password = "kS8M607q97p82Gs079Ck";

// Inactiviteitsperiode instellen in dagen
$inactivity_period = 365; // 1 jaar

try {
    // Database verbinding maken
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vind en verwijder inactieve gebruikers
    $delete_query = "DELETE FROM User 
                     WHERE LaatsteAanmelding < DATE_SUB(NOW(), INTERVAL $inactivity_period DAY)";
    
    $stmt = $pdo->prepare($delete_query);
    $stmt->execute();
    
    $verwijderde_gebruikers = $stmt->rowCount();
    file_put_contents($logfile, "Aantal verwijderde gebruikers: " . $verwijderde_gebruikers . "\n", FILE_APPEND);
    file_put_contents($logfile, "Script voltooid op: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);
    
} catch (PDOException $e) {
    file_put_contents($logfile, "Database fout: " . $e->getMessage() . "\n\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($logfile, "Algemene fout: " . $e->getMessage() . "\n\n", FILE_APPEND);
}
?>