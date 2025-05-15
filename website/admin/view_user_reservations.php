<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Gebruikersreserveringen - 3D Printer Reserveringssysteem';
$currentPage = 'admin-users';

// Initialiseer variabelen
$errorMessage = '';
$successMessage = '';

// Controleer of gebruikers-ID is meegegeven
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$userId = $_GET['id'];

// Ophalen gebruikersgegevens
try {
    $stmt = $conn->prepare("SELECT * FROM User WHERE User_ID = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        $errorMessage = 'Gebruiker niet gevonden.';
    }
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen gebruikersgegevens: ' . $e->getMessage();
}

// Bepaal sortering
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'PRINT_START';
$validSortFields = ['Reservatie_ID', 'Printer_ID', 'PRINT_START', 'PRINT_END'];
$sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'PRINT_START';

// Bepaal sorteervolgorde
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Filter op status - via datum vergelijkingen in plaats van status kolom
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$helpNeededFilter = isset($_GET['help_needed']) ? (int)$_GET['help_needed'] : -1;
$adminPrintFilter = isset($_GET['admin_print']) ? (int)$_GET['admin_print'] : -1;

$statusWhere = '';
$filterParams = [$userId];
$now = date('Y-m-d H:i:s');

if (!empty($statusFilter)) {
    switch ($statusFilter) {
        case 'goedgekeurd':
            // Goedgekeurd = toekomstige reservering (nog niet gestart)
            $statusWhere = " AND r.PRINT_START > ?";
            $filterParams[] = $now;
            break;
        case 'afgerond':
            // Afgerond = voorbije reservering
            $statusWhere = " AND r.PRINT_END < ?";
            $filterParams[] = $now;
            break;
        case 'wachtend':
            // Wachtend = huidige reservering (nu bezig)
            $statusWhere = " AND r.PRINT_START <= ? AND r.PRINT_END >= ?";
            $filterParams[] = $now;
            $filterParams[] = $now;
            break;
        // Afgewezen en geannuleerd kunnen we niet afleiden uit de datums
        // We kunnen deze eventueel een andere logica geven of weglaten
    }
}

// Filter op HulpNodig
if ($helpNeededFilter !== -1) {
    $statusWhere .= " AND r.HulpNodig = ?";
    $filterParams[] = $helpNeededFilter;
}

// Filter op BeheerderPrinten
if ($adminPrintFilter !== -1) {
    $statusWhere .= " AND r.BeheerderPrinten = ?";
    $filterParams[] = $adminPrintFilter;
}

// Paginering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10; // Aantal items per pagina
$offset = ($page - 1) * $perPage;

// Totaal aantal reserveringen voor paginering
try {
    $countQuery = "SELECT COUNT(*) as total FROM Reservatie r WHERE r.User_ID = ?" . $statusWhere;
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($filterParams);
    $totalReservations = $stmt->fetch()['total'];
    $totalPages = ceil($totalReservations / $perPage);
} catch (PDOException $e) {
    $errorMessage = 'Fout bij tellen van reserveringen: ' . $e->getMessage();
    $totalReservations = 0;
    $totalPages = 0;
}

// Reserveringen ophalen
try {
    $query = "SELECT r.*, CONCAT('Printer ', p.Printer_ID) as PrinterNaam, p.Versie_Toestel 
              FROM Reservatie r
              LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
              WHERE r.User_ID = ?" . $statusWhere . "
              ORDER BY r.$sortBy $sortOrder
              LIMIT $perPage OFFSET $offset";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($filterParams);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van reserveringen: ' . $e->getMessage();
    $reservations = [];
}

