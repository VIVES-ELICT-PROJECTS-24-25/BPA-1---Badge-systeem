<?php
// Admin toegang controle
require_once 'admin.php';

// PHPMailer inclusies
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/vendor/autoload.php';

$pageTitle = 'Reservering Details - 3D Printer Reserveringssysteem';
$currentPage = 'admin-reservations';

// Haal reserveringsgegevens op
$reservationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 'true';

if ($reservationId <= 0) {
    header('Location: reservations.php');
    exit;
}

// Functie om e-mail te verzenden
function sendNotificationEmail($user, $reservation, $type = 'update') {
    $mail = new PHPMailer(true);
    
    try {
        // Server instellingen
        $mail->isSMTP();
        $mail->Host       = 'smtp-auth.mailprotect.be'; // Vervang door je SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'reservaties@3dprintersmaaklabvives.be'; // Vervang door je SMTP gebruikersnaam
        $mail->Password   = '9ke53d3w2ZP64ik76qHe';         // Vervang door je SMTP wachtwoord
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Ontvangers
        $mail->setFrom('reservaties@3dprintersmaaklabvives.be', '3D Printer Reserveringssysteem');
        $mail->addAddress($user['Emailadres'], $user['Voornaam'] . ' ' . $user['Naam']);

        // Inhoud
        $mail->isHTML(true);
        
        if ($type == 'update') {
            $mail->Subject = 'Wijziging in je 3D printer reservering';
            $mail->Body    = '
            <html>
            <body>
                <h2>Wijziging in je 3D printer reservering</h2>
                <p>Beste ' . htmlspecialchars($user['Voornaam']) . ',</p>
                <p>Er is een wijziging aangebracht in je reservering met ID #' . $reservation['Reservatie_ID'] . '.</p>
                <h3>Nieuwe reserveringsdetails:</h3>
                <ul>
                    <li><strong>Printer:</strong> ' . htmlspecialchars($reservation['Versie_Toestel']) . '</li>
                    <li><strong>Starttijd:</strong> ' . date('d-m-Y H:i', strtotime($reservation['PRINT_START'])) . '</li>
                    <li><strong>Eindtijd:</strong> ' . date('d-m-Y H:i', strtotime($reservation['PRINT_END'])) . '</li>
                    <li><strong>Pincode:</strong> ' . htmlspecialchars($reservation['Pincode']) . '</li>
                </ul>
                <p>Controleer de details en neem contact op als je vragen hebt.</p>
                <p>Met vriendelijke groet,<br>3D Printer Reserveringsteam</p>
            </body>
            </html>';
        } elseif ($type == 'cancel') {
            $mail->Subject = 'Annulering van je 3D printer reservering';
            $mail->Body    = '
            <html>
            <body>
                <h2>Annulering van je 3D printer reservering</h2>
                <p>Beste ' . htmlspecialchars($user['Voornaam']) . ',</p>
                <p>Je reservering met ID #' . $reservation['Reservatie_ID'] . ' is geannuleerd door een beheerder.</p>
                <p>Als je vragen hebt over deze annulering, neem dan contact op met het 3D printer team.</p>
                <p>Met vriendelijke groet,<br>3D Printer Reserveringsteam</p>
            </body>
            </html>';
        }
        
        $mail->AltBody = strip_tags($mail->Body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Fout bij het verzenden van e-mail: {$mail->ErrorInfo}";
    }
}

// Haal reserveringsdetails op
try {
    $stmt = $conn->prepare("
        SELECT r.*, p.Versie_Toestel, p.netwerkadres, p.Software, p.Status as PrinterStatus,
               u.Voornaam, u.Naam, u.Emailadres, u.Telefoon, u.Type as UserType,
               f.Type AS filament_type, f.Kleur AS filament_kleur,
               l.Locatie as Lokaalnaam
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        JOIN User u ON r.User_ID = u.User_ID
        LEFT JOIN Filament f ON r.filament_id = f.id
        LEFT JOIN Lokalen l ON p.Printer_ID = l.id
        WHERE r.Reservatie_ID = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header('Location: reservations.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Fout bij het ophalen van reserveringsgegevens: ' . $e->getMessage();
}

// Haal beschikbare printers op voor het wijzigingsformulier
try {
    $stmt = $conn->prepare("SELECT Printer_ID, Versie_Toestel FROM Printer ORDER BY Versie_Toestel");
    $stmt->execute();
    $printers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Fout bij het ophalen van printers: ' . $e->getMessage();
    $printers = [];
}

// Haal beschikbare filamenten op
try {
    $stmt = $conn->prepare("SELECT id, Type, Kleur FROM Filament ORDER BY Type, Kleur");
    $stmt->execute();
    $filaments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Fout bij het ophalen van filamenten: ' . $e->getMessage();
    $filaments = [];
}

// Update de reservering
if (isset($_POST['update_reservation'])) {
    try {
        $printStart = $_POST['print_start'];
        $printEnd = $_POST['print_end'];
        $printerId = isset($_POST['printer_id']) ? intval($_POST['printer_id']) : $reservation['Printer_ID'];
        $filamentId = !empty($_POST['filament_id']) ? intval($_POST['filament_id']) : null;
        $comment = $_POST['comment'] ?? '';
        $verbruik = !empty($_POST['verbruik']) ? floatval($_POST['verbruik']) : null;
        
        // Validatie
        if (strtotime($printEnd) <= strtotime($printStart)) {
            $error = 'De eindtijd moet na de starttijd liggen.';
        } else {
            // Controleren op overlappende reserveringen als printer is gewijzigd
            if ($printerId != $reservation['Printer_ID']) {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count FROM Reservatie 
                    WHERE Printer_ID = ? 
                    AND Reservatie_ID != ?
                    AND (
                        (PRINT_START <= ? AND PRINT_END >= ?) OR
                        (PRINT_START <= ? AND PRINT_END >= ?) OR
                        (PRINT_START >= ? AND PRINT_END <= ?)
                    )
                ");
                $stmt->execute([
                    $printerId, 
                    $reservationId,
                    $printStart, $printStart,
                    $printEnd, $printEnd,
                    $printStart, $printEnd
                ]);
                $overlap = $stmt->fetch();
                
                if ($overlap['count'] > 0) {
                    $error = 'Er is een conflict met een andere reservering op de geselecteerde printer en tijdsperiode.';
                }
            } else {
                // Controleren op overlappende reserveringen met aangepaste tijden
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count FROM Reservatie 
                    WHERE Printer_ID = ? 
                    AND Reservatie_ID != ?
                    AND (
                        (PRINT_START <= ? AND PRINT_END >= ?) OR
                        (PRINT_START <= ? AND PRINT_END >= ?) OR
                        (PRINT_START >= ? AND PRINT_END <= ?)
                    )
                ");
                $stmt->execute([
                    $reservation['Printer_ID'], 
                    $reservationId,
                    $printStart, $printStart,
                    $printEnd, $printEnd,
                    $printStart, $printEnd
                ]);
                $overlap = $stmt->fetch();
                
                if ($overlap['count'] > 0) {
                    $error = 'Er is een conflict met een andere reservering op dezelfde printer en tijdsperiode.';
                }
            }
            
            if (empty($error)) {
                // Oude reserveringsgegevens bewaren voor e-mail
                $oldReservation = $reservation;
                
                // Update de reservering
                $stmt = $conn->prepare("
                    UPDATE Reservatie
                    SET PRINT_START = ?, PRINT_END = ?, Printer_ID = ?,
                        filament_id = ?, Comment = ?, verbruik = ?
                    WHERE Reservatie_ID = ?
                ");
                $stmt->execute([
                    $printStart,
                    $printEnd,
                    $printerId,
                    $filamentId,
                    $comment,
                    $verbruik,
                    $reservationId
                ]);
                
                // Update printer status indien nodig
                if ($printerId != $oldReservation['Printer_ID']) {
                    // Oorspronkelijke printer weer op beschikbaar zetten
                    $stmt = $conn->prepare("
                        UPDATE Printer 
                        SET Status = 'beschikbaar', LAATSTE_STATUS_CHANGE = NOW() 
                        WHERE Printer_ID = ?
                    ");
                    $stmt->execute([$oldReservation['Printer_ID']]);
                    
                    // Nieuwe printer op in_gebruik zetten als de reservering actief is
                    $now = new DateTime();
                    $startTime = new DateTime($printStart);
                    $endTime = new DateTime($printEnd);
                    
                    if ($now >= $startTime && $now <= $endTime) {
                        $stmt = $conn->prepare("
                            UPDATE Printer 
                            SET Status = 'in_gebruik', LAATSTE_STATUS_CHANGE = NOW() 
                            WHERE Printer_ID = ?
                        ");
                        $stmt->execute([$printerId]);
                    }
                }
                
                // Haal de bijgewerkte reserveringsgegevens op
                $stmt = $conn->prepare("
                    SELECT r.*, p.Versie_Toestel
                    FROM Reservatie r
                    JOIN Printer p ON r.Printer_ID = p.Printer_ID
                    WHERE r.Reservatie_ID = ?
                ");
                $stmt->execute([$reservationId]);
                $updatedReservation = $stmt->fetch();
                
                // Stuur e-mail naar gebruiker
                $emailResult = sendNotificationEmail([
                    'Emailadres' => $reservation['Emailadres'],
                    'Voornaam' => $reservation['Voornaam'],
                    'Naam' => $reservation['Naam']
                ], $updatedReservation, 'update');
                
                if ($emailResult !== true) {
                    $success = 'Reservering bijgewerkt, maar er was een probleem met het verzenden van de e-mail: ' . $emailResult;
                } else {
                    $success = 'Reservering succesvol bijgewerkt en notificatie e-mail verzonden.';
                }
                
                // Refresh de pagina om de bijgewerkte gegevens te tonen
                header("Location: reservation-detail.php?id=$reservationId&success=updated");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = 'Er is een fout opgetreden bij het bijwerken: ' . $e->getMessage();
    }
}

// Annuleren/verwijderen van de reservering
if (isset($_POST['cancel_reservation'])) {
    try {
        // Bewaar reserveringsgegevens voor e-mail
        $cancelledReservation = $reservation;
        
        // Verwijder de reservering
        $stmt = $conn->prepare("DELETE FROM Reservatie WHERE Reservatie_ID = ?");
        $stmt->execute([$reservationId]);
        
        // Printer status updaten
        $stmt = $conn->prepare("
            UPDATE Printer 
            SET Status = 'beschikbaar', LAATSTE_STATUS_CHANGE = NOW() 
            WHERE Printer_ID = ?
        ");
        $stmt->execute([$reservation['Printer_ID']]);
        
        // Stuur e-mail naar gebruiker
        $emailResult = sendNotificationEmail([
            'Emailadres' => $reservation['Emailadres'],
            'Voornaam' => $reservation['Voornaam'],
            'Naam' => $reservation['Naam']
        ], $cancelledReservation, 'cancel');
        
        if ($emailResult !== true) {
            $success = 'Reservering geannuleerd, maar er was een probleem met het verzenden van de e-mail: ' . $emailResult;
        } else {
            $success = 'Reservering succesvol geannuleerd en notificatie e-mail verzonden.';
        }
        
        // Redirect naar reserveringen overzicht
        header('Location: reservations.php?success=cancelled');
        exit;
        
    } catch (PDOException $e) {
        $error = 'Er is een fout opgetreden bij het annuleren: ' . $e->getMessage();
    }
}

// Controleer of success parameter aanwezig is in de URL
if (isset($_GET['success']) && $_GET['success'] == 'updated') {
    $success = 'Reservering succesvol bijgewerkt en notificatie e-mail verzonden.';
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr voor datum/tijd selectie -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="d-flex justify-content-center mb-4">
                        <a href="../index.php" class="text-white text-decoration-none">
                            <span class="fs-4">3D Printer Admin</span>
                        </a>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                Gebruikers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="printers.php">
                                <i class="fas fa-print me-2"></i>
                                Printers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reservations.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Reserveringen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="filaments.php">
                                <i class="fas fa-layer-group me-2"></i>
                                Filament
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Terug naar site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Uitloggen
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reservering Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="reservations.php">Reserveringen</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Details</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Reservering details -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                                <h5 class="mb-0">
                                    Reservering #<?php echo $reservation['Reservatie_ID']; ?>
                                </h5>
                                <span>
                                    <?php 
                                    $now = new DateTime();
                                    $startTime = new DateTime($reservation['PRINT_START']);
                                    $endTime = new DateTime($reservation['PRINT_END']);
                                    
                                    if ($now >= $startTime && $now <= $endTime) {
                                        echo '<span class="badge bg-success">Actief</span>';
                                    } elseif ($startTime > $now) {
                                        echo '<span class="badge bg-info">Aankomend</span>';
                                    } elseif ($endTime < $now) {
                                        echo '<span class="badge bg-secondary">Afgelopen</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (!$edit_mode): ?>
                                <!-- WEERGAVE MODUS -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>Reserveringsperiode</h6>
                                        <p>
                                            <strong>Start:</strong> <?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_START'])); ?><br>
                                            <strong>Einde:</strong> <?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_END'])); ?>
                                        </p>
                                        <p>
                                            <strong>Duur:</strong> 
                                            <?php 
                                                $start = new DateTime($reservation['PRINT_START']);
                                                $end = new DateTime($reservation['PRINT_END']);
                                                $interval = $start->diff($end);
                                                
                                                $duration = '';
                                                if ($interval->d > 0) {
                                                    $duration .= $interval->d . ' dag(en), ';
                                                }
                                                $duration .= $interval->h . ' uur, ' . $interval->i . ' minuten';
                                                echo $duration;
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Printer</h6>
                                        <p>
                                            <strong>Model:</strong> <?php echo htmlspecialchars($reservation['Versie_Toestel']); ?><br>
                                            <strong>Locatie:</strong> <?php echo htmlspecialchars($reservation['Lokaalnaam'] ?? 'Niet gespecificeerd'); ?><br>
                                            <strong>Netwerkadres:</strong> <?php echo htmlspecialchars($reservation['netwerkadres'] ?? 'Niet gespecificeerd'); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Pincode sectie -->
                                <div class="mb-4">
                                    <h6>Toegangscode</h6>
                                    <div class="alert alert-success">
                                        <strong>Pincode voor de printer:</strong> 
                                        <span class="fs-4"><?php echo htmlspecialchars($reservation['Pincode']); ?></span>
                                        <p class="small mb-0 mt-2">Deze pincode wordt gebruikt om toegang te krijgen tot de printer tijdens het gereserveerde tijdslot.</p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($reservation['filament_type'])): ?>
                                <div class="mb-4">
                                    <h6>Materiaal</h6>
                                    <p>
                                        <strong>Filament:</strong> <?php echo htmlspecialchars($reservation['filament_type']); ?><br>
                                        <strong>Kleur:</strong> <?php echo htmlspecialchars($reservation['filament_kleur']); ?>
                                        <?php if (!empty($reservation['verbruik'])): ?>
                                            <br><strong>Verbruik:</strong> <?php echo htmlspecialchars($reservation['verbruik']); ?> gram
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($reservation['Comment'])): ?>
                                <div class="mb-4">
                                    <h6>Omschrijving</h6>
                                    <p><?php echo nl2br(htmlspecialchars($reservation['Comment'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <div>
                                        <a href="printers.php?id=<?php echo $reservation['Printer_ID']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-print me-1"></i> Printer Beheer
                                        </a>
                                        <a href="users.php?id=<?php echo $reservation['User_ID']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-user me-1"></i> Gebruiker Beheer
                                        </a>
                                    </div>
                                    
                                    <div>
                                        <a href="reservation-detail.php?id=<?php echo $reservationId; ?>&edit=true" class="btn btn-warning">
                                            <i class="fas fa-edit me-1"></i> Wijzigen
                                        </a>
                                        <form method="post" onsubmit="return confirm('Weet je zeker dat je deze reservering wilt annuleren/verwijderen?');" class="d-inline">
                                            <input type="hidden" name="cancel_reservation" value="1">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash me-1"></i> Verwijderen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <!-- WIJZIG MODUS -->
                                <form method="post" action="reservation-detail.php?id=<?php echo $reservationId; ?>">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6>Reserveringsperiode</h6>
                                            <div class="mb-3">
                                                <label for="print_start" class="form-label">Starttijd</label>
                                                <input type="text" class="form-control flatpickr-datetime" id="print_start" name="print_start" 
                                                       value="<?php echo date('Y-m-d H:i', strtotime($reservation['PRINT_START'])); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="print_end" class="form-label">Eindtijd</label>
                                                <input type="text" class="form-control flatpickr-datetime" id="print_end" name="print_end" 
                                                       value="<?php echo date('Y-m-d H:i', strtotime($reservation['PRINT_END'])); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Printer</h6>
                                            <div class="mb-3">
                                                <label for="printer_id" class="form-label">Selecteer Printer</label>
                                                <select class="form-select" id="printer_id" name="printer_id">
                                                    <?php foreach ($printers as $printer): ?>
                                                        <option value="<?php echo $printer['Printer_ID']; ?>" 
                                                                <?php echo ($printer['Printer_ID'] == $reservation['Printer_ID']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($printer['Versie_Toestel']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h6>Pincode</h6>
                                        <div class="alert alert-secondary">
                                            <p class="mb-0">Pincode: <strong><?php echo htmlspecialchars($reservation['Pincode']); ?></strong></p>
                                            <p class="small mb-0 mt-2">De pincode blijft hetzelfde. Alleen de beheerder kan deze bekijken.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h6>Materiaal</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="filament_id" class="form-label">Filament</label>
                                                    <select class="form-select" id="filament_id" name="filament_id">
                                                        <option value="">-- Geen filament --</option>
                                                        <?php foreach ($filaments as $filament): ?>
                                                            <option value="<?php echo $filament['id']; ?>" 
                                                                    <?php echo ($filament['id'] == $reservation['filament_id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($filament['Type'] . ' - ' . $filament['Kleur']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="verbruik" class="form-label">Verbruik (gram)</label>
                                                    <input type="number" class="form-control" id="verbruik" name="verbruik" 
                                                           value="<?php echo $reservation['verbruik'] ?? ''; ?>" step="0.1" min="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="comment" class="form-label">Omschrijving</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3"><?php echo htmlspecialchars($reservation['Comment'] ?? ''); ?></textarea>
                                        <div class="form-text">Optionele beschrijving voor deze reservering.</div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="reservation-detail.php?id=<?php echo $reservationId; ?>" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i> Annuleren
                                        </a>
                                        <button type="submit" name="update_reservation" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i> Wijzigingen Opslaan
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Gebruikersgegevens -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Gebruikersgegevens</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6>Persoonlijke Informatie</h6>
                                    <p>
                                        <strong>Naam:</strong> <?php echo htmlspecialchars($reservation['Voornaam'] . ' ' . $reservation['Naam']); ?><br>
                                        <strong>E-mail:</strong> <a href="mailto:<?php echo htmlspecialchars($reservation['Emailadres']); ?>"><?php echo htmlspecialchars($reservation['Emailadres']); ?></a><br>
                                        <?php if (!empty($reservation['Telefoon'])): ?>
                                            <strong>Telefoon:</strong> <?php echo htmlspecialchars($reservation['Telefoon']); ?><br>
                                        <?php endif; ?>
                                        <strong>Type gebruiker:</strong> <?php echo htmlspecialchars($reservation['UserType']); ?>
                                    </p>
                                </div>
                                
                                <?php
                                // Haal andere reserveringen van deze gebruiker op
                                try {
                                    $stmt = $conn->prepare("
                                        SELECT r.Reservatie_ID, r.PRINT_START, r.PRINT_END, p.Versie_Toestel 
                                        FROM Reservatie r
                                        JOIN Printer p ON r.Printer_ID = p.Printer_ID
                                        WHERE r.User_ID = ? AND r.Reservatie_ID != ?
                                        ORDER BY r.PRINT_START DESC
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$reservation['User_ID'], $reservationId]);
                                    $otherReservations = $stmt->fetchAll();
                                    
                                    if (count($otherReservations) > 0):
                                ?>
                                <div class="mb-3">
                                    <h6>Andere Reserveringen</h6>
                                    <div class="list-group small">
                                        <?php foreach ($otherReservations as $otherRes): ?>
                                            <a href="reservation-detail.php?id=<?php echo $otherRes['Reservatie_ID']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <span><?php echo htmlspecialchars($otherRes['Versie_Toestel']); ?></span>
                                                    <small>#<?php echo $otherRes['Reservatie_ID']; ?></small>
                                                </div>
                                                <small>
                                                    <?php echo date('d-m-Y H:i', strtotime($otherRes['PRINT_START'])); ?> tot 
                                                    <?php echo date('d-m-Y H:i', strtotime($otherRes['PRINT_END'])); ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php 
                                    endif;
                                } catch (PDOException $e) {
                                    // Stilletjes negeren
                                }
                                ?>
                                
                                <a href="mailto:<?php echo htmlspecialchars($reservation['Emailadres']); ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-envelope me-1"></i> E-mail Versturen
                                </a>
                            </div>
                        </div>
                        
                        <!-- Kalender Overzicht -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">Printer Beschikbaarheid</h5>
                            </div>
                            <div class="card-body">
                                <p class="small">Andere reserveringen voor dezelfde printer:</p>
                                <div id="printer-reservations" class="mb-3">
                                    <?php
                                    // Haal andere reserveringen voor dezelfde printer op
                                    try {
                                        $stmt = $conn->prepare("
                                            SELECT r.Reservatie_ID, r.PRINT_START, r.PRINT_END, 
                                                  u.Voornaam, u.Naam
                                            FROM Reservatie r
                                            JOIN User u ON r.User_ID = u.User_ID
                                            WHERE r.Printer_ID = ? AND r.Reservatie_ID != ?
                                            AND r.PRINT_END >= NOW()
                                            ORDER BY r.PRINT_START ASC
                                            LIMIT 5
                                        ");
                                        $stmt->execute([$reservation['Printer_ID'], $reservationId]);
                                        $printerReservations = $stmt->fetchAll();
                                        
                                        if (count($printerReservations) > 0):
                                    ?>
                                    <div class="list-group small">
                                        <?php foreach ($printerReservations as $printerRes): ?>
                                            <a href="reservation-detail.php?id=<?php echo $printerRes['Reservatie_ID']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <span><?php echo htmlspecialchars($printerRes['Voornaam'] . ' ' . $printerRes['Naam']); ?></span>
                                                    <small>#<?php echo $printerRes['Reservatie_ID']; ?></small>
                                                </div>
                                                <small>
                                                    <?php echo date('d-m-Y H:i', strtotime($printerRes['PRINT_START'])); ?> tot 
                                                    <?php echo date('d-m-Y H:i', strtotime($printerRes['PRINT_END'])); ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                        <div class="alert alert-success small">
                                            Er zijn geen andere reserveringen gepland voor deze printer.
                                        </div>
                                    <?php 
                                        endif;
                                    } catch (PDOException $e) {
                                        // Stilletjes negeren
                                    }
                                    ?>
                                </div>
                                
                                <a href="printers.php?id=<?php echo $reservation['Printer_ID']; ?>" class="btn btn-outline-success w-100">
                                    <i class="fas fa-calendar-alt me-1"></i> Bekijk Alle Printer Reserveringen
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr Script -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/nl.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiseer flatpickr voor datum/tijd selectie met Nederlandse taal
            flatpickr(".flatpickr-datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: "nl",
                minuteIncrement: 15
            });
            
            // Toggle sidebar op mobiele apparaten
            const toggleSidebar = document.getElementById('sidebarToggle');
            if (toggleSidebar) {
                toggleSidebar.addEventListener('click', function() {
                    document.body.classList.toggle('sb-sidenav-toggled');
                });
            }
        });
    </script>
</body>
</html>