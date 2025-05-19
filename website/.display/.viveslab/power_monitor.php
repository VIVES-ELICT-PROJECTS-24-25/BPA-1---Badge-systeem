<?php
// power_monitor.php - Script om te controleren of prints zijn afgelopen
// Draai dit script via cronjob, bijv. elke 5 minuten:
// */5 * * * * php /pad/naar/power_monitor.php > /dev/null 2>&1

// Include database connection
require_once 'db_connection.php';

// Firebase library
require_once 'vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

// PHPMailer libraries
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Log functie
function logMessage($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    error_log("[power_monitor] " . $message);
}

try {
    // Initialiseer Firebase
    $serviceAccountPath = __DIR__ . '/maaklab-project-firebase-adminsdk-fbsvc-6560598dd1.json';
    $factory = (new Factory)
        ->withServiceAccount($serviceAccountPath)
        ->withDatabaseUri('https://maaklab-project-default-rtdb.europe-west1.firebasedatabase.app');
    
    $database = $factory->createDatabase();
    
    // Haal alle actieve prints op
    $activePrintsRef = $database->getReference("active_prints");
    $activePrints = $activePrintsRef->getValue();
    
    if (!$activePrints) {
        logMessage("Geen actieve prints gevonden.");
        exit(0);
    }
    
    $now = new DateTime();
    $nowStr = $now->format('Y-m-d H:i:s');
    
    foreach ($activePrints as $reservationId => $printData) {
        logMessage("Controleren van reservering #" . $reservationId);
        
        // Haal de bijbehorende Shelly ID op
        $shellyId = $printData['shelly_id'] ?? null;
        
        if (!$shellyId) {
            logMessage("Geen Shelly ID gevonden voor reservering #" . $reservationId);
            continue;
        }
        
        // Haal verbruiksgegevens van de Shelly op
        $shellyRef = $database->getReference("shellies/" . $shellyId);
        $shellyData = $shellyRef->getValue();
        
        if (!$shellyData) {
            logMessage("Geen data gevonden voor Shelly " . $shellyId);
            continue;
        }
        
        // Update last_check timestamp
        $activePrintsRef->child($reservationId)->update([
            'last_check' => $nowStr
        ]);
        
        // Controleer of de eindtijd is verstreken
        $endTime = new DateTime($printData['end_time']);
        
        if ($now > $endTime) {
            logMessage("Reservering #" . $reservationId . " is verlopen. Controleren op stroomverbruik...");
            
            // Controleer stroomverbruik
            $reportedPower = $shellyData['reported_power'] ?? 0;
            $powerThreshold = $printData['power_threshold'] ?? 10; // Standaard 10W
            
            logMessage("Huidig verbruik: " . $reportedPower . "W, drempelwaarde: " . $powerThreshold . "W");
            
            if ($reportedPower < $powerThreshold) {
                // Schakel Shelly uit als verbruik onder drempelwaarde is
                logMessage("Verbruik onder drempelwaarde. Shelly wordt uitgeschakeld.");
                
                // Update Firebase om Shelly uit te schakelen
                $shellyRef->update([
                    'state' => 'off',
                    'last_toggled' => $nowStr,
                    'command_processed' => false,  // Nieuw commando
                    'source' => 'auto_poweroff'
                ]);
                
                // Markeer print als voltooid
                $activePrintsRef->child($reservationId)->update([
                    'status' => 'completed',
                    'power_off_time' => $nowStr,
                    'completion_reason' => 'low_power_auto_off'
                ]);
                
                // Update de database
                $conn->prepare("
                    UPDATE Reservatie 
                    SET print_completed = 1, 
                        print_end_time = NOW(),
                        last_update = NOW()
                    WHERE Reservatie_ID = ?
                ")->execute([$reservationId]);
                
                logMessage("Reservering #" . $reservationId . " gemarkeerd als voltooid.");
                
                // ======= BEGIN NIEUWE CODE: E-MAIL NOTIFICATIE =======
                
                // Haal gebruiker en printer informatie op voor de e-mail
                $stmt = $conn->prepare("
                    SELECT r.*, u.Voornaam, u.Achternaam, u.Emailadres, p.Versie_Toestel  
                    FROM Reservatie r
                    JOIN User u ON r.Gebruiker_ID = u.user_id
                    JOIN Printer p ON r.Printer_ID = p.ID
                    WHERE r.Reservatie_ID = ?
                ");
                $stmt->execute([$reservationId]);
                $reservationData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($reservationData && !empty($reservationData['Emailadres'])) {
                    // Genereer een unieke feedback token
                    $feedbackToken = bin2hex(random_bytes(32));
                    
                    // Update de reservering met de feedback token
                    $conn->prepare("
                        UPDATE Reservatie 
                        SET feedback_token = ? 
                        WHERE Reservatie_ID = ?
                    ")->execute([$feedbackToken, $reservationId]);
                    
                    // Verstuur een e-mail naar de gebruiker
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host       = 'smtp-auth.mailprotect.be';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'reservaties@3dprintersmaaklabvives.be';
                        $mail->Password   = '9ke53d3w2ZP64ik76qHe';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->CharSet    = 'UTF-8';

                        // Recipients
                        $mail->setFrom('reservaties@3dprintersmaaklabvives.be', 'VIVES Maaklab 3D Printers');
                        $mail->addAddress($reservationData['Emailadres'], $reservationData['Voornaam'] . ' ' . $reservationData['Achternaam']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Uw 3D print is klaar voor afhaling';
                        
                        // Bouw de feedback URL
                        $feedbackUrl = 'https://3dprintersmaaklabvives.be/feedback.php?token=' . $feedbackToken;
                        
                        $mail->Body = "
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
                                        
                                        <p><a href='{$feedbackUrl}' class='button'>Geef feedback na afhaling</a></p>
                                        
                                        <p>Met vriendelijke groet,<br>Het VIVES Maaklab Team</p>
                                    </div>
                                    <div class='footer'>
                                        <p>&copy; " . date('Y') . " VIVES Maaklab. Alle rechten voorbehouden.</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";

                        $mail->send();
                        logMessage("E-mail verzonden naar gebruiker voor reservering #" . $reservationId);
                        
                    } catch (Exception $e) {
                        logMessage("Fout bij verzenden e-mail voor reservering #" . $reservationId . ": " . $e->getMessage());
                    }
                } else {
                    logMessage("Geen gebruikersgegevens gevonden voor reservering #" . $reservationId);
                }
                
                // ======= EINDE NIEUWE CODE =======
                
            } else {
                // Verbruik is nog boven drempelwaarde
                logMessage("Verbruik nog boven drempelwaarde. Wachten op verlaging.");
                
                // Update tijdstip laatste controle
                $activePrintsRef->child($reservationId)->update([
                    'last_power_check' => $nowStr,
                    'last_power_value' => $reportedPower
                ]);
            }
        } else {
            // Reservering is nog niet afgelopen
            $timeRemaining = $now->diff($endTime);
            logMessage("Reservering #" . $reservationId . " is nog actief. Tijd resterend: " . 
                $timeRemaining->format('%H:%I:%S'));
        }
    }
    
    logMessage("Controle voltooid.");
    
} catch (Exception $e) {
    logMessage("FOUT: " . $e->getMessage());
    exit(1);
}
?>