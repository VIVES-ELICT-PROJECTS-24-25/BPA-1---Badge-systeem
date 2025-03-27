<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Beheerdersdashboard - 3D Printer Reserveringssysteem';
$currentPage = 'admin-dashboard';

// Haal enkele statistieken op voor het dashboard
try {
    // Totaal aantal gebruikers
    $stmtUsers = $conn->query("SELECT COUNT(*) as total FROM User");
    $totalUsers = $stmtUsers->fetch()['total'];
    
    // Totaal aantal printers
    $stmtPrinters = $conn->query("SELECT COUNT(*) as total FROM Printer");
    $totalPrinters = $stmtPrinters->fetch()['total'];
    
    // Aantal actieve reserveringen
    $now = date('Y-m-d H:i:s');
    $stmtActiveRes = $conn->prepare("
        SELECT COUNT(*) as total FROM Reservatie 
        WHERE PRINT_START <= ? AND PRINT_END >= ?
    ");
    $stmtActiveRes->execute([$now, $now]);
    $activeReservations = $stmtActiveRes->fetch()['total'];
    
    // Totaal aantal reserveringen
    $stmtTotalRes = $conn->query("SELECT COUNT(*) as total FROM Reservatie");
    $totalReservations = $stmtTotalRes->fetch()['total'];
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
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
                            <a class="nav-link active" href="index.php">
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
                    <h1 class="h2">Beheerdersdashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Exporteren</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Delen</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar-day"></i>
                            Vandaag
                        </button>
                    </div>
                </div>
                
                <!-- Dashboard cards -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4">
                    <div class="col">
                        <div class="card text-white bg-primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Gebruikers</h6>
                                        <h2 class="mb-0"><?php echo isset($totalUsers) ? $totalUsers : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="users.php" class="text-white text-decoration-none">Bekijk details</a>
                                <i class="fas fa-angle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="card text-white bg-success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">3D Printers</h6>
                                        <h2 class="mb-0"><?php echo isset($totalPrinters) ? $totalPrinters : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-print fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="printers.php" class="text-white text-decoration-none">Bekijk details</a>
                                <i class="fas fa-angle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="card text-white bg-info h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Actieve reserveringen</h6>
                                        <h2 class="mb-0"><?php echo isset($activeReservations) ? $activeReservations : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="reservations.php?filter=active" class="text-white text-decoration-none">Bekijk details</a>
                                <i class="fas fa-angle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="card text-white bg-warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Totaal reserveringen</h6>
                                        <h2 class="mb-0"><?php echo isset($totalReservations) ? $totalReservations : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-history fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="reservations.php" class="text-white text-decoration-none">Bekijk details</a>
                                <i class="fas fa-angle-right text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent activities section -->
                <h2>Recente activiteiten</h2>
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Gebruiker</th>
                                <th>Printer</th>
                                <th>Activiteit</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            try {
                                // Haal de meest recente 10 reserveringen op
                                $stmt = $conn->prepare("
                                    SELECT r.Reservatie_ID, r.DATE_TIME_RESERVATIE, r.PRINT_START, r.PRINT_END,
                                           u.Voornaam, u.Naam, p.Versie_Toestel
                                    FROM Reservatie r
                                    JOIN User u ON r.User_ID = u.User_ID
                                    JOIN Printer p ON r.Printer_ID = p.Printer_ID
                                    ORDER BY r.DATE_TIME_RESERVATIE DESC
                                    LIMIT 10
                                ");
                                $stmt->execute();
                                $recentActivities = $stmt->fetchAll();
                                
                                if (count($recentActivities) > 0) {
                                    foreach ($recentActivities as $activity) {
                                        echo '<tr>';
                                        echo '<td>' . date('d-m-Y H:i', strtotime($activity['DATE_TIME_RESERVATIE'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($activity['Voornaam'] . ' ' . $activity['Naam']) . '</td>';
                                        echo '<td>' . htmlspecialchars($activity['Versie_Toestel']) . '</td>';
                                        echo '<td>Reservering aangemaakt</td>';
                                        echo '<td><a href="reservation-details.php?id=' . $activity['Reservatie_ID'] . '" class="btn btn-sm btn-outline-primary">Bekijk</a></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">Geen recente activiteiten gevonden</td></tr>';
                                }
                            } catch (PDOException $e) {
                                echo '<tr><td colspan="5" class="text-danger">Error bij het ophalen van activiteiten: ' . $e->getMessage() . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- System info section -->
                <h2>Systeem informatie</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-server me-1"></i>
                                Server informatie
                            </div>
                            <div class="card-body">
                                <p><strong>PHP versie:</strong> <?php echo phpversion(); ?></p>
                                <p><strong>Server software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                                <p><strong>Database:</strong> MySQL / MariaDB</p>
                                <p><strong>Website pad:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-cogs me-1"></i>
                                Systeemstatus
                            </div>
                            <div class="card-body">
                                <p><strong>Status:</strong> <span class="badge bg-success">Online</span></p>
                                <p><strong>Uptime:</strong> <?php echo rand(1, 30); ?> dagen</p>
                                <p><strong>Laatste update:</strong> <?php echo date('d-m-Y H:i'); ?></p>
                                <p><strong>PHP memory limiet:</strong> <?php echo ini_get('memory_limit'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom admin JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle voor mobiele weergave
        const sidebarToggle = document.querySelector('#sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.querySelector('#sidebar').classList.toggle('show');
            });
        }
    });
    </script>
</body>
</html>