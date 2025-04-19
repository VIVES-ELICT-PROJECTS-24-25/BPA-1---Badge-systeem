<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'reservation-details';
$pageTitle = 'Reservering Details - 3D Printer Reserveringssysteem';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['User_ID'];
$reservationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

// Check for success message in URL
if (isset($_GET['success']) && $_GET['success'] === 'created') {
    $success = 'Je reservering is succesvol aangemaakt!';
}

// Haal reserveringsdetails op
if ($reservationId > 0) {
    // Voor beheerders alle reserveringen toegankelijk, voor normale gebruikers alleen eigen reserveringen
    if (isset($_SESSION['Type']) && $_SESSION['Type'] === 'beheerder') {
        $stmt = $conn->prepare("
            SELECT r.*, p.Versie_Toestel, p.netwerkadres, p.Software, p.Status as PrinterStatus,
                   u.Voornaam, u.Naam, u.Emailadres, u.Telefoon,
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
    } else {
        $stmt = $conn->prepare("
            SELECT r.*, p.Versie_Toestel, p.netwerkadres, p.Software, p.Status as PrinterStatus,
                   u.Voornaam, u.Naam, u.Emailadres, u.Telefoon,
                   f.Type AS filament_type, f.Kleur AS filament_kleur,
                   l.Locatie as Lokaalnaam
            FROM Reservatie r
            JOIN Printer p ON r.Printer_ID = p.Printer_ID
            JOIN User u ON r.User_ID = u.User_ID
            LEFT JOIN Filament f ON r.filament_id = f.id
            LEFT JOIN Lokalen l ON p.Printer_ID = l.id
            WHERE r.Reservatie_ID = ? AND r.User_ID = ?
        ");
        $stmt->execute([$reservationId, $userId]);
    }
    
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header('Location: reservations.php');
        exit;
    }
} else {
    header('Location: reservations.php');
    exit;
}

// Annuleren van de reservering
if (isset($_POST['cancel_reservation'])) {
    // Controleer of de reservering nog niet is begonnen
    $startTime = new DateTime($reservation['PRINT_START']);
    $now = new DateTime();
    
    if ($startTime > $now) {
        try {
            // Verwijder de reservering (of je kunt een andere strategie bedenken)
            $stmt = $conn->prepare("DELETE FROM Reservatie WHERE Reservatie_ID = ?");
            $stmt->execute([$reservationId]);
            
            // Printer status updaten
            $stmt = $conn->prepare("
                UPDATE Printer 
                SET Status = 'beschikbaar', LAATSTE_STATUS_CHANGE = NOW() 
                WHERE Printer_ID = ?
            ");
            $stmt->execute([$reservation['Printer_ID']]);
            
            $success = 'Je reservering is succesvol geannuleerd.';
            
            // Redirect naar reserveringen overzicht
            header('Location: reservations.php?success=cancelled');
            exit;
            
        } catch (PDOException $e) {
            $error = 'Er is een fout opgetreden bij het annuleren: ' . $e->getMessage();
        }
    } else {
        $error = 'Deze reservering kan niet worden geannuleerd. Alleen toekomstige reserveringen kunnen worden geannuleerd.';
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Reservering Details</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="reservations.php">Mijn Reserveringen</a></li>
                <li class="breadcrumb-item active" aria-current="page">Details</li>
            </ol>
        </nav>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Reservering details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                    <h5 class="mb-0">
                        Reservering #<?php echo $reservation['Reservatie_ID']; ?>
                    </h5>
                    <span><?php echo date('d-m-Y', strtotime($reservation['DATE_TIME_RESERVATIE'])); ?></span>
                </div>
                <div class="card-body">
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
                            <strong>Je pincode voor de printer:</strong> 
                            <span class="fs-4"><?php echo htmlspecialchars($reservation['Pincode']); ?></span>
                            <p class="small mb-0 mt-2">Gebruik deze pincode om toegang te krijgen tot de printer tijdens je gereserveerde tijdslot.</p>
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
                        <a href="printer-details.php?id=<?php echo $reservation['Printer_ID']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-print me-1"></i> Printer Details
                        </a>
                        
                        <?php 
                        $startTime = new DateTime($reservation['PRINT_START']);
                        $now = new DateTime();
                        if ($startTime > $now): 
                        ?>
                            <form method="post" onsubmit="return confirm('Weet je zeker dat je deze reservering wilt annuleren?');">
                                <input type="hidden" name="cancel_reservation" value="1">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times-circle me-1"></i> Annuleren
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <!-- Gebruiker informatie -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Gebruiker Informatie</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($reservation['Voornaam'] . '+' . $reservation['Naam']); ?>&background=random&size=64" class="rounded-circle me-3" alt="User Image">
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($reservation['Voornaam'] . ' ' . $reservation['Naam']); ?></h6>
                            <small class="text-muted"><?php echo ucfirst($reservation['Type'] ?? 'Gebruiker'); ?></small>
                        </div>
                    </div>
                    <p class="mb-2">
                        <i class="fas fa-envelope text-secondary me-2"></i> <?php echo htmlspecialchars($reservation['Emailadres']); ?>
                    </p>
                    <?php if (!empty($reservation['Telefoon'])): ?>
                    <p class="mb-0">
                        <i class="fas fa-phone text-secondary me-2"></i> <?php echo htmlspecialchars($reservation['Telefoon']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Printer status -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Printer Status</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Huidige status:</span>
                        <span class="badge bg-<?php 
                            if ($reservation['PrinterStatus'] === 'beschikbaar') echo 'success';
                            elseif ($reservation['PrinterStatus'] === 'onderhoud') echo 'warning';
                            elseif ($reservation['PrinterStatus'] === 'in_gebruik') echo 'primary';
                            else echo 'danger';
                        ?> py-2 px-3">
                            <?php 
                                if ($reservation['PrinterStatus'] === 'beschikbaar') echo 'Beschikbaar';
                                elseif ($reservation['PrinterStatus'] === 'onderhoud') echo 'In onderhoud';
                                elseif ($reservation['PrinterStatus'] === 'in_gebruik') echo 'In gebruik';
                                else echo 'Niet beschikbaar';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reserveringsstatistieken -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Reserveringsdetails</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Reserveringsnummer
                            <span class="badge bg-primary rounded-pill">#<?php echo $reservation['Reservatie_ID']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Aangemaakt op
                            <span><?php echo date('d-m-Y H:i', strtotime($reservation['DATE_TIME_RESERVATIE'])); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Printer ID
                            <span class="badge bg-secondary rounded-pill">#<?php echo $reservation['Printer_ID']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>