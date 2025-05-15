<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Reserveringen Beheer - 3D Printer Reserveringssysteem';
$currentPage = 'admin-reservations';

// Verwerken van formulier inzendingen
$success = '';
$error = '';

// Check of we een kolom 'feedback_mail_verzonden' moeten toevoegen aan de Reservatie tabel
try {
    $checkColumnStmt = $conn->query("SHOW COLUMNS FROM Reservatie LIKE 'feedback_mail_verzonden'");
    if ($checkColumnStmt->rowCount() == 0) {
        // Kolom bestaat niet, voeg toe
        $conn->exec("ALTER TABLE Reservatie ADD COLUMN feedback_mail_verzonden TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    $error = 'Fout bij controleren/aanmaken van database kolom: ' . $e->getMessage();
}

// Reservering verwijderen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Haal printer info op voor status update
        $stmt = $conn->prepare("SELECT Printer_ID FROM Reservatie WHERE Reservatie_ID = ?");
        $stmt->execute([$id]);
        $printerInfo = $stmt->fetch();
        
        if ($printerInfo) {
            // Verwijder de reservering
            $stmt = $conn->prepare("DELETE FROM Reservatie WHERE Reservatie_ID = ?");
            $stmt->execute([$id]);
            
            // Update printer status
            $stmt = $conn->prepare("
                UPDATE Printer 
                SET Status = 'beschikbaar', LAATSTE_STATUS_CHANGE = NOW() 
                WHERE Printer_ID = ?
            ");
            $stmt->execute([$printerInfo['Printer_ID']]);
            
            $success = 'Reservering succesvol verwijderd.';
        } else {
            $error = 'Reservering niet gevonden.';
        }
    } catch (PDOException $e) {
        $error = 'Fout bij het verwijderen van reservering: ' . $e->getMessage();
    }
}

// Filter parameters
$filter = $_GET['filter'] ?? 'all';
$date = $_GET['date'] ?? '';
$user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$printer = isset($_GET['printer']) ? (int)$_GET['printer'] : 0;
$help_needed = isset($_GET['help_needed']) ? (int)$_GET['help_needed'] : -1;
$admin_print = isset($_GET['admin_print']) ? (int)$_GET['admin_print'] : -1;

// Basis query opbouwen
$query = "
    SELECT r.*, 
           u.Voornaam, u.Naam, u.Emailadres, u.Type as UserType,
           p.Versie_Toestel, p.Status as PrinterStatus,
           f.Type as FilamentType, f.Kleur as FilamentKleur
    FROM Reservatie r
    JOIN User u ON r.User_ID = u.User_ID
    JOIN Printer p ON r.Printer_ID = p.Printer_ID
    LEFT JOIN Filament f ON r.filament_id = f.id
    WHERE 1=1
";

$params = [];

// Filters toepassen
if ($filter === 'active') {
    $now = date('Y-m-d H:i:s');
    $query .= " AND r.PRINT_START <= ? AND r.PRINT_END >= ?";
    $params[] = $now;
    $params[] = $now;
} elseif ($filter === 'upcoming') {
    $now = date('Y-m-d H:i:s');
    $query .= " AND r.PRINT_START > ?";
    $params[] = $now;
} elseif ($filter === 'past') {
    $now = date('Y-m-d H:i:s');
    $query .= " AND r.PRINT_END < ?";
    $params[] = $now;
}

if (!empty($date)) {
    $query .= " AND DATE(r.PRINT_START) = ?";
    $params[] = $date;
}

if ($user > 0) {
    $query .= " AND r.User_ID = ?";
    $params[] = $user;
}

if ($printer > 0) {
    $query .= " AND r.Printer_ID = ?";
    $params[] = $printer;
}

// Filter op HulpNodig
if ($help_needed === 1) {
    $query .= " AND r.HulpNodig = 1";
} elseif ($help_needed === 0) {
    $query .= " AND (r.HulpNodig = 0 OR r.HulpNodig IS NULL)";
}

// Filter op BeheerderPrinten
if ($admin_print === 1) {
    $query .= " AND r.BeheerderPrinten = 1";
} elseif ($admin_print === 0) {
    $query .= " AND (r.BeheerderPrinten = 0 OR r.BeheerderPrinten IS NULL)";
}

// Sorteren
$query .= " ORDER BY r.PRINT_START DESC";

// Voer query uit
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reserveringen = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Fout bij het ophalen van reserveringen: ' . $e->getMessage();
    $reserveringen = [];
}

