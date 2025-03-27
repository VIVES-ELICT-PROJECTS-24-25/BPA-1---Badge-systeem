<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Ensure user is logged in
requireLogin();

$currentPage = 'reservations';
$pageTitle = 'Mijn Reserveringen - 3D Printer Reserveringssysteem';

$userId = $_SESSION['user_id'];

// Process deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $reservationId = $_GET['delete'];
    
    // Check if reservation belongs to user
    $stmt = $conn->prepare("SELECT id FROM reservations WHERE id = ? AND user_id = ?");
    $stmt->execute([$reservationId, $userId]);
    
    if ($stmt->rowCount() === 0) {
        setFlashMessage('Je hebt geen toegang tot deze reservering.', 'danger');
    } else {
        $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
        $result = $stmt->execute([$reservationId]);
        
        if ($result) {
            setFlashMessage('Reservering succesvol verwijderd.', 'success');
        } else {
            setFlashMessage('Fout bij het verwijderen van de reservering.', 'danger');
        }
    }
    redirect('my-reservations.php');
}

// Get user's reservations
$stmt = $conn->prepare("
    SELECT r.*, p.name AS printer_name, p.model, p.color_capability
    FROM reservations r
    JOIN printers p ON r.printer_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.start_time DESC
");
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Mijn Reserveringen</h1>
        <a href="reserve.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Nieuwe Reservering
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Reserveringen Overzicht</h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Totaal Reserveringen:</span>
                            <strong><?php echo count($reservations); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Aankomende Reserveringen:</span>
                            <strong>
                                <?php 
                                    $upcoming = 0;
                                    foreach ($reservations as $reservation) {
                                        if (strtotime($reservation['start_time']) > time()) {
                                            $upcoming++;
                                        }
                                    }
                                    echo $upcoming;
                                ?>
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Afgelopen Reserveringen:</span>
                            <strong><?php echo count($reservations) - $upcoming; ?></strong>
                        </div>
                    </div>
                    <a href="calendar.php" class="btn btn-outline-primary btn-sm w-100">Bekijk Kalender</a>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <ul class="nav nav-tabs mb-4" id="reservationsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="true">Aankomend</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="false">Afgelopen</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="false">Alles</button>
                </li>
            </ul>
            
            <div class="tab-content" id="reservationsTabsContent">
                <!-- Upcoming Reservations -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                    <?php 
                        $hasUpcoming = false;
                        foreach ($reservations as $reservation) {
                            if (strtotime($reservation['start_time']) > time()) {
                                $hasUpcoming = true;
                                break;
                            }
                        }
                    ?>
                    
                    <?php if ($hasUpcoming): ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php if (strtotime($reservation['start_time']) > time()): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($reservation['printer_name']); ?></h5>
                                            <span class="badge bg-<?php 
                                                if ($reservation['status'] === 'confirmed') echo 'success';
                                                elseif ($reservation['status'] === 'pending') echo 'warning';
                                                else echo 'danger';
                                            ?>">
                                                <?php 
                                                    if ($reservation['status'] === 'confirmed') echo 'Bevestigd';
                                                    elseif ($reservation['status'] === 'pending') echo 'In Afwachting';
                                                    else echo 'Geannuleerd';
                                                ?>
                                            </span>
                                        </div>
                                        <p class="card-text">
                                            <strong>Model:</strong> <?php echo htmlspecialchars($reservation['model']); ?><br>
                                            <strong>Datum:</strong> <?php echo date('d-m-Y', strtotime($reservation['start_time'])); ?><br>
                                            <strong>Tijd:</strong> <?php echo date('H:i', strtotime($reservation['start_time'])); ?> - <?php echo date('H:i', strtotime($reservation['end_time'])); ?><br>
                                            <?php if (!empty($reservation['purpose'])): ?>
                                                <strong>Doel:</strong> <?php echo htmlspecialchars($reservation['purpose']); ?><br>
                                            <?php endif; ?>
                                            <strong>Kleurenprint:</strong> <?php echo $reservation['color_printing'] ? 'Ja' : 'Nee'; ?>
                                        </p>
                                        <div class="d-flex justify-content-end">
                                            <a href="edit-reservation.php?id=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-primary me-2">
                                                <i class="fas fa-edit"></i> Wijzigen
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $reservation['id']; ?>">
                                                <i class="fas fa-trash"></i> Annuleren
                                            </a>
                                        </div>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $reservation['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $reservation['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $reservation['id']; ?>">Bevestig Annulering</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Weet je zeker dat je deze reservering wilt annuleren?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Terug</button>
                                                        <a href="my-reservations.php?delete=<?php echo $reservation['id']; ?>" class="btn btn-danger">Annuleren</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Je hebt geen aankomende reserveringen. <a href="reserve.php">Maak een nieuwe reservering</a>.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Past Reservations -->
                <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
                    <?php 
                        $hasPast = false;
                        foreach ($reservations as $reservation) {
                            if (strtotime($reservation['start_time']) <= time()) {
                                $hasPast = true;
                                break;
                            }
                        }
                    ?>
                    
                    <?php if ($hasPast): ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php if (strtotime($reservation['start_time']) <= time()): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($reservation['printer_name']); ?></h5>
                                            <span class="badge bg-secondary">Afgelopen</span>
                                        </div>
                                        <p class="card-text">
                                            <strong>Model:</strong> <?php echo htmlspecialchars($reservation['model']); ?><br>
                                            <strong>Datum:</strong> <?php echo date('d-m-Y', strtotime($reservation['start_time'])); ?><br>
                                            <strong>Tijd:</strong> <?php echo date('H:i', strtotime($reservation['start_time'])); ?> - <?php echo date('H:i', strtotime($reservation['end_time'])); ?><br>
                                            <?php if (!empty($reservation['purpose'])): ?>
                                                <strong>Doel:</strong> <?php echo htmlspecialchars($reservation['purpose']); ?><br>
                                            <?php endif; ?>
                                            <strong>Kleurenprint:</strong> <?php echo $reservation['color_printing'] ? 'Ja' : 'Nee'; ?>
                                        </p>
                                        <div class="d-flex justify-content-end">
                                            <a href="reserve.php?copy=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-copy"></i> Opnieuw Reserveren
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Je hebt geen afgelopen reserveringen.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- All Reservations -->
                <div class="tab-pane fade" id="all" role="tabpanel" aria-labelledby="all-tab">
                    <?php if (count($reservations) > 0): ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($reservation['printer_name']); ?></h5>
                                        <?php if (strtotime($reservation['start_time']) > time()): ?>
                                            <span class="badge bg-<?php 
                                                if ($reservation['status'] === 'confirmed') echo 'success';
                                                elseif ($reservation['status'] === 'pending') echo 'warning';
                                                else echo 'danger';
                                            ?>">
                                                <?php 
                                                    if ($reservation['status'] === 'confirmed') echo 'Bevestigd';
                                                    elseif ($reservation['status'] === 'pending') echo 'In Afwachting';
                                                    else echo 'Geannuleerd';
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Afgelopen</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="card-text">
                                        <strong>Model:</strong> <?php echo htmlspecialchars($reservation['model']); ?><br>
                                        <strong>Datum:</strong> <?php echo date('d-m-Y', strtotime($reservation['start_time'])); ?><br>
                                        <strong>Tijd:</strong> <?php echo date('H:i', strtotime($reservation['start_time'])); ?> - <?php echo date('H:i', strtotime($reservation['end_time'])); ?><br>
                                        <?php if (!empty($reservation['purpose'])): ?>
                                            <strong>Doel:</strong> <?php echo htmlspecialchars($reservation['purpose']); ?><br>
                                        <?php endif; ?>
                                        <strong>Kleurenprint:</strong> <?php echo $reservation['color_printing'] ? 'Ja' : 'Nee'; ?>
                                    </p>
                                    <div class="d-flex justify-content-end">
                                        <?php if (strtotime($reservation['start_time']) > time()): ?>
                                            <a href="edit-reservation.php?id=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-primary me-2">
                                                <i class="fas fa-edit"></i> Wijzigen
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $reservation['id']; ?>">
                                                <i class="fas fa-trash"></i> Annuleren
                                            </a>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $reservation['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $reservation['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $reservation['id']; ?>">Bevestig Annulering</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Weet je zeker dat je deze reservering wilt annuleren?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Terug</button>
                                                            <a href="my-reservations.php?delete=<?php echo $reservation['id']; ?>" class="btn btn-danger">Annuleren</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <a href="reserve.php?copy=<?php echo $reservation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-copy"></i> Opnieuw Reserveren
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Je hebt nog geen reserveringen gemaakt. <a href="reserve.php">Maak een nieuwe reservering</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>