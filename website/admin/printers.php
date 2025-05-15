<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Printerbeheer - 3D Printer Reserveringssysteem';
$currentPage = 'admin-printers';

// Verwerk formulieren
$message = '';
$error = '';

// Status wijzigen
if (isset($_POST['change_status']) && isset($_POST['printer_id']) && isset($_POST['new_status'])) {
    $printer_id = intval($_POST['printer_id']);
    $new_status = $_POST['new_status'];
    
    // Controleer of de status een geldige waarde heeft
    $valid_statuses = ['beschikbaar', 'in_gebruik', 'onderhoud', 'defect'];
    if (in_array($new_status, $valid_statuses)) {
        try {
            $stmt = $conn->prepare("
                UPDATE Printer 
                SET Status = ?, LAATSTE_STATUS_CHANGE = NOW() 
                WHERE Printer_ID = ?
            ");
            $stmt->execute([$new_status, $printer_id]);
            $message = "Status van printer #$printer_id is gewijzigd naar '$new_status'.";
        } catch (PDOException $e) {
            $error = "Fout bij status wijzigen: " . $e->getMessage();
        }
    }
}

// Printer verwijderen
if (isset($_POST['delete_printer']) && isset($_POST['printer_id'])) {
    $printer_id = intval($_POST['printer_id']);
    
    try {
        // Eerst controleren of de printer nog reserveringen heeft
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM Reservatie WHERE Printer_ID = ?");
        $checkStmt->execute([$printer_id]);
        $reservationCount = $checkStmt->fetch()['count'];
        
        if ($reservationCount > 0) {
            $error = "Deze printer kan niet worden verwijderd omdat er nog $reservationCount reservering(en) aan gekoppeld zijn.";
        } else {
            // Verwijder eventuele filament compatibiliteit records
            $stmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE printer_id = ?");
            $stmt->execute([$printer_id]);
            
            // Verwijder de printer
            $stmt = $conn->prepare("DELETE FROM Printer WHERE Printer_ID = ?");
            $stmt->execute([$printer_id]);
            
            $message = "Printer #$printer_id is succesvol verwijderd.";
        }
    } catch (PDOException $e) {
        $error = "Fout bij verwijderen: " . $e->getMessage();
    }
}

// Nieuwe printer toevoegen
if (isset($_POST['add_printer'])) {
    $versie_toestel = trim($_POST['versie_toestel'] ?? '');
    $status = $_POST['status'] ?? 'beschikbaar';
    $netwerkadres = trim($_POST['netwerkadres'] ?? '');
    $software = $_POST['software'] ?? '';
    $datadrager = $_POST['datadrager'] ?? '';
    $bouwvolume_id = !empty($_POST['bouwvolume_id']) ? intval($_POST['bouwvolume_id']) : null;
    $opmerkingen = trim($_POST['opmerkingen'] ?? '');
    
    if (empty($versie_toestel)) {
        $error = "Versie/model van het toestel is verplicht.";
    } else {
        try {
            // Genereer een nieuwe Printer_ID
            $stmtMaxId = $conn->query("SELECT MAX(Printer_ID) as maxId FROM Printer");
            $result = $stmtMaxId->fetch();
            $newPrinterId = ($result['maxId'] ?? 0) + 1;
            
            $stmt = $conn->prepare("
                INSERT INTO Printer (
                    Printer_ID, Status, LAATSTE_STATUS_CHANGE, netwerkadres, 
                    Versie_Toestel, Software, Datadrager, Bouwvolume_id, Opmerkingen
                ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $newPrinterId,
                $status,
                $netwerkadres,
                $versie_toestel,
                $software,
                $datadrager,
                $bouwvolume_id,
                $opmerkingen
            ]);
            
            // Voeg filament compatibiliteit toe indien geselecteerd
            if (isset($_POST['filaments']) && is_array($_POST['filaments'])) {
                $insertStmt = $conn->prepare("
                    INSERT INTO Filament_compatibiliteit (printer_id, filament_id) 
                    VALUES (?, ?)
                ");
                
                foreach ($_POST['filaments'] as $filament_id) {
                    $insertStmt->execute([$newPrinterId, $filament_id]);
                }
            }
            
            $message = "Nieuwe printer '$versie_toestel' is succesvol toegevoegd.";
        } catch (PDOException $e) {
            $error = "Fout bij toevoegen: " . $e->getMessage();
        }
    }
}

// Printer bewerken
if (isset($_POST['edit_printer'])) {
    $printer_id = intval($_POST['printer_id']);
    $versie_toestel = trim($_POST['versie_toestel'] ?? '');
    $status = $_POST['status'] ?? 'beschikbaar';
    $netwerkadres = trim($_POST['netwerkadres'] ?? '');
    $software = $_POST['software'] ?? '';
    $datadrager = $_POST['datadrager'] ?? '';
    $bouwvolume_id = !empty($_POST['bouwvolume_id']) ? intval($_POST['bouwvolume_id']) : null;
    $opmerkingen = trim($_POST['opmerkingen'] ?? '');
    
    if (empty($versie_toestel)) {
        $error = "Versie/model van het toestel is verplicht.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE Printer SET 
                    Status = ?, 
                    LAATSTE_STATUS_CHANGE = NOW(),
                    netwerkadres = ?,
                    Versie_Toestel = ?,
                    Software = ?,
                    Datadrager = ?,
                    Bouwvolume_id = ?,
                    Opmerkingen = ?
                WHERE Printer_ID = ?
            ");
            $stmt->execute([
                $status,
                $netwerkadres,
                $versie_toestel,
                $software,
                $datadrager,
                $bouwvolume_id,
                $opmerkingen,
                $printer_id
            ]);
            
            // Update filament compatibiliteit
            if (isset($_POST['filaments'])) {
                // Verwijder huidige compatibiliteit
                $deleteStmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE printer_id = ?");
                $deleteStmt->execute([$printer_id]);
                
                // Voeg nieuwe toe
                if (is_array($_POST['filaments'])) {
                    $insertStmt = $conn->prepare("
                        INSERT INTO Filament_compatibiliteit (printer_id, filament_id) 
                        VALUES (?, ?)
                    ");
                    
                    foreach ($_POST['filaments'] as $filament_id) {
                        $insertStmt->execute([$printer_id, $filament_id]);
                    }
                }
            }
            
            $message = "Printer '$versie_toestel' is succesvol bijgewerkt.";
        } catch (PDOException $e) {
            $error = "Fout bij bewerken: " . $e->getMessage();
        }
    }
}

// Haal alle printers op
try {
    $stmt = $conn->query("
        SELECT p.*, b.lengte, b.breedte, b.hoogte, l.Locatie as Lokaalnaam,
               COUNT(r.Reservatie_ID) as reservations_count
        FROM Printer p
        LEFT JOIN bouwvolume b ON p.Bouwvolume_id = b.id
        LEFT JOIN Lokalen l ON p.Printer_ID = l.id
        LEFT JOIN Reservatie r ON p.Printer_ID = r.Printer_ID
        GROUP BY p.Printer_ID
        ORDER BY p.Printer_ID
    ");
    $printers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Fout bij ophalen printers: " . $e->getMessage();
    $printers = [];
}

// Haal alle bouwvolumes op voor dropdown
try {
    $stmt = $conn->query("SELECT * FROM bouwvolume ORDER BY id");
    $bouwvolumes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Fout bij ophalen bouwvolumes: " . $e->getMessage();
    $bouwvolumes = [];
}

// Haal alle filamenten op voor dropdown
try {
    $stmt = $conn->query("SELECT * FROM Filament ORDER BY Type, Kleur");
    $filamenten = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Fout bij ophalen filamenten: " . $e->getMessage();
    $filamenten = [];
}

// Haal alle lokalen op voor dropdown
try {
    $stmt = $conn->query("SELECT * FROM Lokalen ORDER BY id");
    $lokalen = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Fout bij ophalen lokalen: " . $e->getMessage();
    $lokalen = [];
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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
                            <a class="nav-link active" href="printers.php">
                                <i class="fas fa-print me-2"></i>
                                Printers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bouwvolumes.php">
                                <i class="fas fa-cube me-2"></i>
                                Bouwvolumes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reservations.php">
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
                            <a class="nav-link" href="openingsuren.php">
                                <i class="fas fa-clock me-2"></i>
                                Openingsuren
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="opleidingen.php">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Opleidingen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="feedback.php">
                                <i class="fas fa-comments me-2"></i>
                                Feedback
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
                    <h1 class="h2">Printerbeheer</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPrinterModal">
                            <i class="fas fa-plus me-1"></i> Nieuwe Printer
                        </button>
                    </div>
                </div>
                
                <!-- Alerts -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Printers Tabel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-print me-1"></i>
                        Alle Printers
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="printersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Model</th>
                                        <th>Status</th>
                                        <th>Laatste wijziging</th>
                                        <th>Netwerkadres</th>
                                        <th>Software</th>
                                        <th>Bouwvolume</th>
                                        <th>Locatie</th>
                                        <th>Reserveringen</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($printers as $printer): ?>
                                        <tr>
                                            <td><?php echo $printer['Printer_ID']; ?></td>
                                            <td><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    if ($printer['Status'] === 'beschikbaar') echo 'success';
                                                    elseif ($printer['Status'] === 'in_gebruik') echo 'primary';
                                                    elseif ($printer['Status'] === 'onderhoud') echo 'warning';
                                                    else echo 'danger';
                                                ?>">
                                                    <?php echo htmlspecialchars($printer['Status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $printer['LAATSTE_STATUS_CHANGE'] ? date('d-m-Y H:i', strtotime($printer['LAATSTE_STATUS_CHANGE'])) : 'Onbekend'; ?></td>
                                            <td><?php echo htmlspecialchars($printer['netwerkadres'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($printer['Software'] ?? '-'); ?></td>
                                            <td>
                                                <?php 
                                                if (!empty($printer['lengte']) && !empty($printer['breedte']) && !empty($printer['hoogte'])) {
                                                    echo $printer['lengte'] . 'x' . $printer['breedte'] . 'x' . $printer['hoogte'] . ' mm';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($printer['Lokaalnaam'] ?? '-'); ?></td>
                                            <td><?php echo $printer['reservations_count']; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#changeStatusModal" 
                                                            data-printer-id="<?php echo $printer['Printer_ID']; ?>"
                                                            data-current-status="<?php echo $printer['Status']; ?>">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary editPrinterBtn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editPrinterModal"
                                                            data-printer-id="<?php echo $printer['Printer_ID']; ?>"
                                                            data-versie-toestel="<?php echo htmlspecialchars($printer['Versie_Toestel']); ?>"
                                                            data-status="<?php echo $printer['Status']; ?>"
                                                            data-netwerkadres="<?php echo htmlspecialchars($printer['netwerkadres'] ?? ''); ?>"
                                                            data-software="<?php echo $printer['Software'] ?? ''; ?>"
                                                            data-datadrager="<?php echo $printer['Datadrager'] ?? ''; ?>"
                                                            data-bouwvolume-id="<?php echo $printer['Bouwvolume_id'] ?? ''; ?>"
                                                            data-opmerkingen="<?php echo htmlspecialchars($printer['Opmerkingen'] ?? ''); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deletePrinterModal"
                                                            data-printer-id="<?php echo $printer['Printer_ID']; ?>"
                                                            data-printer-name="<?php echo htmlspecialchars($printer['Versie_Toestel']); ?>"
                                                            data-reservations="<?php echo $printer['reservations_count']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modals -->
    
    <!-- Change Status Modal -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">Status wijzigen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="printer_id" id="statusPrinterId">
                        <p>Wijzig de status van de printer:</p>
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Nieuwe status</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <option value="beschikbaar">Beschikbaar</option>
                                <option value="in_gebruik">In gebruik</option>
                                <option value="onderhoud">Onderhoud</option>
                                <option value="defect">Defect</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="change_status" class="btn btn-primary">Status wijzigen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Printer Modal -->
    <div class="modal fade" id="deletePrinterModal" tabindex="-1" aria-labelledby="deletePrinterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePrinterModalLabel">Printer verwijderen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Weet je zeker dat je deze printer wilt verwijderen?</p>
                    <p id="deleteMessage"></p>
                    <div id="reservationWarning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Deze printer heeft nog actieve reserveringen. Verwijderen is alleen mogelijk als alle reserveringen zijn verwijderd.
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="printer_id" id="deletePrinterId">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="delete_printer" class="btn btn-danger" id="confirmDeleteBtn">Verwijderen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Printer Modal -->
    <div class="modal fade" id="addPrinterModal" tabindex="-1" aria-labelledby="addPrinterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPrinterModalLabel">Nieuwe printer toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="versie_toestel" class="form-label">Model/Versie *</label>
                                <input type="text" class="form-control" id="versie_toestel" name="versie_toestel" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="beschikbaar">Beschikbaar</option>
                                    <option value="in_gebruik">In gebruik</option>
                                    <option value="onderhoud">Onderhoud</option>
                                    <option value="defect">Defect</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="netwerkadres" class="form-label">Netwerkadres</label>
                                <input type="text" class="form-control" id="netwerkadres" name="netwerkadres">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="software" class="form-label">Software</label>
                                <input type="text" class="form-control" id="software" name="software" list="software-options">
                                <datalist id="software-options">
                                    <option value="Cura">
                                    <option value="Preform">
                                    <option value="Z suite">
                                    <option value="Bambu Studio">
                                    <option value="Prusa Slicer">
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="datadrager" class="form-label">Datadrager</label>
                                <select class="form-select" id="datadrager" name="datadrager">
                                    <option value="">Selecteer...</option>
                                    <option value="SD">SD Kaart</option>
                                    <option value="USB">USB</option>
                                    <option value="WIFI">WiFi</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bouwvolume_id" class="form-label">Bouwvolume</label>
                                <select class="form-select" id="bouwvolume_id" name="bouwvolume_id">
                                    <option value="">Selecteer...</option>
                                    <?php foreach ($bouwvolumes as $volume): ?>
                                        <option value="<?php echo $volume['id']; ?>"><?php echo $volume['lengte'] . 'x' . $volume['breedte'] . 'x' . $volume['hoogte'] . ' mm'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="filaments" class="form-label">Compatibele filamenten</label>
                            <select class="form-select" id="filaments" name="filaments[]" multiple>
                                <?php foreach ($filamenten as $filament): ?>
                                    <option value="<?php echo $filament['id']; ?>">
                                        <?php echo $filament['Type'] . ' - ' . $filament['Kleur'] . ' (' . 
                                                  (isset($filament['diameter']) ? $filament['diameter'] : '1.75') . ' mm)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Houd CTRL ingedrukt om meerdere te selecteren.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="opmerkingen" class="form-label">Opmerkingen</label>
                            <textarea class="form-control" id="opmerkingen" name="opmerkingen" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="add_printer" class="btn btn-primary">Toevoegen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Printer Modal -->
    <div class="modal fade" id="editPrinterModal" tabindex="-1" aria-labelledby="editPrinterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPrinterModalLabel">Printer bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="printer_id" id="editPrinterId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_versie_toestel" class="form-label">Model/Versie *</label>
                                <input type="text" class="form-control" id="edit_versie_toestel" name="versie_toestel" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="beschikbaar">Beschikbaar</option>
                                    <option value="in_gebruik">In gebruik</option>
                                    <option value="onderhoud">Onderhoud</option>
                                    <option value="defect">Defect</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_netwerkadres" class="form-label">Netwerkadres</label>
                                <input type="text" class="form-control" id="edit_netwerkadres" name="netwerkadres">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_software" class="form-label">Software</label>
                                <input type="text" class="form-control" id="edit_software" name="software" list="edit-software-options">
                                <datalist id="edit-software-options">
                                    <option value="Cura">
                                    <option value="Preform">
                                    <option value="Z suite">
                                    <option value="Bambu Studio">
                                    <option value="Prusa Slicer">
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_datadrager" class="form-label">Datadrager</label>
                                <select class="form-select" id="edit_datadrager" name="datadrager">
                                    <option value="">Selecteer...</option>
                                    <option value="SD">SD Kaart</option>
                                    <option value="USB">USB</option>
                                    <option value="WIFI">WiFi</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_bouwvolume_id" class="form-label">Bouwvolume</label>
                                <select class="form-select" id="edit_bouwvolume_id" name="bouwvolume_id">
                                    <option value="">Selecteer...</option>
                                    <?php foreach ($bouwvolumes as $volume): ?>
                                        <option value="<?php echo $volume['id']; ?>"><?php echo $volume['lengte'] . 'x' . $volume['breedte'] . 'x' . $volume['hoogte'] . ' mm'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_filaments" class="form-label">Compatibele filamenten</label>
                            <select class="form-select" id="edit_filaments" name="filaments[]" multiple>
                                <?php foreach ($filamenten as $filament): ?>
                                    <option value="<?php echo $filament['id']; ?>">
                                        <?php echo $filament['Type'] . ' - ' . $filament['Kleur'] . ' (' . 
                                                  (isset($filament['diameter']) ? $filament['diameter'] : '1.75') . ' mm)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Houd CTRL ingedrukt om meerdere te selecteren.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_opmerkingen" class="form-label">Opmerkingen</label>
                            <textarea class="form-control" id="edit_opmerkingen" name="opmerkingen" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="edit_printer" class="btn btn-primary">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTables
        $('#printersTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/nl-NL.json'
            },
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
        
        // Setup for change status modal
        $('#changeStatusModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const printerId = button.data('printer-id');
            const currentStatus = button.data('current-status');
            
            $('#statusPrinterId').val(printerId);
            $('#new_status').val(currentStatus);
        });
        
        // Setup for delete printer modal
        $('#deletePrinterModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const printerId = button.data('printer-id');
            const printerName = button.data('printer-name');
            const reservations = button.data('reservations');
            
            $('#deletePrinterId').val(printerId);
            $('#deleteMessage').text(`Printer: ${printerName} (ID: ${printerId})`);
            
            if (reservations > 0) {
                $('#reservationWarning').show();
                $('#confirmDeleteBtn').prop('disabled', true);
            } else {
                $('#reservationWarning').hide();
                $('#confirmDeleteBtn').prop('disabled', false);
            }
        });
        
        // Setup for edit printer modal
        $('.editPrinterBtn').on('click', function () {
            const printerId = $(this).data('printer-id');
            const versieToestel = $(this).data('versie-toestel');
            const status = $(this).data('status');
            const netwerkadres = $(this).data('netwerkadres');
            const software = $(this).data('software');
            const datadrager = $(this).data('datadrager');
            const bouwvolumeId = $(this).data('bouwvolume-id');
            const opmerkingen = $(this).data('opmerkingen');
            
            $('#editPrinterId').val(printerId);
            $('#edit_versie_toestel').val(versieToestel);
            $('#edit_status').val(status);
            $('#edit_netwerkadres').val(netwerkadres);
            $('#edit_software').val(software);
            $('#edit_datadrager').val(datadrager);
            $('#edit_bouwvolume_id').val(bouwvolumeId);
            $('#edit_opmerkingen').val(opmerkingen);
            
            // Load filament compatibilities
            $.ajax({
                url: 'get_filament_compat.php',
                method: 'GET',
                data: { printer_id: printerId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const filamentIds = response.filament_ids;
                        $('#edit_filaments').val(filamentIds);
                    }
                }
            });
        });
    });
    </script>
</body>
</html>