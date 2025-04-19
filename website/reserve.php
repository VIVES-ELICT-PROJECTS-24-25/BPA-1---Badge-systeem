<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Include PHPMailer (adjust path if needed)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php'; // Adjust path to your PHPMailer installation

$currentPage = 'reserve';
$pageTitle = 'Printer Reserveren - 3D Printer Reserveringssysteem';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['User_ID'];
$printerId = isset($_GET['printer_id']) ? intval($_GET['printer_id']) : 0;
$error = '';
$success = '';

// Haal huidige datum en minimale/maximale tijden op
$today = date('Y-m-d');
$minDate = $today;
$maxDate = date('Y-m-d', strtotime('+30 days')); // Max 30 dagen vooruit

// Haal printer informatie op
$printer = null;
if ($printerId > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, f.id AS filament_id, f.Type AS filament_type, f.Kleur AS filament_color 
            FROM Printer p
            LEFT JOIN Filament_compatibiliteit fc ON p.Printer_ID = fc.printer_id
            LEFT JOIN Filament f ON fc.filament_id = f.id
            WHERE p.Printer_ID = ?
            LIMIT 1
        ");
        $stmt->execute([$printerId]);
        $printer = $stmt->fetch();
        
        if (!$printer) {
            header('Location: printers.php');
            exit;
        }
        
        // Controleer of de printer beschikbaar is
        if ($printer['Status'] !== 'beschikbaar') {
            header('Location: printer-details.php?id=' . $printerId . '&error=not_available');
            exit;
        }
    } catch (PDOException $e) {
        $error = 'Database fout: ' . $e->getMessage();
    }
} else {
    header('Location: printers.php');
    exit;
}

// Haal alle beschikbare filamenten op
$filamenten = [];
try {
    $stmt = $conn->query("SELECT * FROM Filament ORDER BY Type, Kleur");
    $filamenten = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database fout bij ophalen filamenten: ' . $e->getMessage();
}