// Haal printers op voor filteren
try {
    $stmtPrinters = $conn->query("SELECT Printer_ID, Versie_Toestel FROM Printer ORDER BY Versie_Toestel");
    $printers = $stmtPrinters->fetchAll();
} catch (PDOException $e) {
    $printers = [];
}

// Haal gebruikers op voor filteren
try {
    $stmtUsers = $conn->query("SELECT User_ID, Voornaam, Naam FROM User ORDER BY Naam, Voornaam");
    $users = $stmtUsers->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Tel het aantal reserveringen die nog geen feedback mail hebben ontvangen
try {
    $stmtCount = $conn->query("SELECT COUNT(*) as count FROM Reservatie WHERE (feedback_mail_verzonden = 0 OR feedback_mail_verzonden IS NULL) AND PRINT_END < NOW()");
    $pendingMailsCount = $stmtCount->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $pendingMailsCount = 0;
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
                    <h1 class="h2">Reserveringen Beheer</h1>
                    
                    <!-- Nieuwe knop voor bulk feedback mails -->
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="send_all_feedback_emails.php" 
                           class="btn btn-success me-2" 
                           onclick="return confirm('Wilt u feedback mails versturen naar alle gebruikers die er nog geen hebben ontvangen?')">
                            <i class="fas fa-envelope me-1"></i> Alle feedback mails versturen
                            <?php if ($pendingMailsCount > 0): ?>
                                <span class="badge bg-light text-dark ms-1"><?php echo $pendingMailsCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-filter me-1"></i>
                        Filters
                    </div>
                    <div class="card-body">
                        <form method="get" action="reservations.php" class="row g-3">
                            <div class="col-md-3">
                                <label for="filter" class="form-label">Status</label>
                                <select class="form-select" id="filter" name="filter">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Alle reserveringen</option>
                                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Actieve reserveringen</option>
                                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Aankomende reserveringen</option>
                                    <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Voorbije reserveringen</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Datum</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="user" class="form-label">Gebruiker</label>
                                <select class="form-select" id="user" name="user">
                                    <option value="0">Alle gebruikers</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['User_ID']; ?>" <?php echo $user === (int)$u['User_ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['Naam'] . ', ' . $u['Voornaam']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="printer" class="form-label">Printer</label>
                                <select class="form-select" id="printer" name="printer">
                                    <option value="0">Alle printers</option>
                                    <?php foreach ($printers as $p): ?>
                                        <option value="<?php echo $p['Printer_ID']; ?>" <?php echo $printer === (int)$p['Printer_ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['Versie_Toestel']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Nieuwe filters voor hulp en beheerder printen -->
                            <div class="col-md-3">
                                <label for="help_needed" class="form-label">Hulp nodig</label>
                                <select class="form-select" id="help_needed" name="help_needed">
                                    <option value="-1" <?php echo $help_needed === -1 ? 'selected' : ''; ?>>Alle reserveringen</option>
                                    <option value="1" <?php echo $help_needed === 1 ? 'selected' : ''; ?>>Ja, hulp nodig</option>
                                    <option value="0" <?php echo $help_needed === 0 ? 'selected' : ''; ?>>Nee, geen hulp nodig</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="admin_print" class="form-label">Beheerder print</label>
                                <select class="form-select" id="admin_print" name="admin_print">
                                    <option value="-1" <?php echo $admin_print === -1 ? 'selected' : ''; ?>>Alle reserveringen</option>
                                    <option value="1" <?php echo $admin_print === 1 ? 'selected' : ''; ?>>Ja, beheerder moet printen</option>
                                    <option value="0" <?php echo $admin_print === 0 ? 'selected' : ''; ?>>Nee, zelf printen</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Filter toepassen</button>
                                <a href="reservations.php" class="btn btn-outline-secondary">Filters wissen</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Reservations table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Reserveringen
                        <span class="badge bg-primary"><?php echo count($reserveringen); ?> gevonden</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reserveringen)): ?>
                            <div class="alert alert-info">Geen reserveringen gevonden met de opgegeven filters.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Gebruiker</th>
                                            <th>Printer</th>
                                            <th>Start tijd</th>
                                            <th>Eind tijd</th>
                                            <th>Hulp nodig</th>
                                            <th>Beheerder print</th>
                                            <th>Filament</th>
                                            <th>Pincode</th>
                                            <th>Mail status</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reserveringen as $reservering): ?>
                                            <?php
                                            // Bepaal rij-klasse op basis van timing
                                            $now = new DateTime();
                                            $startTime = new DateTime($reservering['PRINT_START']);
                                            $endTime = new DateTime($reservering['PRINT_END']);
                                            
                                            $rowClass = '';
                                            if ($now >= $startTime && $now <= $endTime) {
                                                $rowClass = 'table-success'; // Actief
                                            } elseif ($startTime > $now) {
                                                $rowClass = 'table-info'; // Aankomend
                                            } elseif ($endTime < $now) {
                                                $rowClass = 'table-secondary'; // Voorbij
                                            }
                                            
                                            // Markeer rijen met hulp of beheerder printen
                                            if (isset($reservering['HulpNodig']) && $reservering['HulpNodig'] == 1) {
                                                $rowClass .= ' border-warning';
                                            }
                                            if (isset($reservering['BeheerderPrinten']) && $reservering['BeheerderPrinten'] == 1) {
                                                $rowClass .= ' border-danger';
                                            }
                                            ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td><?php echo $reservering['Reservatie_ID']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($reservering['Voornaam'] . ' ' . $reservering['Naam']); ?>
                                                    <span class="badge bg-<?php echo $reservering['UserType'] === 'beheerder' ? 'danger' : 'primary'; ?>">
                                                        <?php echo $reservering['UserType']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($reservering['Versie_Toestel']); ?>
                                                    <span class="badge bg-<?php 
                                                        if ($reservering['PrinterStatus'] === 'beschikbaar') echo 'success';
                                                        elseif ($reservering['PrinterStatus'] === 'in_gebruik') echo 'primary';
                                                        elseif ($reservering['PrinterStatus'] === 'onderhoud') echo 'warning';
                                                        else echo 'danger';
                                                    ?>">
                                                        <?php echo $reservering['PrinterStatus']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservering['PRINT_START'])); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservering['PRINT_END'])); ?></td>
                                                <td>
                                                    <?php if (isset($reservering['HulpNodig']) && $reservering['HulpNodig'] == 1): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="fas fa-hands-helping me-1"></i> Ja
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Nee</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($reservering['BeheerderPrinten']) && $reservering['BeheerderPrinten'] == 1): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-print me-1"></i> Ja
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Nee</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($reservering['FilamentType'])): ?>
                                                        <?php echo htmlspecialchars($reservering['FilamentType'] . ' - ' . $reservering['FilamentKleur']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Geen filament</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($reservering['Pincode'] ?? 'N/A'); ?></code>
                                                </td>
                                                <td>
                                                    <?php if (isset($reservering['feedback_mail_verzonden']) && $reservering['feedback_mail_verzonden'] == 1): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i> Verzonden
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-times me-1"></i> Niet verzonden
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="reservation-detail.php?id=<?php echo $reservering['Reservatie_ID']; ?>" class="btn btn-sm btn-primary" title="Bekijken">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="reservations.php?delete=<?php echo $reservering['Reservatie_ID']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Weet je zeker dat je deze reservering wilt verwijderen?')"
                                                       title="Verwijderen">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <!-- Feedback Mail knop -->
                                                    <a href="send_feedback_email.php?id=<?php echo $reservering['Reservatie_ID']; ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       onclick="return confirm('Wilt u een feedback mail versturen naar de gebruiker?')"
                                                       title="Feedback mail versturen">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Legenda
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success me-2" style="width: 20px; height: 20px;"></div>
                                    <span>Actieve reservering (nu bezig)</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-info me-2" style="width: 20px; height: 20px;"></div>
                                    <span>Aankomende reservering</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-secondary me-2" style="width: 20px; height: 20px;"></div>
                                    <span>Afgelopen reservering</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-warning text-dark me-2">
                                        <i class="fas fa-hands-helping"></i> Ja
                                    </span>
                                    <span>Hulp nodig</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-danger me-2">
                                        <i class="fas fa-print"></i> Ja
                                    </span>
                                    <span>Beheerder moet printen</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <span>Feedback mail versturen</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>