// Status opties voor filter dropdown
$statusOptions = ['goedgekeurd', 'afgerond', 'wachtend'];
// We hebben afgewezen en geannuleerd weggelaten omdat we die niet kunnen afleiden uit de datums
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
                            <a class="nav-link active" href="users.php">
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
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Terug naar site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="feedback.php">
                                <i class="fas fa-comments me-2"></i>
                                Feedback
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
                    <h1 class="h2">
                        Reserveringen van Gebruiker
                        <?php if (isset($userData)): ?>
                            <span class="text-primary"><?php echo htmlspecialchars($userData['Voornaam'] . ' ' . $userData['Naam']); ?></span>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="users.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Terug naar gebruikers
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Afdrukken
                        </button>
                    </div>
                </div>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Gebruikersinfo -->
                <?php if (isset($userData)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user me-1"></i>
                        Gebruikersgegevens
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Naam:</strong> <?php echo htmlspecialchars($userData['Voornaam'] . ' ' . $userData['Naam']); ?></p>
                                <p><strong>E-mail:</strong> <?php echo htmlspecialchars($userData['Emailadres']); ?></p>
                                <p><strong>Telefoon:</strong> <?php echo htmlspecialchars($userData['Telefoon'] ?: 'Niet opgegeven'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p>
                                    <strong>Type:</strong>
                                    <?php if ($userData['Type'] == 'beheerder'): ?>
                                        <span class="badge bg-danger">Beheerder</span>
                                    <?php elseif ($userData['Type'] == 'onderzoeker'): ?>
                                        <span class="badge bg-warning text-dark">Onderzoeker</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Student</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Account aangemaakt:</strong> <?php echo date('d-m-Y', strtotime($userData['AanmaakAccount'])); ?></p>
                                <p>
                                    <strong>Laatste aanmelding:</strong>
                                    <?php 
                                    if ($userData['LaatsteAanmelding']) {
                                        echo date('d-m-Y H:i', strtotime($userData['LaatsteAanmelding']));
                                    } else {
                                        echo '<span class="text-muted">Nooit</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="edit_user.php?id=<?php echo $userId; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Gebruiker bewerken
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filter en sortering -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="view_user_reservations.php" method="GET" class="row g-3">
                            <input type="hidden" name="id" value="<?php echo $userId; ?>">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Filter op status</label>
                                <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">Alle reserveringen</option>
                                    <option value="goedgekeurd" <?php echo ($statusFilter == 'goedgekeurd') ? 'selected' : ''; ?>>Toekomstige reserveringen</option>
                                    <option value="wachtend" <?php echo ($statusFilter == 'wachtend') ? 'selected' : ''; ?>>Huidige reserveringen</option>
                                    <option value="afgerond" <?php echo ($statusFilter == 'afgerond') ? 'selected' : ''; ?>>Afgeronde reserveringen</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="help_needed" class="form-label">Hulp nodig</label>
                                <select name="help_needed" id="help_needed" class="form-select" onchange="this.form.submit()">
                                    <option value="-1">Alle reserveringen</option>
                                    <option value="1" <?php echo ($helpNeededFilter == 1) ? 'selected' : ''; ?>>Hulp nodig</option>
                                    <option value="0" <?php echo ($helpNeededFilter === 0) ? 'selected' : ''; ?>>Geen hulp nodig</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="admin_print" class="form-label">Beheerder print</label>
                                <select name="admin_print" id="admin_print" class="form-select" onchange="this.form.submit()">
                                    <option value="-1">Alle reserveringen</option>
                                    <option value="1" <?php echo ($adminPrintFilter == 1) ? 'selected' : ''; ?>>Beheerder print</option>
                                    <option value="0" <?php echo ($adminPrintFilter === 0) ? 'selected' : ''; ?>>Zelf printen</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Sorteer op</label>
                                <div class="input-group">
                                    <select name="sort" id="sort" class="form-select">
                                        <option value="PRINT_START" <?php echo ($sortBy == 'PRINT_START') ? 'selected' : ''; ?>>Startdatum</option>
                                        <option value="PRINT_END" <?php echo ($sortBy == 'PRINT_END') ? 'selected' : ''; ?>>Einddatum</option>
                                        <option value="Reservatie_ID" <?php echo ($sortBy == 'Reservatie_ID') ? 'selected' : ''; ?>>Reservatie ID</option>
                                    </select>
                                    <select name="order" id="order" class="form-select">
                                        <option value="asc" <?php echo ($sortOrder == 'ASC') ? 'selected' : ''; ?>>Oplopend</option>
                                        <option value="desc" <?php echo ($sortOrder == 'DESC') ? 'selected' : ''; ?>>Aflopend</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Sorteer</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Reserveringen tabel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Reserveringen (<?php echo $totalReservations; ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($reservations)): ?>
                            <div class="alert alert-info">
                                Geen reserveringen gevonden voor deze gebruiker<?php echo !empty($statusFilter) ? ' met de geselecteerde status' : ''; ?>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Printer</th>
                                            <th>Start</th>
                                            <th>Einde</th>
                                            <th>Status</th>
                                            <th>Hulp</th>
                                            <th>Beheerder Print</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservations as $reservation): 
                                            // Bepaal de status gebaseerd op de datum
                                            $now = new DateTime();
                                            $startTime = new DateTime($reservation['PRINT_START']);
                                            $endTime = new DateTime($reservation['PRINT_END']);
                                            
                                            $displayStatus = 'Onbekend';
                                            $badgeClass = 'bg-light text-dark';
                                            
                                            if ($now > $endTime) {
                                                $displayStatus = 'Afgerond';
                                                $badgeClass = 'bg-info';
                                            } elseif ($now >= $startTime && $now <= $endTime) {
                                                $displayStatus = 'Huidig';
                                                $badgeClass = 'bg-warning text-dark';
                                            } elseif ($now < $startTime) {
                                                $displayStatus = 'Toekomstig';
                                                $badgeClass = 'bg-success';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo $reservation['Reservatie_ID']; ?></td>
                                                <td><?php echo htmlspecialchars($reservation['Versie_Toestel'] ?: $reservation['PrinterNaam']); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_START'])); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_END'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $displayStatus; ?></span>
                                                </td>
                                                <td>
                                                    <?php if (isset($reservation['HulpNodig']) && $reservation['HulpNodig'] == 1): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="fas fa-hands-helping me-1"></i> Ja
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Nee</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($reservation['BeheerderPrinten']) && $reservation['BeheerderPrinten'] == 1): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-print me-1"></i> Ja
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Nee</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="reservation-detail.php?id=<?php echo $reservation['Reservatie_ID']; ?>" class="btn btn-info" title="Details bekijken">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($now < $startTime): ?>
                                                            <a href="reservation-detail.php?id=<?php echo $reservation['Reservatie_ID']; ?>&edit=true" class="btn btn-warning" title="Bewerken">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Paginering -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Reserveringenpagina's">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $userId; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($statusFilter) ? '&status=' . $statusFilter : ''; ?><?php echo $helpNeededFilter != -1 ? '&help_needed=' . $helpNeededFilter : ''; ?><?php echo $adminPrintFilter != -1 ? '&admin_print=' . $adminPrintFilter : ''; ?>">
                                                Vorige
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Vorige</span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Bepaal welke pagina's te tonen
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    // Zorg dat we altijd 5 pagina's tonen als dat mogelijk is
                                    if ($endPage - $startPage + 1 < 5) {
                                        if ($startPage == 1) {
                                            $endPage = min($totalPages, $startPage + 4);
                                        } elseif ($endPage == $totalPages) {
                                            $startPage = max(1, $endPage - 4);
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id=<?php echo $userId; ?>&page=<?php echo $i; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($statusFilter) ? '&status=' . $statusFilter : ''; ?><?php echo $helpNeededFilter != -1 ? '&help_needed=' . $helpNeededFilter : ''; ?><?php echo $adminPrintFilter != -1 ? '&admin_print=' . $adminPrintFilter : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $userId; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($statusFilter) ? '&status=' . $statusFilter : ''; ?><?php echo $helpNeededFilter != -1 ? '&help_needed=' . $helpNeededFilter : ''; ?><?php echo $adminPrintFilter != -1 ? '&admin_print=' . $adminPrintFilter : ''; ?>">
                                                Volgende
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Volgende</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistieken -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        Reserveringsstatistieken
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            // Status statistieken berekenen op basis van datums
                            try {
                                $now = date('Y-m-d H:i:s');
                                
                                // Toekomstige reserveringen (goedgekeurd)
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count
                                    FROM Reservatie
                                    WHERE User_ID = ? AND PRINT_START > ?
                                ");
                                $stmt->execute([$userId, $now]);
                                $futureCount = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Huidige reserveringen (wachtend)
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count
                                    FROM Reservatie
                                    WHERE User_ID = ? AND PRINT_START <= ? AND PRINT_END >= ?
                                ");
                                $stmt->execute([$userId, $now, $now]);
                                $currentCount = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Afgeronde reserveringen
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count
                                    FROM Reservatie
                                    WHERE User_ID = ? AND PRINT_END < ?
                                ");
                                $stmt->execute([$userId, $now]);
                                $pastCount = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Hulp nodig statistieken
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count
                                    FROM Reservatie
                                    WHERE User_ID = ? AND HulpNodig = 1
                                ");
                                $stmt->execute([$userId]);
                                $helpNeededCount = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Beheerder print statistieken
                                $stmt = $conn->prepare("
                                    SELECT COUNT(*) as count
                                    FROM Reservatie
                                    WHERE User_ID = ? AND BeheerderPrinten = 1
                                ");
                                $stmt->execute([$userId]);
                                $adminPrintCount = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Totaal aantal uren gereserveerd
                                $stmt = $conn->prepare("
                                    SELECT SUM(TIMESTAMPDIFF(HOUR, PRINT_START, PRINT_END)) as total_hours
                                    FROM Reservatie
                                    WHERE User_ID = ?
                                ");
                                $stmt->execute([$userId]);
                                $totalHours = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Per printer statistieken
                                $stmt = $conn->prepare("
                                    SELECT p.Versie_Toestel, COUNT(*) as count
                                    FROM Reservatie r
                                    JOIN Printer p ON r.Printer_ID = p.Printer_ID
                                    WHERE r.User_ID = ?
                                    GROUP BY r.Printer_ID
                                    ORDER BY count DESC
                                    LIMIT 5
                                ");
                                $stmt->execute([$userId]);
                                $printerStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                                
                            } catch (PDOException $e) {
                                $futureCount = 0;
                                $currentCount = 0;
                                $pastCount = 0;
                                $helpNeededCount = 0;
                                $adminPrintCount = 0;
                                $totalHours = 0;
                                $printerStats = [];
                            }
                            
                            // Maak een status array voor de statistieken tabel
                            $statusStats = [
                                'Toekomstig' => $futureCount,
                                'Huidig' => $currentCount,
                                'Afgerond' => $pastCount
                            ];
                            ?>
                            
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-primary h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Totaal aantal reserveringen</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalReservations; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-success h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Totaal aantal uren gereserveerd</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalHours ?: 0; ?> uur</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-warning h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Aantal keer hulp nodig</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $helpNeededCount; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-hands-helping fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card border-left-danger h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                    Aantal keer beheerder print</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $adminPrintCount; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-print fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Status Verdeling</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Aantal</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            foreach ($statusStats as $status => $count): 
                                                $percentage = ($totalReservations > 0) ? round(($count / $totalReservations) * 100) : 0;
                                            ?>
                                                <tr>
                                                    <td><?php echo $status; ?></td>
                                                    <td><?php echo $count; ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" 
                                                                aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                <?php echo $percentage; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <h5 class="mt-4">Ondersteuning</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Aantal</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-hands-helping me-1"></i> Hulp nodig
                                                    </span>
                                                </td>
                                                <td><?php echo $helpNeededCount; ?></td>
                                                <td>
                                                    <?php 
                                                    $helpPercentage = ($totalReservations > 0) ? round(($helpNeededCount / $totalReservations) * 100) : 0;
                                                    ?>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-warning" role="progressbar" 
                                                            style="width: <?php echo $helpPercentage; ?>%;" 
                                                            aria-valuenow="<?php echo $helpPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo $helpPercentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-print me-1"></i> Beheerder print
                                                    </span>
                                                </td>
                                                <td><?php echo $adminPrintCount; ?></td>
                                                <td>
                                                    <?php 
                                                    $printPercentage = ($totalReservations > 0) ? round(($adminPrintCount / $totalReservations) * 100) : 0;
                                                    ?>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-danger" role="progressbar" 
                                                            style="width: <?php echo $printPercentage; ?>%;" 
                                                            aria-valuenow="<?php echo $printPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo $printPercentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Meest Gebruikte Printers</h5>
                                <?php if (empty($printerStats)): ?>
                                    <p class="text-muted">Geen printergegevens beschikbaar.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Printer</th>
                                                    <th>Aantal reserveringen</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                foreach ($printerStats as $printer => $count): 
                                                    $percentage = ($totalReservations > 0) ? round(($count / $totalReservations) * 100) : 0;
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($printer); ?></td>
                                                        <td><?php echo $count; ?></td>
                                                        <td>
                                                            <div class="progress">
                                                                <div class="progress-bar bg-info" role="progressbar" 
                                                                    style="width: <?php echo $percentage; ?>%;" 
                                                                    aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                    <?php echo $percentage; ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer met laatste update info -->
                <footer class="bg-light p-3 rounded text-center mt-4 mb-2">
                    <small class="text-muted">Laatste update: <?php echo date('Y-m-d H:i:s'); ?></small>
                    <br>
                    <small class="text-muted">Ingelogd als: <?php echo htmlspecialchars($currentUser); ?></small>
                </footer>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>