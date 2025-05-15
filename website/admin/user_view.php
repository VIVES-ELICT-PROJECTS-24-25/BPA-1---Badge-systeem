<?php
// Admin toegang controle
require_once 'admin.php';

// Start output buffering om header-redirect problemen te voorkomen
ob_start();

$pageTitle = 'Gebruiker Profiel - 3D Printer Reserveringssysteem';
$currentPage = 'admin-users';

// Eenvoudige foutafhandeling zonder redirects die problemen kunnen veroorzaken
$error = '';

// Controleer of een gebruikers-ID is opgegeven
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Geen geldig gebruikers-ID opgegeven.';
} else {
    $userId = $_GET['id'];
    
    // Gebruikersgegevens ophalen
    try {
        $stmt = $conn->prepare("
            SELECT User_ID, Voornaam, Naam, Emailadres, Telefoon, Type, 
                   AanmaakAccount, LaatsteAanmelding, HuidigActief, HulpNodig
            FROM User 
            WHERE User_ID = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = 'Gebruiker niet gevonden.';
        } else {
            // Ophalen van VIVES-specifieke gegevens voor alle typen gebruikers
            $vivesInfo = null;
            // Zorg ervoor dat we VIVES-informatie ophalen voor student, docent en onderzoeker
            $stmt = $conn->prepare("
                SELECT v.*, o.naam as opleiding_naam
                FROM Vives v
                LEFT JOIN opleidingen o ON v.opleiding_id = o.id
                WHERE v.User_ID = ?
            ");
            $stmt->execute([$userId]);
            $vivesInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Opleidingsgegevens ophalen indien het een student is en er een opleiding_id is
            $opleiding = null;
            $opos = [];
            if ($user['Type'] == 'student' && $vivesInfo && isset($vivesInfo['opleiding_id'])) {
                // OPO's voor deze opleiding ophalen
                $stmt = $conn->prepare("
                    SELECT id, naam
                    FROM OPOs
                    WHERE opleiding_id = ?
                    ORDER BY naam
                ");
                $stmt->execute([$vivesInfo['opleiding_id']]);
                $opos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $error = 'Fout bij ophalen van gebruikersgegevens: ' . $e->getMessage();
    }
}

// Reserveringen ophalen (indien gebruiker bestaat)
$reservations = [];
$reservationCount = 0;
if (!$error && isset($user) && $user) {
    try {
        // Aantal reserveringen tellen
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM Reservatie WHERE User_ID = ?
        ");
        $stmt->execute([$userId]);
        $reservationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Recente reserveringen ophalen
        if ($reservationCount > 0) {
            $stmt = $conn->prepare("
                SELECT r.Reservatie_ID, r.PRINT_START, r.PRINT_END, r.Status, 
                       p.Naam as printer_naam, p.model as printer_model
                FROM Reservatie r
                LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
                WHERE r.User_ID = ?
                ORDER BY r.PRINT_START DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Bij fout met reserveringen, toon nog steeds de gebruikersgegevens
        // maar geen reserveringen
    }
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
                    <h1 class="h2">
                        <?php if (!empty($error)): ?>
                            Fout bij laden gebruikersprofiel
                        <?php else: ?>
                            Gebruikersprofiel: <?php echo htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']); ?>
                            <?php if (isset($_SESSION['User_ID']) && $user['User_ID'] == $_SESSION['User_ID']): ?>
                                <span class="badge bg-info">Jouw account</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="users.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Terug naar overzicht
                        </a>
                        <?php if (!empty($user)): ?>
                        <a href="edit_user.php?id=<?php echo $user['User_ID']; ?>" class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-edit"></i> Bewerken
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php else: ?>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <!-- Basisgegevens -->
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Basisgegevens</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th style="width: 30%;">ID:</th>
                                        <td><?php echo $user['User_ID']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Voornaam:</th>
                                        <td><?php echo htmlspecialchars($user['Voornaam']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Achternaam:</th>
                                        <td><?php echo htmlspecialchars($user['Naam']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>E-mail:</th>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($user['Emailadres']); ?>">
                                                <?php echo htmlspecialchars($user['Emailadres']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Telefoon:</th>
                                        <td>
                                            <?php if (!empty($user['Telefoon'])): ?>
                                                <a href="tel:<?php echo htmlspecialchars($user['Telefoon']); ?>">
                                                    <?php echo htmlspecialchars($user['Telefoon']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Niet opgegeven</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Type:</th>
                                        <td>
                                            <?php if ($user['Type'] == 'beheerder'): ?>
                                                <span class="badge bg-danger">Beheerder</span>
                                            <?php elseif ($user['Type'] == 'onderzoeker'): ?>
                                                <span class="badge bg-warning text-dark">Onderzoeker</span>
                                            <?php elseif ($user['Type'] == 'docent'): ?>
                                                <span class="badge bg-info">Docent</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Student</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Hulp nodig:</th>
                                        <td>
                                            <?php if (isset($user['HulpNodig']) && $user['HulpNodig']): ?>
                                                <span class="badge bg-success">Ja</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Nee</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php if ($user['HuidigActief']): ?>
                                                <span class="badge bg-success">Actief</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactief</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <!-- Account informatie -->
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Informatie</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th style="width: 40%;">Aangemaakt op:</th>
                                        <td><?php echo date('d-m-Y H:i', strtotime($user['AanmaakAccount'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Laatste aanmelding:</th>
                                        <td>
                                            <?php 
                                            if ($user['LaatsteAanmelding']) {
                                                echo date('d-m-Y H:i', strtotime($user['LaatsteAanmelding']));
                                            } else {
                                                echo '<span class="text-muted">Nooit</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Totaal reserveringen:</th>
                                        <td>
                                            <?php if ($reservationCount > 0): ?>
                                                <span class="badge bg-primary"><?php echo $reservationCount; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php if ($vivesInfo): ?>
                                <!-- VIVES Informatie -->
                                <h6 class="mt-4 mb-3 border-bottom pb-2">VIVES Informatie</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <th style="width: 40%;">VIVES ID:</th>
                                        <td>
                                            <?php echo htmlspecialchars($vivesInfo['Vives_id'] ?? 'Niet opgegeven'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>RFID kaartnummer:</th>
                                        <td>
                                            <?php if (isset($vivesInfo['rfidkaartnr']) && !empty($vivesInfo['rfidkaartnr'])): ?>
                                                <?php echo htmlspecialchars($vivesInfo['rfidkaartnr']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Niet opgegeven</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (isset($vivesInfo['opleiding_id']) && !empty($vivesInfo['opleiding_id'])): ?>
                                    <tr>
                                        <th>Opleiding:</th>
                                        <td>
                                            <?php if (isset($vivesInfo['opleiding_naam'])): ?>
                                                <strong><?php echo htmlspecialchars($vivesInfo['opleiding_naam']); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Niet opgegeven</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Type:</th>
                                        <td>
                                            <?php if (isset($vivesInfo['Type'])): ?>
                                                <?php if ($vivesInfo['Type'] == 'student'): ?>
                                                    <span class="badge bg-primary">Student</span>
                                                <?php elseif ($vivesInfo['Type'] == 'medewerker'): ?>
                                                    <span class="badge bg-info">Medewerker</span>
                                                <?php elseif ($vivesInfo['Type'] == 'onderzoeker'): ?>
                                                    <span class="badge bg-warning text-dark">Onderzoeker</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($vivesInfo['Type']); ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Niet opgegeven</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($user['Type'] == 'student' && !empty($opos)): ?>
                <!-- OPO's / Vakken -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Beschikbare OPO's voor deze opleiding</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($opos as $opo): ?>
                            <div class="col-md-4 mb-2">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div><?php echo htmlspecialchars($opo['naam']); ?></div>
                                            <span class="badge bg-secondary"><?php echo $opo['id']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($reservationCount > 0): ?>
                <!-- Recente Reserveringen -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recente Reserveringen</h5>
                        <a href="view_user_reservations.php?id=<?php echo $user['User_ID']; ?>" class="btn btn-sm btn-outline-primary">Alle reserveringen (<?php echo $reservationCount; ?>)</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                        <td><?php echo htmlspecialchars($reservation['printer_naam'] ?? 'Onbekend'); ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_START'])); ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($reservation['PRINT_END'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                if ($reservation['Status'] == 'wachtend') echo 'warning';
                                                elseif ($reservation['Status'] == 'actief') echo 'success';
                                                elseif ($reservation['Status'] == 'geannuleerd') echo 'danger';
                                                elseif ($reservation['Status'] == 'afgerond') echo 'info';
                                                else echo 'secondary';
                                            ?>">
                                                <?php echo ucfirst($reservation['Status'] ?? 'Onbekend'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="reservation-detail.php?id=<?php echo $reservation['Reservatie_ID']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Verzend de output buffer
ob_end_flush();
?>