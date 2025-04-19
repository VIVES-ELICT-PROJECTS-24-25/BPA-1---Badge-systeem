<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Controleer of gebruiker admin is
if (!isset($_SESSION['User_ID']) || !isset($_SESSION['Type']) || $_SESSION['Type'] != 'beheerder') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Admin Dashboard - 3D Printer Reserveringssysteem';

// Statistieken ophalen
// 1. Totaal aantal gebruikers
$stmt = $conn->query("SELECT COUNT(*) as count FROM User");
$totalUsers = $stmt->fetch()['count'];

// 2. Totaal aantal printers
$stmt = $conn->query("SELECT COUNT(*) as count FROM Printer");
$totalPrinters = $stmt->fetch()['count'];

// 3. Beschikbare printers
$stmt = $conn->query("SELECT COUNT(*) as count FROM Printer WHERE Status = 'beschikbaar'");
$availablePrinters = $stmt->fetch()['count'];

// 4. Printers in onderhoud
$stmt = $conn->query("SELECT COUNT(*) as count FROM Printer WHERE Status = 'onderhoud'");
$maintenancePrinters = $stmt->fetch()['count'];

// 5. Totaal aantal reserveringen
$stmt = $conn->query("SELECT COUNT(*) as count FROM Reservatie");
$totalReservations = $stmt->fetch()['count'];

// 6. Reserveringen vandaag
$stmt = $conn->query("SELECT COUNT(*) as count FROM Reservatie WHERE DATE(PRINT_START) = CURDATE()");
$todayReservations = $stmt->fetch()['count'];

// 7. Recente reserveringen
$stmt = $conn->query("
    SELECT r.Reservatie_ID, r.DATE_TIME_RESERVATIE, r.PRINT_START, r.PRINT_END, 
           u.Voornaam, u.Naam, p.Versie_Toestel, r.Status
    FROM Reservatie r
    JOIN User u ON r.User_ID = u.User_ID
    JOIN Printer p ON r.Printer_ID = p.Printer_ID
    ORDER BY r.DATE_TIME_RESERVATIE DESC
    LIMIT 5
");
$recentReservations = $stmt->fetchAll();

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
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="opleidingen.php">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Opleidingen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="opos.php">
                                <i class="fas fa-book me-2"></i>
                                OPO's
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="stats.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Statistieken
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar-day"></i> Vandaag
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar-week"></i> Deze week
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Statistieken cards -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">
                    <div class="col">
                        <div class="card text-white bg-primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Totaal Gebruikers</h6>
                                        <h2 class="mb-0"><?php echo $totalUsers; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="users.php" class="text-white text-decoration-none small">Bekijk details</a>
                                <i class="fas fa-chevron-right text-white small"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="card text-white bg-success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Beschikbare Printers</h6>
                                        <h2 class="mb-0"><?php echo $availablePrinters; ?> / <?php echo $totalPrinters; ?></h2>
                                    </div>
                                    <i class="fas fa-print fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="printers.php" class="text-white text-decoration-none small">Bekijk details</a>
                                <i class="fas fa-chevron-right text-white small"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="card text-white bg-warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">In Onderhoud</h6>
                                        <h2 class="mb-0"><?php echo $maintenancePrinters; ?></h2>
                                    </div>
                                    <i class="fas fa-tools fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="printers.php?status=onderhoud" class="text-white text-decoration-none small">Bekijk details</a>
                                <i class="fas fa-chevron-right text-white small"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="card text-white bg-info h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Reserveringen Vandaag</h6>
                                        <h2 class="mb-0"><?php echo $todayReservations; ?></h2>
                                    </div>
                                    <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="reservations.php?date=today" class="text-white text-decoration-none small">Bekijk details</a>
                                <i class="fas fa-chevron-right text-white small"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recente reserveringen -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Recente Reserveringen
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Gebruiker</th>
                                        <th>Printer</th>
                                        <th>Start</th>
                                        <th>Einde</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentReservations)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Geen recente reserveringen gevonden</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentReservations as $reservation): ?>
                                            <tr>
                                                <td><?php echo $reservation['Reservatie_ID']; ?></td>
                                                <td><?php echo htmlspecialchars($reservation['Voornaam'] . ' ' . $reservation['Naam']); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['Versie_Toestel']); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_START'])); ?></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_END'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        if ($reservation['Status'] == 'wachtend') echo 'warning';
                                                        elseif ($reservation['Status'] == 'actief') echo 'success';
                                                        elseif ($reservation['Status'] == 'geannuleerd') echo 'danger';
                                                        else echo 'secondary';
                                                    ?>">
                                                        <?php echo ucfirst($reservation['Status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="reservation-detail.php?id=<?php echo $reservation['Reservatie_ID']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="reservations.php" class="btn btn-primary">Alle Reserveringen</a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin JS -->
    <script src="../assets/js/admin.js"></script>
</body>
</html>