<?php
// Admin toegang controle
require_once 'admin.php';

// Schakel foutrapportage in voor debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start timer om uitvoeringstijd bij te houden
$startTime = microtime(true);

// Teller voor verzonden e-mails
$sentCount = 0;
$failedCount = 0;
$logMessages = [];

// Functie om log berichten toe te voegen
function addLogMessage($message) {
    global $logMessages;
    $logMessages[] = date('H:i:s') . ' - ' . $message;
    // Ook naar error_log schrijven voor debugging
    error_log($message);
}

addLogMessage("Script gestart");

try {
    // Haal alle reserveringen op die:
    // 1. Al voorbij zijn
    // 2. Nog geen feedback mail hebben ontvangen
    // 3. Een voltooide print hebben (print_completed = 1)
    $query = "
        SELECT r.Reservatie_ID, r.PRINT_END, 
               u.Voornaam, u.Naam, u.Emailadres,
               p.Versie_Toestel
        FROM Reservatie r
        JOIN User u ON r.User_ID = u.User_ID
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.PRINT_END < NOW() 
          AND (r.feedback_mail_verzonden = 0 OR r.feedback_mail_verzonden IS NULL)
          AND r.print_completed = 1
          AND u.Emailadres IS NOT NULL
        ORDER BY r.PRINT_END DESC
    ";

    $stmt = $conn->query($query);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalCount = count($reservations);
    addLogMessage("Gevonden voltooide reserveringen zonder feedback mail: " . $totalCount);
    
    if ($totalCount == 0) {
        // Geen mails te versturen
        addLogMessage("Geen voltooide reserveringen gevonden die feedback mails nodig hebben.");
        header('Location: reservations.php?success=' . urlencode('Geen nieuwe feedback mails te versturen.'));
        exit;
    }
    
    // Limiet instellen (om server overbelasting te voorkomen)
    $limit = 20; // Maximum aantal mails per keer
    $processCount = min($limit, $totalCount);
    
    addLogMessage("Verwerken van {$processCount} van de {$totalCount} reserveringen...");
    
    // Process each reservation
    foreach (array_slice($reservations, 0, $limit) as $reservation) {
        addLogMessage("Verwerken van reservering #{$reservation['Reservatie_ID']} voor {$reservation['Voornaam']} {$reservation['Naam']}");
        
        try {
            // Genereer een unieke feedback token
            $feedbackToken = md5($reservation['Reservatie_ID'] . time() . rand(1000, 9999));
            
            // Update de reservering eerst met de feedback token
            $updateStmt = $conn->prepare("
                UPDATE Reservatie 
                SET feedback_token = ? 
                WHERE Reservatie_ID = ?
            ");
            $updateStmt->execute([$feedbackToken, $reservation['Reservatie_ID']]);
            
            // Verzend e-mail met PHP's mail functie
            $to = $reservation['Emailadres'];
            $subject = 'Uw 3D print is klaar voor afhaling';
            
            // Bouw de feedback URL
            $feedbackUrl = 'https://3dprintersmaaklabvives.be/feedback.php?token=' . $feedbackToken;
            
            // Headers voor HTML e-mail
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: VIVES Maaklab 3D Printers <reservaties@3dprintersmaaklabvives.be>\r\n";
            
            // E-mail body
            $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { padding: 20px; }
                        .header { background: #007bff; color: #fff; padding: 10px; text-align: center; }
                        .content { margin: 20px 0; }
                        .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
                        .button { display: inline-block; background: #007bff; color: #fff; padding: 10px 20px; 
                                 text-decoration: none; border-radius: 5px; margin-top: 15px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Uw 3D print is klaar!</h1>
                        </div>
                        <div class='content'>
                            <p>Beste {$reservation['Voornaam']},</p>
                            <p>Goed nieuws! Uw 3D print op printer <strong>{$reservation['Versie_Toestel']}</strong> 
                            is succesvol afgerond en klaar voor afhaling.</p>
                            
                            <p><strong>Reserveringsdetails:</strong><br>
                            Reserveringsnummer: #{$reservation['Reservatie_ID']}<br>
                            Printer: {$reservation['Versie_Toestel']}<br>
                            Voltooid op: " . date('d-m-Y H:i', strtotime($reservation['PRINT_END'])) . "</p>
                            
                            <p>U kunt uw print afhalen in het VIVES Maaklab tijdens de openingsuren.</p>
                            
                            <p>Na het afhalen van uw print zouden we het zeer op prijs stellen als u even de tijd 
                            neemt om feedback te geven over uw ervaring. Dit helpt ons het systeem te verbeteren.</p>
                            
                            <p><a href='{$feedbackUrl}' style='display: inline-block; background: #007bff; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px;'>Geef feedback na afhaling</a></p>
                            
                            <p>Met vriendelijke groet,<br>Het VIVES Maaklab Team</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " VIVES Maaklab. Alle rechten voorbehouden.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            // Verzend de e-mail
            if (mail($to, $subject, $message, $headers)) {
                // Markeer de mail als verzonden in de database
                $conn->prepare("
                    UPDATE Reservatie 
                    SET feedback_mail_verzonden = 1 
                    WHERE Reservatie_ID = ?
                ")->execute([$reservation['Reservatie_ID']]);
                
                $sentCount++;
                addLogMessage("✓ Mail verzonden naar {$reservation['Emailadres']}");
                
                // Korte pauze tussen mails om server load te beperken
                usleep(500000); // 0.5 seconden pauze
            } else {
                $failedCount++;
                addLogMessage("✗ Fout bij verzenden naar {$reservation['Emailadres']}");
            }
            
        } catch (Exception $e) {
            $failedCount++;
            addLogMessage("✗ Fout voor reservering #{$reservation['Reservatie_ID']}: " . $e->getMessage());
        }
    }
    
    // Bereken totale tijd
    $executionTime = round(microtime(true) - $startTime, 2);
    addLogMessage("Script voltooid in {$executionTime} seconden. Verzonden: {$sentCount}, Mislukt: {$failedCount}");
    
    // Redirect met succesmelding
    $remaining = $totalCount - $processCount;
    $message = "Feedback mails verzonden: {$sentCount}, mislukt: {$failedCount}.";
    if ($remaining > 0) {
        $message .= " Er staan nog {$remaining} reserveringen in de wachtrij. Voer de actie opnieuw uit om deze te verwerken.";
    }
    
    header('Location: reservations.php?success=' . urlencode($message));
    exit;
    
} catch (PDOException $e) {
    // Log gedetailleerde foutmelding
    addLogMessage("Database error: " . $e->getMessage());
    
    // Redirect met foutmelding
    header('Location: reservations.php?error=' . urlencode('Database fout: ' . $e->getMessage()));
    exit;
}

// In geval van andere fouten, toon log
echo "<h1>Logboek</h1>";
echo "<pre>" . implode("\n", $logMessages) . "</pre>";
echo "<p><a href='reservations.php'>Terug naar Reserveringen</a></p>";
?>