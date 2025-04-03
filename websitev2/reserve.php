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
    
    // Genereer een pincode van 6 cijfers
    $pincode = sprintf("%06d", rand(0, 999999));
    
    // Validatie
    if (empty($startDate) || empty($startTime) || empty($endDate) || empty($endTime)) {
        $error = 'Alle tijdvelden zijn verplicht.';
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
                                PRINT_START, PRINT_END, Comment, Pincode, filament_id, verbruik
                            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, NULL)
                        ");
                        
                        $stmt->execute([
                            $newReservationId,
                            $userId,
                            $printerId,
                            $startDateTime,
                            $endDateTime,
                            $comment,
                            $pincode,
                            $filamentId
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
                        
                        // Send email confirmation
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
                                            </div>
                                            ' . (!empty($comment) ? '<div class="detail-row">
                                                <span class="label">Opmerkingen:</span> ' . htmlspecialchars($comment) . '
                                            </div>' : '') . '
                                        </div>
                                        
                                        <p>Gebruik de volgende pincode om de printer te ontgrendelen:</p>
                                        
                                        <div class="pincode">' . $pincode . '</div>
                                        
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
                            $mail->AltBody = "Bevestiging van uw 3D Printer Reservering #" . $newReservationId . "\n\n" .
                                            "Printer: " . $printerDetails['Versie_Toestel'] . "\n" .
                                            "Start: " . $startDateFormatted . " om " . $startTimeFormatted . "\n" .
                                            "Einde: " . $endDateFormatted . " om " . $endTimeFormatted . "\n" .
                                            "Filament: " . $filamentName . "\n" .
                                            "Pincode: " . $pincode . "\n\n" .
                                            "Bedankt voor je reservering bij 3D Printers MaakLab VIVES.";
                            
                            $mail->send();
                            
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

include 'includes/header.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const startTimeInput = document.getElementById('start_time');
    
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
});
</script>

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
                    <form method="post" action="reserve.php?printer_id=<?php echo $printerId; ?>" id="reservationForm">
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
                <img src="assets/img/printer-<?php echo $printer['Printer_ID']; ?>.jpg" 
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
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validatie voor start- en eindtijd
    const startDateInput = document.getElementById('start_date');
    const startTimeInput = document.getElementById('start_time');
    const endDateInput = document.getElementById('end_date');
    const endTimeInput = document.getElementById('end_time');
    const reservationForm = document.getElementById('reservationForm');
    
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
            }
            
            // Check maximale reserveringsduur (8 uur)
            const diffHours = (endDateTime - startDateTime) / (1000 * 60 * 60);
            if (diffHours > 8) {
                e.preventDefault();
                alert('De maximale reserveringsduur is 8 uur. Pas je eindtijd aan.');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>