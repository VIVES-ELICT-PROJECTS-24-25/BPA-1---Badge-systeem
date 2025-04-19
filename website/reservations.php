<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'reservations';
$pageTitle = 'Mijn Reserveringen - 3D Printer Reserveringssysteem';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['User_ID'];
$error = '';
$success = '';

// Success message als een reservering is geannuleerd
if (isset($_GET['success']) && $_GET['success'] === 'cancelled') {
    $success = 'Je reservering is succesvol geannuleerd.';
}

// Annuleren van een reservering indien gevraagd
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $reserveringId = $_GET['cancel'];
    
    // Controleer of de reservering van deze gebruiker is
    $stmt = $conn->prepare("
        SELECT Reservatie_ID, PRINT_START 
        FROM Reservatie 
        WHERE Reservatie_ID = ? AND User_ID = ?
    ");
    $stmt->execute([$reserveringId, $userId]);
    $reservering = $stmt->fetch();
    
    if ($reservering) {
        // Controleer of de reservering nog niet is begonnen
        $startTijd = new DateTime($reservering['PRINT_START']);
        $nu = new DateTime();
        
        if ($startTijd > $nu) {
            try {
                // Verwijder de reservering
                $stmt = $conn->prepare("DELETE FROM Reservatie WHERE Reservatie_ID = ?");
                $stmt->execute([$reserveringId]);
                
                // Printer status updaten
                $stmt = $conn->prepare("
                    UPDATE Printer 
                    SET Status = 'beschikbaar', LAATSTE_STATUS_CHANGE = NOW() 
                    WHERE Printer_ID = (
                        SELECT Printer_ID FROM Reservatie WHERE Reservatie_ID = ?
                    )
                ");
                $stmt->execute([$reserveringId]);
                
                $success = 'Je reservering is succesvol geannuleerd.';
            } catch (PDOException $e) {
                $error = 'Er is een fout opgetreden bij het annuleren: ' . $e->getMessage();
            }
        } else {
            $error = 'Deze reservering kan niet worden geannuleerd. Alleen toekomstige reserveringen kunnen worden geannuleerd.';
        }
    } else {
        $error = 'Reservering niet gevonden of je hebt geen toegang tot deze reservering.';
    }
}

// Haal alle reserveringen van de gebruiker op
try {
    $stmt = $conn->prepare("
        SELECT r.Reservatie_ID, r.Printer_ID, r.DATE_TIME_RESERVATIE, r.PRINT_START, r.PRINT_END, 
               r.Comment, r.Pincode, r.verbruik, p.Versie_Toestel, p.Status as PrinterStatus,
               f.Type AS filament_type, f.Kleur AS filament_kleur
        FROM Reservatie r
        JOIN Printer p ON r.Printer_ID = p.Printer_ID
        LEFT JOIN Filament f ON r.filament_id = f.id
        WHERE r.User_ID = ?
        ORDER BY r.PRINT_START DESC
    ");
    $stmt->execute([$userId]);
    $reserveringen = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Er is een fout opgetreden bij het ophalen van je reserveringen: ' . $e->getMessage();
    $reserveringen = [];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Mijn Reserveringen</h1>
        <a href="printers.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nieuwe Reservering
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (empty($reserveringen)): ?>
        <div class="alert alert-info">
            <h4 class="alert-heading">Geen reserveringen gevonden</h4>
            <p>Je hebt nog geen reserveringen gemaakt. Bekijk de beschikbare printers om een reservering te maken.</p>
            <div class="mt-3">
                <a href="printers.php" class="btn btn-info">Bekijk Beschikbare Printers</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Tabs voor organisatie van reserveringen -->
        <ul class="nav nav-tabs mb-4" id="reservationsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="true">Aankomende</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="false">Afgelopen</button>
            </li>
        </ul>
        
        <div class="tab-content" id="reservationsTabsContent">
            <!-- Aankomende reserveringen -->
            <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php 
                    $hasUpcoming = false;
                    $now = new DateTime();
                    
                    foreach ($reserveringen as $reservering):
                        $endTime = new DateTime($reservering['PRINT_END']);
                        
                        if ($endTime > $now):
                            $hasUpcoming = true;
                    ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <span class="badge bg-<?php 
                                            if ($reservering['PrinterStatus'] == 'beschikbaar') echo 'success';
                                            elseif ($reservering['PrinterStatus'] == 'in_gebruik') echo 'primary';
                                            else echo 'secondary';
                                        ?>">
                                            <?php 
                                                if ($reservering['PrinterStatus'] == 'beschikbaar') echo 'Beschikbaar';
                                                elseif ($reservering['PrinterStatus'] == 'in_gebruik') echo 'In gebruik';
                                                else echo ucfirst($reservering['PrinterStatus']);
                                            ?>
                                        </span>
                                    </h5>
                                    <small>Reservering #<?php echo $reservering['Reservatie_ID']; ?></small>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($reservering['Versie_Toestel']); ?></h5>
                                    <div class="mb-3">
                                        <div><strong>Gereserveerd op:</strong> <?php echo date('d-m-Y H:i', strtotime($reservering['DATE_TIME_RESERVATIE'])); ?></div>
                                        <div class="text-primary"><strong>Start:</strong> <?php echo date('d-m-Y H:i', strtotime($reservering['PRINT_START'])); ?></div>
                                        <div class="text-primary"><strong>Einde:</strong> <?php echo date('d-m-Y H:i', strtotime($reservering['PRINT_END'])); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($reservering['filament_type'])): ?>
                                    <div class="mb-3">
                                        <div><strong>Filament:</strong> <?php echo htmlspecialchars($reservering['filament_type']); ?></div>
                                        <div><strong>Kleur:</strong> <?php echo htmlspecialchars($reservering['filament_kleur']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="alert alert-success">
                                        <strong>Pincode:</strong> <?php echo htmlspecialchars($reservering['Pincode']); ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between">
                                        <a href="reservation-details.php?id=<?php echo $reservering['Reservatie_ID']; ?>" class="btn btn-outline-primary">Details</a>
                                        
                                        <?php 
                                        $startTime = new DateTime($reservering['PRINT_START']);
                                        if ($startTime > $now): 
                                        ?>
                                            <a href="reservations.php?cancel=<?php echo $reservering['Reservatie_ID']; ?>" 
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Weet je zeker dat je deze reservering wilt annuleren?');">
                                                Annuleren
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    
                    if (!$hasUpcoming):
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                Je hebt geen aankomende reserveringen.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Afgelopen reserveringen -->
            <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php 
                    $hasPast = false;
                    
                    foreach ($reserveringen as $reservering):
                        $endTime = new DateTime($reservering['PRINT_END']);
                        
                        if ($endTime <= $now):
                            $hasPast = true;
                    ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <span class="badge bg-secondary">Afgerond</span>
                                    </h5>
                                    <small>Reservering #<?php echo $reservering['Reservatie_ID']; ?></small>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($reservering['Versie_Toestel']); ?></h5>
                                    <div class="mb-3">
                                        <div><strong>Periode:</strong> <?php echo date('d-m-Y H:i', strtotime($reservering['PRINT_START'])); ?> tot <?php echo date('d-m-Y H:i', strtotime($reservering['PRINT_END'])); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($reservering['filament_type'])): ?>
                                    <div class="mb-3">
                                        <div><strong>Filament:</strong> <?php echo htmlspecialchars($reservering['filament_type']); ?> (<?php echo htmlspecialchars($reservering['filament_kleur']); ?>)</div>
                                        <?php if (!empty($reservering['verbruik'])): ?>
                                        <div><strong>Verbruik:</strong> <?php echo htmlspecialchars($reservering['verbruik']); ?> gram</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($reservering['Comment'])): ?>
                                    <div class="mb-2">
                                        <strong>Commentaar:</strong>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($reservering['Comment'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="reservation-details.php?id=<?php echo $reservering['Reservatie_ID']; ?>" class="btn btn-outline-primary">Details</a>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    
                    if (!$hasPast):
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                Je hebt nog geen afgeronde reserveringen.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>