// Verwerk reserveringsformulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $filamentId = isset($_POST['filament_id']) && !empty($_POST['filament_id']) ? intval($_POST['filament_id']) : null;
    $comment = trim($_POST['comment'] ?? '');
    $hulpNodig = isset($_POST['help_needed']) ? 1 : 0;
    $beheerderPrinten = isset($_POST['admin_print']) ? 1 : 0;
    
    // Genereer een pincode van 6 cijfers
    $pincode = sprintf("%06d", rand(0, 999999));
    
    // Validatie
    if (empty($startDate) || empty($startTime) || empty($endDate) || empty($endTime)) {
        $error = 'Alle tijdvelden zijn verplicht.';
    } else if ($beheerderPrinten && !isset($_FILES['print_file']) && empty($_FILES['print_file']['name'])) {
        $error = 'Als je de beheerder wilt laten printen, moet je een bestand uploaden.';
    } else {
        // Samenstellen van start- en eindtijd
        $startDateTime = $startDate . ' ' . $startTime . ':00';
        $endDateTime = $endDate . ' ' . $endTime . ':00';
        
        // Controleer of einddatum niet voor startdatum ligt
        if (strtotime($endDateTime) <= strtotime($startDateTime)) {
            $error = 'De eindtijd moet na de starttijd liggen.';
        } else {
            try {
                // Controleer of de printer beschikbaar is in het gekozen tijdslot
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM Reservatie 
                    WHERE Printer_ID = ? 
                    AND (
                        (PRINT_START <= ? AND PRINT_END > ?) OR
                        (PRINT_START < ? AND PRINT_END >= ?) OR
                        (PRINT_START >= ? AND PRINT_END <= ?)
                    )
                ");
                $stmt->execute([
                    $printerId, 
                    $endDateTime, $startDateTime, 
                    $endDateTime, $startDateTime,
                    $startDateTime, $endDateTime
                ]);
                
                $conflictCount = $stmt->fetch()['count'];
                
                if ($conflictCount > 0) {
                    $error = 'De printer is niet beschikbaar in het gekozen tijdslot. Kies een andere tijd.';
                } else {
                    // Verwerk het geüploade bestand indien beheerder moet printen
                    $uploadedFilePath = '';
                    $uploadedFileName = '';
                    
                    if ($beheerderPrinten && isset($_FILES['print_file']) && $_FILES['print_file']['error'] == 0) {
                        $uploadDir = 'uploads/print_files/';
                        
                        // Maak de map aan als deze niet bestaat
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        // Genereer een unieke bestandsnaam
                        $uploadedFileName = $_FILES['print_file']['name'];
                        $fileExtension = pathinfo($uploadedFileName, PATHINFO_EXTENSION);
                        $uniqueId = uniqid('print_', true);
                        $newFileName = $uniqueId . '.' . $fileExtension;
                        $uploadedFilePath = $uploadDir . $newFileName;
                        
                        // Verplaats het bestand
                        if (!move_uploaded_file($_FILES['print_file']['tmp_name'], $uploadedFilePath)) {
                            $error = 'Er is een fout opgetreden bij het uploaden van het bestand.';
                        }
                    }
                    
                    if (empty($error)) {
                        // Begin transaction OUTSIDE of try block
                        $conn->beginTransaction();
                        
                        try {
                            // Genereer een nieuw Reservatie_ID (auto increment simuleren)
                            $stmtMaxId = $conn->query("SELECT MAX(Reservatie_ID) as maxId FROM Reservatie");
                            $result = $stmtMaxId->fetch();
                            $newReservationId = ($result['maxId'] ?? 0) + 1;
                            
                            // Maak de reservering
                            $stmt = $conn->prepare("
                                INSERT INTO Reservatie (
                                    Reservatie_ID, User_ID, Printer_ID, DATE_TIME_RESERVATIE, 
                                    PRINT_START, PRINT_END, Comment, Pincode, filament_id, verbruik, HulpNodig, BeheerderPrinten
                                ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, NULL, ?, ?)
                            ");
                            
                            $stmt->execute([
                                $newReservationId,
                                $userId,
                                $printerId,
                                $startDateTime,
                                $endDateTime,
                                $comment,
                                $pincode,
                                $filamentId,
                                $hulpNodig,
                                $beheerderPrinten
                            ]);
                            
                            // REMOVED: We no longer update the printer status to keep it available
                            // The printer should remain 'beschikbaar' so others can reserve it for different time slots
                            
                            // Commit transaction
                            $conn->commit();
                            
                            // Get user email for notification - FIXED COLUMN NAMES based on actual DB schema
                            $userStmt = $conn->prepare("SELECT Emailadres, Voornaam, Naam FROM User WHERE User_ID = ?");
                            $userStmt->execute([$userId]);
                            $user = $userStmt->fetch();
                            
                            // Get printer details
                            $printerStmt = $conn->prepare("SELECT Versie_Toestel FROM Printer WHERE Printer_ID = ?");
                            $printerStmt->execute([$printerId]);
                            $printerDetails = $printerStmt->fetch();
                            
                            // Get filament details if selected
                            $filamentName = "Eigen filament";
                            if ($filamentId) {
                                $filamentStmt = $conn->prepare("SELECT Type, Kleur FROM Filament WHERE id = ?");
                                $filamentStmt->execute([$filamentId]);
                                $filamentDetails = $filamentStmt->fetch();
                                if ($filamentDetails) {
                                    $filamentName = $filamentDetails['Type'] . ' - ' . $filamentDetails['Kleur'];
                                }
                            }
                            
                            // Format dates for display
                            $startDateFormatted = date('d-m-Y', strtotime($startDateTime));
                            $startTimeFormatted = date('H:i', strtotime($startDateTime));
                            $endDateFormatted = date('d-m-Y', strtotime($endDateTime));
                            $endTimeFormatted = date('H:i', strtotime($endDateTime));
                            
                            // Send email confirmation to user
                            try {
                                // Create a new PHPMailer instance
                                $mail = new PHPMailer(true);
                                
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
                                $mail->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printers MaakLab VIVES');
                                $mail->addAddress($user['Emailadres'], $user['Voornaam'] . ' ' . $user['Naam']);
                                
                                // Content
                                $mail->isHTML(true);
                                $mail->Subject = 'Bevestiging van uw 3D Printer Reservering #' . $newReservationId;
                                
                                // Create the HTML email body
                                $emailBody = '
                                <!DOCTYPE html>
                                <html>
                                <head>
                                    <style>
                                        body {
                                            font-family: Arial, sans-serif;
                                            line-height: 1.6;
                                            color: #333333;
                                        }
                                        .container {
                                            max-width: 600px;
                                            margin: 0 auto;
                                        }
                                        .header {
                                            background-color: #0d6efd;
                                            color: white;
                                            padding: 20px;
                                            text-align: center;
                                        }
                                        .content {
                                            padding: 20px;
                                            background-color: #f9f9f9;
                                        }
                                        .reservation-details {
                                            background-color: white;
                                            border: 1px solid #dddddd;
                                            border-radius: 5px;
                                            padding: 15px;
                                            margin-bottom: 20px;
                                        }
                                        .detail-row {
                                            padding: 8px 0;
                                            border-bottom: 1px solid #eeeeee;
                                        }
                                        .detail-row:last-child {
                                            border-bottom: none;
                                        }
                                        .label {
                                            font-weight: bold;
                                            display: inline-block;
                                            width: 160px;
                                        }
                                        .pincode {
                                            font-size: 24px;
                                            font-weight: bold;
                                            color: #0d6efd;
                                            text-align: center;
                                            padding: 10px;
                                            margin: 10px 0;
                                            border: 2px dashed #0d6efd;
                                            background-color: #f8f9ff;
                                        }
                                        .footer {
                                            margin-top: 20px;
                                            text-align: center;
                                            font-size: 12px;
                                            color: #666666;
                                        }
                                        .note {
                                            padding: 10px;
                                            background-color: #fff3cd;
                                            border-left: 4px solid #ffc107;
                                            margin: 15px 0;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class="container">
                                        <div class="header">
                                            <h1>Bevestiging Reservering</h1>
                                        </div>
                                        <div class="content">
                                            <p>Beste ' . htmlspecialchars($user['Voornaam']) . ',</p>
                                            
                                            <p>Bedankt voor je reservering bij 3D Printers MaakLab VIVES. Hieronder vind je alle details van je reservering:</p>
                                            
                                            <div class="reservation-details">
                                                <div class="detail-row">
                                                    <span class="label">Reserveringsnummer:</span> #' . $newReservationId . '
                                                </div>
                                                <div class="detail-row">
                                                    <span class="label">Printer:</span> ' . htmlspecialchars($printerDetails['Versie_Toestel']) . '
                                                </div>
                                                <div class="detail-row">
                                                    <span class="label">Startdatum:</span> ' . $startDateFormatted . '
                                                </div>
                                                <div class="detail-row">
                                                    <span class="label">Starttijd:</span> ' . $startTimeFormatted . '
                                                </div>
                                                <div class="detail-row">
                                                    <span class="label">Einddatum:</span> ' . $endDateFormatted . '
                                                </div>
                                                <div class="detail-row">
                                                    <span class="label">Eindtijd:</span> ' . $endTimeFormatted . '
                                                </div>
                                                <div class="detail-row">
                                                    <span class="label">Filament:</span> ' . htmlspecialchars($filamentName) . '
                                                </div>';
                                
                                if ($hulpNodig == 1) {
                                    $emailBody .= '
                                                <div class="detail-row">
                                                    <span class="label">Hulp nodig:</span> Ja
                                                </div>';
                                }
                                
                                if ($beheerderPrinten == 1) {
                                    $emailBody .= '
                                                <div class="detail-row">
                                                    <span class="label">Beheerder print:</span> Ja
                                                </div>';
                                    
                                    if (!empty($uploadedFileName)) {
                                        $emailBody .= '
                                                <div class="detail-row">
                                                    <span class="label">Bestand:</span> ' . htmlspecialchars($uploadedFileName) . '
                                                </div>';
                                    }
                                }
                                
                                $emailBody .= (!empty($comment) ? '
                                                <div class="detail-row">
                                                    <span class="label">Opmerkingen:</span> ' . htmlspecialchars($comment) . '
                                                </div>' : '') . '
                                            </div>
                                            
                                            <p>Gebruik de volgende pincode om de printer te ontgrendelen:</p>
                                            
                                            <div class="pincode">' . $pincode . '</div>';
                                
                                if ($beheerderPrinten == 1) {
                                    $emailBody .= '
                                            <div class="note">
                                                <strong>Let op:</strong> Je hebt aangegeven dat de beheerder voor je moet printen. 
                                                Je bestand is succesvol geüpload en zal worden verwerkt door de beheerder.
                                            </div>';
                                }
                                
                                $emailBody .= '
                                            <div class="note">
                                                <strong>Opmerking:</strong> Annuleren is mogelijk tot 2 uur voor de starttijd. Log in op de website om je reservering te beheren.
                                            </div>
                                            
                                            <p>We wensen je veel succes met je project!</p>
                                            
                                            <p>Met vriendelijke groeten,<br>
                                            Het team van 3D Printers MaakLab VIVES</p>
                                        </div>
                                        <div class="footer">
                                            <p>Dit is een automatisch gegenereerd bericht. Gelieve niet te antwoorden op deze e-mail.</p>
                                            <p>&copy; ' . date('Y') . ' 3D Printers MaakLab VIVES. Alle rechten voorbehouden.</p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                                ';
                                
                                $mail->Body = $emailBody;
                                
                                // Create a plain text version for email clients that don't support HTML
                                $altBodyText = "Bevestiging van uw 3D Printer Reservering #" . $newReservationId . "\n\n" .
                                               "Printer: " . $printerDetails['Versie_Toestel'] . "\n" .
                                               "Start: " . $startDateFormatted . " om " . $startTimeFormatted . "\n" .
                                               "Einde: " . $endDateFormatted . " om " . $endTimeFormatted . "\n" .
                                               "Filament: " . $filamentName . "\n";
                                
                                if ($hulpNodig == 1) {
                                    $altBodyText .= "Hulp nodig: Ja\n";
                                }
                                
                                if ($beheerderPrinten == 1) {
                                    $altBodyText .= "Beheerder print: Ja\n";
                                    if (!empty($uploadedFileName)) {
                                        $altBodyText .= "Bestand: " . $uploadedFileName . "\n";
                                    }
                                }
                                
                                $altBodyText .= "Pincode: " . $pincode . "\n\n" .
                                               "Bedankt voor je reservering bij 3D Printers MaakLab VIVES.";
                                
                                $mail->AltBody = $altBodyText;
                                
                                $mail->send();
                                
                                // Stuur notificatie naar beheerder als hulp nodig is of beheerder moet printen
                                if ($hulpNodig == 1 || $beheerderPrinten == 1) {
                                    $adminMail = new PHPMailer(true);
                                    
                                    // Server settings
                                    $adminMail->isSMTP();
                                    $adminMail->Host       = 'smtp-auth.mailprotect.be';
                                    $adminMail->SMTPAuth   = true;
                                    $adminMail->Username   = 'reservaties@3dprintersmaaklabvives.be';
                                    $adminMail->Password   = '9ke53d3w2ZP64ik76qHe';
                                    $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                    $adminMail->Port       = 587;
                                    $adminMail->CharSet    = 'UTF-8';
                                    
                                    // Recipients
                                    $adminMail->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printers MaakLab VIVES');
                                    $adminMail->addAddress('vdklars159@gmail.com', 'Beheerder 3D Printers');
                                    
                                    // Reply-to
                                    $adminMail->addReplyTo($user['Emailadres'], $user['Voornaam'] . ' ' . $user['Naam']);
                                    
                                    // Attachment
                                    if ($beheerderPrinten && !empty($uploadedFilePath) && file_exists($uploadedFilePath)) {
                                        $adminMail->addAttachment($uploadedFilePath, $uploadedFileName);
                                    }
                                    
                                    // Content
                                    $adminMail->isHTML(true);
                                    
                                    $subject = '';
                                    if ($hulpNodig == 1 && $beheerderPrinten == 1) {
                                        $subject = 'Printopdracht en hulp vereist - Reservering #' . $newReservationId;
                                    } elseif ($hulpNodig == 1) {
                                        $subject = 'Hulp vereist bij printen - Reservering #' . $newReservationId;
                                    } elseif ($beheerderPrinten == 1) {
                                        $subject = 'Printopdracht - Reservering #' . $newReservationId;
                                    }
                                    
                                    $adminMail->Subject = $subject;
                                    
                                    // Create the HTML email body for admin
                                    $adminEmailBody = '
                                    <!DOCTYPE html>
                                    <html>
                                    <head>
                                        <style>
                                            body {
                                                font-family: Arial, sans-serif;
                                                line-height: 1.6;
                                                color: #333333;
                                            }
                                            .container {
                                                max-width: 600px;
                                                margin: 0 auto;
                                            }
                                            .header {
                                                background-color: #0d6efd;
                                                color: white;
                                                padding: 20px;
                                                text-align: center;
                                            }
                                            .content {
                                                padding: 20px;
                                                background-color: #f9f9f9;
                                            }
                                            .reservation-details {
                                                background-color: white;
                                                border: 1px solid #dddddd;
                                                border-radius: 5px;
                                                padding: 15px;
                                                margin-bottom: 20px;
                                            }
                                            .detail-row {
                                                padding: 8px 0;
                                                border-bottom: 1px solid #eeeeee;
                                            }
                                            .detail-row:last-child {
                                                border-bottom: none;
                                            }
                                            .label {
                                                font-weight: bold;
                                                display: inline-block;
                                                width: 160px;
                                            }
                                            .action-required {
                                                background-color: #f8d7da;
                                                border: 1px solid #f5c6cb;
                                                color: #721c24;
                                                padding: 10px;
                                                margin: 15px 0;
                                                border-radius: 5px;
                                            }
                                            .file-attached {
                                                background-color: #d1ecf1;
                                                border: 1px solid #bee5eb;
                                                color: #0c5460;
                                                padding: 10px;
                                                margin: 15px 0;
                                                border-radius: 5px;
                                            }
                                        </style>
                                    </head>
                                    <body>
                                        <div class="container">
                                            <div class="header">
                                                <h1>Actie Vereist</h1>
                                            </div>
                                            <div class="content">
                                                <p>Beste beheerder,</p>
                                                
                                                <div class="action-required">
                                                    <h3 style="margin-top: 0;">';
                                    
                                    if ($hulpNodig == 1 && $beheerderPrinten == 1) {
                                        $adminEmailBody .= 'Gebruiker heeft hulp nodig en een printopdracht';
                                    } elseif ($hulpNodig == 1) {
                                        $adminEmailBody .= 'Gebruiker heeft hulp nodig bij het printen';
                                    } elseif ($beheerderPrinten == 1) {
                                        $adminEmailBody .= 'Gebruiker heeft een printopdracht';
                                    }
                                    
                                    $adminEmailBody .= '</h3>
                                                    <p>Voor de onderstaande reservering is actie vereist:</p>
                                                </div>';
                                    
                                    if ($beheerderPrinten && !empty($uploadedFileName)) {
                                        $adminEmailBody .= '
                                                <div class="file-attached">
                                                    <h3 style="margin-top: 0;">Bestand bijgevoegd</h3>
                                                    <p>Het te printen bestand <strong>' . htmlspecialchars($uploadedFileName) . '</strong> is als bijlage toegevoegd aan deze e-mail.</p>
                                                </div>';
                                    }
                                    
                                    $adminEmailBody .= '
                                                <div class="reservation-details">
                                                    <div class="detail-row">
                                                        <span class="label">Reserveringsnr.:</span> #' . $newReservationId . '
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label">Gebruiker:</span> ' . htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']) . '
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label">E-mail:</span> ' . htmlspecialchars($user['Emailadres']) . '
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label">Printer:</span> ' . htmlspecialchars($printerDetails['Versie_Toestel']) . '
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label">Start:</span> ' . $startDateFormatted . ' ' . $startTimeFormatted . '
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label">Einde:</span> ' . $endDateFormatted . ' ' . $endTimeFormatted . '
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="label">Filament:</span> ' . htmlspecialchars($filamentName) . '
                                                    </div>';
                                    
                                    if ($hulpNodig == 1) {
                                        $adminEmailBody .= '
                                                    <div class="detail-row">
                                                        <span class="label">Hulp nodig:</span> <strong>Ja</strong>
                                                    </div>';
                                    }
                                    
                                    if ($beheerderPrinten == 1) {
                                        $adminEmailBody .= '
                                                    <div class="detail-row">
                                                        <span class="label">Beheerder print:</span> <strong>Ja</strong>
                                                    </div>';
                                    }
                                    
                                    $adminEmailBody .= (!empty($comment) ? '
                                                    <div class="detail-row">
                                                        <span class="label">Opmerkingen:</span> ' . htmlspecialchars($comment) . '
                                                    </div>' : '') . '
                                                </div>
                                                
                                                <p>Neem contact op met de gebruiker als je meer informatie nodig hebt.</p>
                                                
                                                <p>Met vriendelijke groeten,<br>
                                                Reservatie Systeem 3D Printers MaakLab VIVES</p>
                                            </div>
                                        </div>
                                    </body>
                                    </html>
                                    ';
                                    
                                    $adminMail->Body = $adminEmailBody;
                                    
                                    // Create a plain text version for admin email
                                    $adminAltBody = "Actie vereist - Reservering #" . $newReservationId . "\n\n";
                                    
                                    if ($hulpNodig == 1 && $beheerderPrinten == 1) {
                                        $adminAltBody .= "Gebruiker heeft hulp nodig en een printopdracht\n\n";
                                    } elseif ($hulpNodig == 1) {
                                        $adminAltBody .= "Gebruiker heeft hulp nodig bij het printen\n\n";
                                    } elseif ($beheerderPrinten == 1) {
                                        $adminAltBody .= "Gebruiker heeft een printopdracht\n\n";
                                    }
                                    
                                    if ($beheerderPrinten && !empty($uploadedFileName)) {
                                        $adminAltBody .= "BESTAND BIJGEVOEGD: " . $uploadedFileName . "\n\n";
                                    }
                                    
                                    $adminAltBody .= "Reserveringsnr.: #" . $newReservationId . "\n" .
                                                   "Gebruiker: " . $user['Voornaam'] . ' ' . $user['Naam'] . "\n" .
                                                   "E-mail: " . $user['Emailadres'] . "\n" .
                                                   "Printer: " . $printerDetails['Versie_Toestel'] . "\n" .
                                                   "Start: " . $startDateFormatted . " " . $startTimeFormatted . "\n" .
                                                   "Einde: " . $endDateFormatted . " " . $endTimeFormatted . "\n" .
                                                   "Filament: " . $filamentName . "\n";
                                    
                                    if ($hulpNodig == 1) {
                                        $adminAltBody .= "Hulp nodig: Ja\n";
                                    }
                                    
                                    if ($beheerderPrinten == 1) {
                                        $adminAltBody .= "Beheerder print: Ja\n";
                                    }
                                    
                                    if (!empty($comment)) {
                                        $adminAltBody .= "Opmerkingen: " . $comment . "\n";
                                    }
                                    
                                    $adminAltBody .= "\nNeem contact op met de gebruiker als je meer informatie nodig hebt.";
                                    
                                    $adminMail->AltBody = $adminAltBody;
                                    
                                    $adminMail->send();
                                }
                                
                            } catch (Exception $e) {
                                // Log the error but don't stop the process
                                error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                            }
                            
                            $success = 'Je reservering is succesvol aangemaakt!';
                            
                            // Redirect naar reservering details
                            header("Location: reservation-details.php?id=" . $newReservationId . "&success=created");
                            exit;
                            
                        } catch (PDOException $e) {
                            // Rollback bij fouten
                            if ($conn->inTransaction()) {
                                $conn->rollBack();
                            }
                            $error = 'Er is een fout opgetreden bij het maken van je reservering: ' . $e->getMessage();
                        }
                    }
                }
            } catch (PDOException $e) {
                // Make sure any open transaction is rolled back
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $error = 'Database fout bij controleren beschikbaarheid: ' . $e->getMessage();
            }
        }
    }
}

// Voeg database tabellen toe indien nodig (Dit zou normaal in een aparte migratiescript moeten gebeuren)
try {
    $conn->exec("
        ALTER TABLE Reservatie 
        ADD COLUMN IF NOT EXISTS HulpNodig TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS BeheerderPrinten TINYINT(1) DEFAULT 0
    ");
} catch (PDOException $e) {
    // Log de fout maar ga verder met de pagina
    error_log("Error adding columns to Reservatie table: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Printer Reserveren</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="printers.php">Printers</a></li>
                        <li class="breadcrumb-item"><a href="printer-details.php?id=<?php echo $printerId; ?>"><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reserveren</li>
                    </ol>
                </nav>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Maak een nieuwe reservering</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="reserve.php?printer_id=<?php echo $printerId; ?>" id="reservationForm" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Startdatum *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       min="<?php echo $minDate; ?>" max="<?php echo $maxDate; ?>" 
                                       value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : $today; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">Starttijd *</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" 
                                       min="08:00" max="19:30" 
                                       value="<?php echo isset($_POST['start_time']) ? $_POST['start_time'] : '09:00'; ?>" required>
                                <small class="form-text text-muted">Tussen 8:00 en 19:30</small>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Einddatum *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       min="<?php echo $minDate; ?>" max="<?php echo $maxDate; ?>" 
                                       value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : $today; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">Eindtijd *</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" 
                                       min="08:30" max="20:00" 
                                       value="<?php echo isset($_POST['end_time']) ? $_POST['end_time'] : '10:00'; ?>" required>
                                <small class="form-text text-muted">Tussen 8:30 en 20:00</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="filament_id" class="form-label">Filament</label>
                            <select class="form-select" id="filament_id" name="filament_id">
                                <option value="">Geen filament / Eigen filament</option>
                                <?php foreach ($filamenten as $filament): ?>
                                    <option value="<?php echo $filament['id']; ?>" <?php echo (isset($_POST['filament_id']) && $_POST['filament_id'] == $filament['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($filament['Type'] . ' - ' . $filament['Kleur']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Optioneel. Selecteer het filament dat je wilt gebruiken of laat leeg als je je eigen filament meebrengt.</small>
                        </div>
                        
                        <!-- Hulp nodig checkbox -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="help_needed" name="help_needed">
                            <label class="form-check-label" for="help_needed">Ik heb hulp nodig bij het printen</label>
                            <small class="form-text text-muted d-block">
                                Vink dit aan als je assistentie nodig hebt tijdens het printen. Een beheerder zal contact met je opnemen.
                            </small>
                        </div>
                        
                        <!-- Beheerder moet printen checkbox -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="admin_print" name="admin_print">
                            <label class="form-check-label" for="admin_print">Ik wil dat de beheerder voor mij print</label>
                            <small class="form-text text-muted d-block">
                                Vink dit aan als je wilt dat een beheerder het printen voor je uitvoert.
                            </small>
                        </div>
                        
                        <!-- File upload section - Let op: Deze sectie moet standaard zichtbaar zijn of worden weergegeven via JavaScript -->
                        <div id="file-upload-section" class="mb-4 p-3 border rounded bg-light">
                            <h6>Upload je printbestand</h6>
                            <p class="small">
                                Upload hier je 3D-bestand (.stl, .obj, .3mf, etc.). Het bestand wordt automatisch naar de beheerder gestuurd.
                            </p>
                            <div class="mb-3">
                                <input type="file" class="form-control" id="print_file" name="print_file" 
                                       accept=".stl,.obj,.3mf,.gcode,.step,.stp">
                                <small class="form-text text-muted">
                                    Maximale bestandsgrootte: 20MB. Ondersteunde formaten: STL, OBJ, 3MF, GCODE, STEP, STP
                                </small>
                            </div>
                            <div id="file-name-preview"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="comment" class="form-label">Omschrijving/Opmerkingen</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3"><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                            <small class="form-text text-muted">Optioneel. Beschrijf kort waar je de printer voor gaat gebruiken.</small>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Reservering Bevestigen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Printer informatie -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Printer Informatie</h5>
                </div>
                <img src="<?php echo htmlspecialchars($printer['foto']); ?>" 
    		class="card-img-top" 
    		alt="<?php echo htmlspecialchars($printer['Versie_Toestel']); ?>" 
    		onerror="this.outerHTML='<div class=\'card-img-top d-flex justify-content-center align-items-center bg-light\' style=\'height: 200px;\'><p class=\'text-muted mb-0\'>Geen afbeelding beschikbaar voor <?php echo htmlspecialchars($printer['Versie_Toestel']); ?></p></div>'">                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></h5>
                    <p class="card-text">
                        <strong>Software:</strong> <?php echo htmlspecialchars($printer['Software'] ?? 'Niet gespecificeerd'); ?><br>
                        <strong>Datadrager:</strong> <?php echo htmlspecialchars($printer['Datadrager'] ?? 'Niet gespecificeerd'); ?><br>
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Deze printer is nu beschikbaar voor reservering.
                    </div>
                </div>
            </div>
            
            <!-- Reserverings informatie -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Reserveringsregels</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-clock me-2 text-primary"></i>
                            Maximale reserveringsduur: 8 uur
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-calendar me-2 text-primary"></i>
                            Reserveringen mogelijk tot 30 dagen vooruit
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                            Annuleren mogelijk tot 2 uur voor starttijd
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-key me-2 text-success"></i>
                            Je ontvangt een pincode om de printer te ontgrendelen
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-hands-helping me-2 text-info"></i>
                            Hulp nodig? Vink de optie aan bij je reservering
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variabelen
    const startDateInput = document.getElementById('start_date');
    const startTimeInput = document.getElementById('start_time');
    const endDateInput = document.getElementById('end_date');
    const endTimeInput = document.getElementById('end_time');
    const reservationForm = document.getElementById('reservationForm');
    const adminPrintCheckbox = document.getElementById('admin_print');
    const printFileInput = document.getElementById('print_file');
    const fileUploadSection = document.getElementById('file-upload-section');
    
    // Toon/verberg bestandsupload sectie op basis van admin_print checkbox
    if (adminPrintCheckbox && fileUploadSection) {
        // Functie om de zichtbaarheid van het bestandsupload veld te beheren
        function toggleFileUploadSection() {
            if (adminPrintCheckbox.checked) {
                fileUploadSection.style.display = 'block';
            } else {
                fileUploadSection.style.display = 'none';
            }
        }
        
        // Voeg event listener toe voor wijzigingen in de checkbox
        adminPrintCheckbox.addEventListener('change', toggleFileUploadSection);
        
        // Initiële status instellen
        toggleFileUploadSection();
    }
    
    // Function to disable past hours on the current day
    function updateAvailableTimes() {
        if (!startDateInput || !startTimeInput) return; // Guard clause if elements don't exist
        
        const selectedDate = new Date(startDateInput.value);
        const today = new Date();
        
        // Reset all options to enabled
        Array.from(startTimeInput.options).forEach(option => {
            option.disabled = false;
        });
        
        // If selected date is today, disable past hours
        if (selectedDate.toDateString() === today.toDateString()) {
            const currentHour = today.getHours();
            
            Array.from(startTimeInput.options).forEach(option => {
                const optionHour = parseInt(option.value.split(':')[0]);
                if (optionHour <= currentHour) {
                    option.disabled = true;
                }
            });
        }
    }
    
    // Update times when date changes
    if (startDateInput) {
        startDateInput.addEventListener('change', updateAvailableTimes);
        // Initial update
        updateAvailableTimes();
    }
    
    // File upload preview
    const fileNamePreview = document.getElementById('file-name-preview');
    
    if (printFileInput && fileNamePreview) {
        printFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2) + ' MB';
                fileNamePreview.innerHTML = `
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-file-alt me-2"></i>
                        <div>
                            <strong>${fileName}</strong><br>
                            <small>${fileSize}</small>
                        </div>
                    </div>`;
            } else {
                fileNamePreview.innerHTML = '';
            }
        });
    }
    
    // Update eindtijd wanneer starttijd wijzigt
    function updateEndTime() {
        const startDate = startDateInput.value;
        const startTime = startTimeInput.value;
        
        if (startDate && startTime) {
            // Als starttijd en einddatum gelijk zijn, zorg dat eindtijd minimaal 30 min later is
            if (startDate === endDateInput.value) {
                const [startHours, startMinutes] = startTime.split(':').map(Number);
                let endHours = startHours;
                let endMinutes = startMinutes + 30;
                
                // Als minuten over 60 gaan, pas uren aan
                if (endMinutes >= 60) {
                    endMinutes -= 60;
                    endHours += 1;
                }
                
                // Formatteer uren en minuten met leading zeros
                const formattedHours = endHours.toString().padStart(2, '0');
                const formattedMinutes = endMinutes.toString().padStart(2, '0');
                
                // Alleen updaten als eindtijd eerder is dan nieuwe berekende tijd
                const currentEndTime = endTimeInput.value;
                const [currentHours, currentMinutes] = currentEndTime.split(':').map(Number);
                
                if (currentHours < endHours || (currentHours === endHours && currentMinutes < endMinutes)) {
                    endTimeInput.value = `${formattedHours}:${formattedMinutes}`;
                }
            }
        }
    }
    
    // Event listeners
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            if (startDateInput.value > endDateInput.value) {
                endDateInput.value = startDateInput.value;
            }
            updateEndTime();
        });
    }
    
    if (startTimeInput) {
        startTimeInput.addEventListener('change', updateEndTime);
    }
    
    // Form validatie
    if (reservationForm) {
        reservationForm.addEventListener('submit', function(e) {
            const startDateTime = new Date(`${startDateInput.value}T${startTimeInput.value}`);
            const endDateTime = new Date(`${endDateInput.value}T${endTimeInput.value}`);
            
            if (endDateTime <= startDateTime) {
                e.preventDefault();
                alert('De eindtijd moet na de starttijd liggen.');
                return;
            }
            
            // Check maximale reserveringsduur (8 uur)
            const diffHours = (endDateTime - startDateTime) / (1000 * 60 * 60);
            if (diffHours > 8) {
                e.preventDefault();
                alert('De maximale reserveringsduur is 8 uur. Pas je eindtijd aan.');
                return;
            }
            
            // Controleer of er een bestand is geüpload als beheerder moet printen
            if (adminPrintCheckbox.checked) {
                if (!printFileInput.files.length) {
                    e.preventDefault();
                    alert('Je hebt aangegeven dat de beheerder voor je moet printen. Upload a.u.b. een bestand.');
                    return;
                }
                
                // Controleer bestandsgrootte (max 20MB)
                const fileSize = printFileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 20) {
                    e.preventDefault();
                    alert('Het bestand is te groot. De maximale bestandsgrootte is 20MB.');
                    return;
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>