<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

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
if ($printerId > 0) {
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
} else {
    header('Location: printers.php');
    exit;
}

// Haal alle beschikbare filamenten op
$stmt = $conn->query("SELECT * FROM Filament ORDER BY Type, Kleur");
$filamenten = $stmt->fetchAll();

// Verwerk reserveringsformulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $filamentId = isset($_POST['filament_id']) && !empty($_POST['filament_id']) ? intval($_POST['filament_id']) : null;
    $comment = trim($_POST['comment'] ?? '');
    
    // Genereer een pincode van 4 cijfers
    $pincode = sprintf("%04d", rand(0, 9999));
    
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
                    
                    // Update printer status
                    $stmt = $conn->prepare("UPDATE Printer SET Status = 'in_gebruik', LAATSTE_STATUS_CHANGE = NOW() WHERE Printer_ID = ?");
                    $stmt->execute([$printerId]);
                    
                    $success = 'Je reservering is succesvol aangemaakt!';
                    
                    // Redirect naar reservering details
                    header("Location: reservation-details.php?id=" . $newReservationId . "&success=created");
                    exit;
                    
                } catch (PDOException $e) {
                    $error = 'Er is een fout opgetreden bij het maken van je reservering: ' . $e->getMessage();
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date');
    const startTimeInput = document.getElementById('start_time');
    
    // Function to disable past hours on the current day
    function updateAvailableTimes() {
        const selectedDate = new Date(dateInput.value);
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
    dateInput.addEventListener('change', updateAvailableTimes);
    
    // Initial update
    updateAvailableTimes();
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
                <img src="assets/img/printer-<?php echo $printer['Printer_ID']; ?>.jpg" class="card-img-top" alt="<?php echo htmlspecialchars($printer['Versie_Toestel']); ?>" onerror="this.src='assets/img/printer-default.jpg'">
                <div class="card-body">
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
    startDateInput.addEventListener('change', function() {
        if (startDateInput.value > endDateInput.value) {
            endDateInput.value = startDateInput.value;
        }
        updateEndTime();
    });
    
    startTimeInput.addEventListener('change', updateEndTime);
    
    // Form validatie
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
});
</script>

<?php include 'includes/footer.php'; ?>