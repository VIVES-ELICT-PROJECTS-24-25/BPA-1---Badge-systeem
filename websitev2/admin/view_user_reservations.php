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
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'StartTijd';
$validSortFields = ['Reservatie_ID', 'Printer_ID', 'StartTijd', 'EindTijd', 'Status'];
$sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'StartTijd';

// Bepaal sorteervolgorde
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Filter op status
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$statusWhere = '';
$filterParams = [$userId];

if (!empty($statusFilter)) {
    $statusWhere = " AND Status = ?";
    $filterParams[] = $statusFilter;
}

// Paginering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10; // Aantal items per pagina
$offset = ($page - 1) * $perPage;

// Totaal aantal reserveringen voor paginering
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Reservatie WHERE User_ID = ?" . $statusWhere);
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
    $stmt = $conn->prepare(
        "SELECT r.*, p.Naam as PrinterNaam 
        FROM Reservatie r
        LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
        WHERE r.User_ID = ?" . $statusWhere . "
        ORDER BY r.$sortBy $sortOrder
        LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($filterParams);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van reserveringen: ' . $e->getMessage();
    $reservations = [];
}

// Status ophalen voor filter dropdown
$statusOptions = ['goedgekeurd', 'afgewezen', 'afgerond', 'wachtend', 'geannuleerd'];
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
                            <div class="col-md-4">
                                <label for="status" class="form-label">Filter op status</label>
                                <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">Alle statussen</option>
                                    <?php foreach ($statusOptions as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php echo ($statusFilter == $option) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="sort" class="form-label">Sorteer op</label>
                                <select name="sort" id="sort" class="form-select" onchange="this.form.submit()">
                                    <option value="StartTijd" <?php echo ($sortBy == 'StartTijd') ? 'selected' : ''; ?>>Startdatum</option>
                                    <option value="EindTijd" <?php echo ($sortBy == 'EindTijd') ? 'selected' : ''; ?>>Einddatum</option>
                                    <option value="Reservatie_ID" <?php echo ($sortBy == 'Reservatie_ID') ? 'selected' : ''; ?>>Reservatie ID</option>
                                    <option value="Status" <?php echo ($sortBy == 'Status') ? 'selected' : ''; ?>>Status</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="order" class="form-label">Volgorde</label>
                                <select name="order" id="order" class="form-select" onchange="this.form.submit()">
                                    <option value="asc" <?php echo ($sortOrder == 'ASC') ? 'selected' : ''; ?>>Oplopend</option>
                                    <option value="desc" <?php echo ($sortOrder == 'DESC') ? 'selected' : ''; ?>>Aflopend</option>
                                </select>
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
                                Geen reserveringen gevonden voor deze gebruiker<?php echo !empty($statusFilter) ? ' met status ' . $statusFilter : ''; ?>.
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
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservations as $reservation): ?>
                                            <tr>
                                                <td><?php echo $reservation['Reservatie_ID']; ?></td>
                                                <td><?php echo htmlspecialchars($reservation['PrinterNaam']); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservation['StartTijd'])); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservation['EindTijd'])); ?></td>
                                                <td>
                                                    <?php
                                                    switch ($reservation['Status']) {
                                                        case 'goedgekeurd':
                                                            echo '<span class="badge bg-success">Goedgekeurd</span>';
                                                            break;
                                                        case 'afgewezen':
                                                            echo '<span class="badge bg-danger">Afgewezen</span>';
                                                            break;
                                                        case 'afgerond':
                                                            echo '<span class="badge bg-info">Afgerond</span>';
                                                            break;
                                                        case 'wachtend':
                                                            echo '<span class="badge bg-warning text-dark">Wachtend</span>';
                                                            break;
                                                        case 'geannuleerd':
                                                            echo '<span class="badge bg-secondary">Geannuleerd</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-light text-dark">Onbekend</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view_reservation.php?id=<?php echo $reservation['Reservatie_ID']; ?>" class="btn btn-info" title="Details bekijken">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($reservation['Status'] == 'wachtend'): ?>
                                                            <a href="approve_reservation.php?id=<?php echo $reservation['Reservatie_ID']; ?>" class="btn btn-success" title="Goedkeuren">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="reject_reservation.php?id=<?php echo $reservation['Reservatie_ID']; ?>" class="btn btn-danger" title="Afwijzen">
                                                                <i class="fas fa-times"></i>
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
                                            <a class="page-link" href="?id=<?php echo $userId; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($statusFilter) ? '&status=' . $statusFilter : ''; ?>">
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
                                            <a class="page-link" href="?id=<?php echo $userId; ?>&page=<?php echo $i; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($statusFilter) ? '&status=' . $statusFilter : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $userId; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($statusFilter) ? '&status=' . $statusFilter : ''; ?>">
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
                            // Status statistieken ophalen
                            try {
                                $stmt = $conn->prepare("
                                    SELECT Status, COUNT(*) as count
                                    FROM Reservatie
                                    WHERE User_ID = ?
                                    GROUP BY Status
                                ");
                                $stmt->execute([$userId]);
                                $statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                                
                                // Aantal uren gereserveerd berekenen
                                $stmt = $conn->prepare("
                                    SELECT SUM(TIMESTAMPDIFF(HOUR, StartTijd, EindTijd)) as total_hours
                                    FROM Reservatie
                                    WHERE User_ID = ? AND Status IN ('goedgekeurd', 'afgerond')
                                ");
                                $stmt->execute([$userId]);
                                $totalHours = $stmt->fetch(PDO::FETCH_COLUMN);
                                
                                // Per printer statistieken
                                $stmt = $conn->prepare("
                                    SELECT p.Naam, COUNT(*) as count
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
                                $statusStats = [];
                                $totalHours = 0;
                                $printerStats = [];
                            }
                            ?>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
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
                            
                            <div class="col-xl-4 col-md-6 mb-4">
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
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card border-left-info h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Goedkeuringspercentage</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?php 
                                                    $approved = isset($statusStats['goedgekeurd']) ? $statusStats['goedgekeurd'] : 0;
                                                    $completed = isset($statusStats['afgerond']) ? $statusStats['afgerond'] : 0;
                                                    $total = $totalReservations ?: 1; // Voorkom delen door nul
                                                    echo round((($approved + $completed) / $total) * 100) . '%';
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Status Verdeling</h5>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Aantal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statusOptions as $status): ?>
                                            <tr>
                                                <td><?php echo ucfirst($status); ?></td>
                                                <td><?php echo isset($statusStats[$status]) ? $statusStats[$status] : 0; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Meest Gebruikte Printers</h5>
                                <?php if (empty($printerStats)): ?>
                                    <p class="text-muted">Geen printergegevens beschikbaar.</p>
                                <?php else: ?>
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Printer</th>
                                                <th>Aantal reserveringen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($printerStats as $printer => $count): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($printer); ?></td>
                                                    <td><?php echo $count; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>