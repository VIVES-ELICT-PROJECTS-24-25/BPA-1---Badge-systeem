<?php
// Admin toegang controle
require_once 'admin.php';

// Schakel foutrapportage in voor debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controleer of er een reserverings-ID is meegegeven
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect met foutmelding
    header('Location: reservations.php?error=' . urlencode('Geen geldige reserverings-ID opgegeven.'));
    exit;
}

$reservationId = (int)$_GET['id'];

try {
    // Haal de reserveringsgegevens op
    $stmt = $conn->prepare("
        SELECT r.*, u.Voornaam, u.Naam, u.Emailadres, p.Versie_Toestel  
        FROM Reservatie r
        JOIN User u ON r.User_ID = u.User_ID
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.Reservatie_ID = ?
    ");
    $stmt->execute([$reservationId]);
    $reservationData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Controleer of de reservering bestaat en een e-mailadres bevat
    if (!$reservationData || empty($reservationData['Emailadres'])) {
        // Redirect met foutmelding
        header('Location: reservations.php?error=' . urlencode('Reservering niet gevonden of geen e-mailadres beschikbaar.'));
        exit;
    }
    
    // Controleer of de print voltooid is
    if (!isset($reservationData['print_completed']) || $reservationData['print_completed'] != 1) {
        // Redirect met foutmelding
        header('Location: reservations.php?error=' . urlencode('De print is nog niet voltooid. Feedback mail kan pas verstuurd worden als de print voltooid is.'));
        exit;
    }

    // Maak een eenvoudige token
    $feedbackToken = md5($reservationId . time());
    
    // Verzend e-mail met PHP's mail functie in plaats van PHPMailer
    $to = $reservationData['Emailadres'];
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
                    <p>Beste {$reservationData['Voornaam']},</p>
                    <p>Goed nieuws! Uw 3D print op printer <strong>{$reservationData['Versie_Toestel']}</strong> 
                    is succesvol afgerond en klaar voor afhaling.</p>
                    
                    <p><strong>Reserveringsdetails:</strong><br>
                    Reserveringsnummer: #{$reservationId}<br>
                    Printer: {$reservationData['Versie_Toestel']}<br>
                    Voltooid op: " . date('d-m-Y H:i') . "</p>
                    
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
        // Update beide velden - feedback_token en feedback_mail_verzonden
        try {
            $conn->prepare("
                UPDATE Reservatie 
                SET feedback_token = ?,
                    feedback_mail_verzonden = 1
                WHERE Reservatie_ID = ?
            ")->execute([$feedbackToken, $reservationId]);
        } catch (PDOException $e) {
            // Negeer fouten, we gaan toch door
            error_log("Token update mislukt: " . $e->getMessage());
        }
        
        // Redirect met succesmelding
        header('Location: reservations.php?success=' . urlencode('Feedback mail succesvol verzonden naar ' . $reservationData['Voornaam'] . ' ' . $reservationData['Naam'] . '.'));
        exit;
    } else {
        // Redirect met foutmelding
        header('Location: reservations.php?error=' . urlencode('Fout bij verzenden e-mail. Controleer de server instellingen.'));
        exit;
    }
    
} catch (PDOException $e) {
    // Log gedetailleerde foutmelding
    error_log("Database error: " . $e->getMessage());
    
    // Redirect met foutmelding
    header('Location: reservations.php?error=' . urlencode('Database fout: ' . $e->getMessage()));
    exit;
}
?>