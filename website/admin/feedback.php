<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Feedback Overzicht - 3D Printer Reserveringssysteem';
$currentPage = 'admin-feedback';

// Initialiseer variabelen
$errorMessage = '';
$successMessage = '';

// Bepaal sortering
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'feedback_datum';
$validSortFields = ['Reservatie_ID', 'feedback_datum', 'feedback_print_kwaliteit', 'feedback_gebruiksgemak'];
$sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'feedback_datum';

// Bepaal sorteervolgorde
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Filter op kwaliteitsscore
$printQualityFilter = isset($_GET['quality']) ? (int)$_GET['quality'] : 0;
$usabilityFilter = isset($_GET['usability']) ? (int)$_GET['usability'] : 0;

// Bouw WHERE clausule op basis van filters
$filterWhere = " WHERE r.feedback_gegeven = 1";
$filterParams = [];

if ($printQualityFilter > 0) {
    $filterWhere .= " AND r.feedback_print_kwaliteit = ?";
    $filterParams[] = $printQualityFilter;
}

if ($usabilityFilter > 0) {
    $filterWhere .= " AND r.feedback_gebruiksgemak = ?";
    $filterParams[] = $usabilityFilter;
}

// Paginering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10; // Aantal items per pagina
$offset = ($page - 1) * $perPage;

// Totaal aantal feedback items voor paginering
try {
    $countQuery = "SELECT COUNT(*) as total FROM Reservatie r" . $filterWhere;
    $stmt = $conn->prepare($countQuery);
    if (!empty($filterParams)) {
        $stmt->execute($filterParams);
    } else {
        $stmt->execute();
    }
    $totalFeedbacks = $stmt->fetch()['total'];
    $totalPages = ceil($totalFeedbacks / $perPage);
} catch (PDOException $e) {
    $errorMessage = 'Fout bij tellen van feedback items: ' . $e->getMessage();
    $totalFeedbacks = 0;
    $totalPages = 0;
}

// Feedback items ophalen
try {
    $query = "SELECT r.*, u.Voornaam, u.Naam, p.Versie_Toestel 
              FROM Reservatie r
              LEFT JOIN User u ON r.User_ID = u.User_ID
              LEFT JOIN Printer p ON r.Printer_ID = p.Printer_ID
              $filterWhere
              ORDER BY r.$sortBy $sortOrder
              LIMIT $perPage OFFSET $offset";
    
    $stmt = $conn->prepare($query);
    if (!empty($filterParams)) {
        $stmt->execute($filterParams);
    } else {
        $stmt->execute();
    }
    $feedbacks = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van feedback: ' . $e->getMessage();
    $feedbacks = [];
}

// Statistieken verzamelen
try {
    // Gemiddelde scores
    $stmt = $conn->query("SELECT 
                            AVG(feedback_print_kwaliteit) as avg_print_quality,
                            AVG(feedback_gebruiksgemak) as avg_usability,
                            COUNT(*) as total_feedback
                        FROM Reservatie 
                        WHERE feedback_gegeven = 1");
    $averages = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Distributie van printkwaliteit scores
    $stmt = $conn->query("SELECT 
                            feedback_print_kwaliteit as score,
                            COUNT(*) as count
                        FROM Reservatie 
                        WHERE feedback_gegeven = 1
                        GROUP BY feedback_print_kwaliteit
                        ORDER BY feedback_print_kwaliteit");
    $printQualityDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Distributie van gebruiksgemak scores
    $stmt = $conn->query("SELECT 
                            feedback_gebruiksgemak as score,
                            COUNT(*) as count
                        FROM Reservatie 
                        WHERE feedback_gegeven = 1
                        GROUP BY feedback_gebruiksgemak
                        ORDER BY feedback_gebruiksgemak");
    $usabilityDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Feedback per printer
    $stmt = $conn->query("SELECT 
                            p.Versie_Toestel,
                            COUNT(*) as count,
                            AVG(r.feedback_print_kwaliteit) as avg_quality
                        FROM Reservatie r
                        JOIN Printer p ON r.Printer_ID = p.Printer_ID
                        WHERE r.feedback_gegeven = 1
                        GROUP BY r.Printer_ID
                        ORDER BY count DESC");
    $printerFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Feedback over tijd
    $stmt = $conn->query("SELECT 
                            DATE_FORMAT(feedback_datum, '%Y-%m') as month,
                            COUNT(*) as count,
                            AVG(feedback_print_kwaliteit) as avg_quality,
                            AVG(feedback_gebruiksgemak) as avg_usability
                        FROM Reservatie
                        WHERE feedback_gegeven = 1
                        GROUP BY DATE_FORMAT(feedback_datum, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 6");
    $feedbackOverTime = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van statistieken: ' . $e->getMessage();
    $averages = ['avg_print_quality' => 0, 'avg_usability' => 0, 'total_feedback' => 0];
    $printQualityDistribution = [];
    $usabilityDistribution = [];
    $printerFeedback = [];
    $feedbackOverTime = [];
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
    <style>
        .star-rating {
            color: #ffc107;
            font-size: 1.2em;
        }
        .feedback-text {
            max-height: 100px;
            overflow-y: auto;
        }
    </style>
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
                            <a class="nav-link active" href="feedback.php">
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
                        <i class="fas fa-comments me-2"></i> Feedback Overzicht
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
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
                
                <!-- Statistieken -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Totaal aantal feedback</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $averages['total_feedback']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-comments fa-2x text-gray-300"></i>
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
                                            Gemiddelde printkwaliteit</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($averages['avg_print_quality'], 1); ?> / 5
                                            <span class="star-rating">
                                                <?php echo str_repeat('★', round($averages['avg_print_quality'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-star fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Gemiddeld gebruiksgemak</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($averages['avg_usability'], 1); ?> / 5
                                            <span class="star-rating">
                                                <?php echo str_repeat('★', round($averages['avg_usability'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-thumbs-up fa-2x text-gray-300"></i>
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
                                            Response percentage</div>
                                        <?php 
                                            // Bereken het percentage van voltooide prints die feedback hebben
                                            $stmt = $conn->query("SELECT COUNT(*) FROM Reservatie WHERE print_completed = 1");
                                            $totalCompleted = $stmt->fetchColumn();
                                            $feedbackRate = ($totalCompleted > 0) ? round(($averages['total_feedback'] / $totalCompleted) * 100) : 0;
                                        ?>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $feedbackRate; ?>%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percent fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter en sortering -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="feedback.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="quality" class="form-label">Filter op printkwaliteit</label>
                                <select name="quality" id="quality" class="form-select" onchange="this.form.submit()">
                                    <option value="0">Alle beoordelingen</option>
                                    <option value="5" <?php echo ($printQualityFilter == 5) ? 'selected' : ''; ?>>5 sterren</option>
                                    <option value="4" <?php echo ($printQualityFilter == 4) ? 'selected' : ''; ?>>4 sterren</option>
                                    <option value="3" <?php echo ($printQualityFilter == 3) ? 'selected' : ''; ?>>3 sterren</option>
                                    <option value="2" <?php echo ($printQualityFilter == 2) ? 'selected' : ''; ?>>2 sterren</option>
                                    <option value="1" <?php echo ($printQualityFilter == 1) ? 'selected' : ''; ?>>1 ster</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="usability" class="form-label">Filter op gebruiksgemak</label>
                                <select name="usability" id="usability" class="form-select" onchange="this.form.submit()">
                                    <option value="0">Alle beoordelingen</option>
                                    <option value="5" <?php echo ($usabilityFilter == 5) ? 'selected' : ''; ?>>5 sterren</option>
                                    <option value="4" <?php echo ($usabilityFilter == 4) ? 'selected' : ''; ?>>4 sterren</option>
                                    <option value="3" <?php echo ($usabilityFilter == 3) ? 'selected' : ''; ?>>3 sterren</option>
                                    <option value="2" <?php echo ($usabilityFilter == 2) ? 'selected' : ''; ?>>2 sterren</option>
                                    <option value="1" <?php echo ($usabilityFilter == 1) ? 'selected' : ''; ?>>1 ster</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sort" class="form-label">Sorteer op</label>
                                <div class="input-group">
                                    <select name="sort" id="sort" class="form-select">
                                        <option value="feedback_datum" <?php echo ($sortBy == 'feedback_datum') ? 'selected' : ''; ?>>Datum</option>
                                        <option value="feedback_print_kwaliteit" <?php echo ($sortBy == 'feedback_print_kwaliteit') ? 'selected' : ''; ?>>Printkwaliteit</option>
                                        <option value="feedback_gebruiksgemak" <?php echo ($sortBy == 'feedback_gebruiksgemak') ? 'selected' : ''; ?>>Gebruiksgemak</option>
                                        <option value="Reservatie_ID" <?php echo ($sortBy == 'Reservatie_ID') ? 'selected' : ''; ?>>Reservatie ID</option>
                                    </select>
                                    <select name="order" id="order" class="form-select">
                                        <option value="desc" <?php echo ($sortOrder == 'DESC') ? 'selected' : ''; ?>>Aflopend</option>
                                        <option value="asc" <?php echo ($sortOrder == 'ASC') ? 'selected' : ''; ?>>Oplopend</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Sorteer</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Feedback tabel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-comments me-1"></i>
                        Feedback overzicht (<?php echo $totalFeedbacks; ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($feedbacks)): ?>
                            <div class="alert alert-info">
                                Geen feedback gevonden met de huidige filterinstellingen.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Gebruiker</th>
                                            <th>Printer</th>
                                            <th>Datum</th>
                                            <th>Printkwaliteit</th>
                                            <th>Gebruiksgemak</th>
                                            <th>Feedback</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feedbacks as $feedback): ?>
                                            <tr>
                                                <td><?php echo $feedback['Reservatie_ID']; ?></td>
                                                <td>
                                                    <a href="view_user_reservations.php?id=<?php echo $feedback['User_ID']; ?>">
                                                        <?php echo htmlspecialchars($feedback['Voornaam'] . ' ' . $feedback['Naam']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($feedback['Versie_Toestel']); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($feedback['feedback_datum'])); ?></td>
                                                <td>
                                                    <span class="star-rating">
                                                        <?php echo str_repeat('★', $feedback['feedback_print_kwaliteit']); ?>
                                                    </span>
                                                    (<?php echo $feedback['feedback_print_kwaliteit']; ?>)
                                                </td>
                                                <td>
                                                    <span class="star-rating">
                                                        <?php echo str_repeat('★', $feedback['feedback_gebruiksgemak']); ?>
                                                    </span>
                                                    (<?php echo $feedback['feedback_gebruiksgemak']; ?>)
                                                </td>
                                                <td>
                                                    <div class="feedback-text">
                                                        <?php echo nl2br(htmlspecialchars($feedback['feedback_tekst'])); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="reservation-detail.php?id=<?php echo $feedback['Reservatie_ID']; ?>" class="btn btn-info" title="Reserveringsdetails bekijken">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $feedback['Reservatie_ID']; ?>" title="Volledige feedback bekijken">
                                                            <i class="fas fa-expand-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Modal voor volledige feedback -->
                                            <div class="modal fade" id="feedbackModal<?php echo $feedback['Reservatie_ID']; ?>" tabindex="-1" aria-labelledby="feedbackModalLabel<?php echo $feedback['Reservatie_ID']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="feedbackModalLabel<?php echo $feedback['Reservatie_ID']; ?>">
                                                                Feedback van <?php echo htmlspecialchars($feedback['Voornaam'] . ' ' . $feedback['Naam']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <p><strong>Reservering ID:</strong> <?php echo $feedback['Reservatie_ID']; ?></p>
                                                                    <p><strong>Printer:</strong> <?php echo htmlspecialchars($feedback['Versie_Toestel']); ?></p>
                                                                    <p><strong>Datum feedback:</strong> <?php echo date('d-m-Y H:i', strtotime($feedback['feedback_datum'])); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p>
                                                                        <strong>Printkwaliteit:</strong> 
                                                                        <span class="star-rating">
                                                                            <?php echo str_repeat('★', $feedback['feedback_print_kwaliteit']); ?>
                                                                        </span>
                                                                        (<?php echo $feedback['feedback_print_kwaliteit']; ?>/5)
                                                                    </p>
                                                                    <p>
                                                                        <strong>Gebruiksgemak:</strong> 
                                                                        <span class="star-rating">
                                                                            <?php echo str_repeat('★', $feedback['feedback_gebruiksgemak']); ?>
                                                                        </span>
                                                                        (<?php echo $feedback['feedback_gebruiksgemak']; ?>/5)
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <h6>Feedback tekst:</h6>
                                                                <div class="p-3 bg-light rounded">
                                                                    <?php echo nl2br(htmlspecialchars($feedback['feedback_tekst'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <a href="reservation-detail.php?id=<?php echo $feedback['Reservatie_ID']; ?>" class="btn btn-info">
                                                                Bekijk reserveringsdetails
                                                            </a>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sluiten</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Paginering -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Feedbackpagina's">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo $printQualityFilter ? '&quality=' . $printQualityFilter : ''; ?><?php echo $usabilityFilter ? '&usability=' . $usabilityFilter : ''; ?>">
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo $printQualityFilter ? '&quality=' . $printQualityFilter : ''; ?><?php echo $usabilityFilter ? '&usability=' . $usabilityFilter : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo $printQualityFilter ? '&quality=' . $printQualityFilter : ''; ?><?php echo $usabilityFilter ? '&usability=' . $usabilityFilter : ''; ?>">
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
                
                <!-- Statistieken visualisaties -->
                <div class="row">
                    <!-- Scores verdeling -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-1"></i>
                                Verdeling van scores
                            </div>
                            <div class="card-body">
                                <h5>Printkwaliteit</h5>
                                <div class="mb-4">
                                    <?php
                                    // Normale print kwaliteit verdeling
                                    for ($i = 5; $i >= 1; $i--) {
                                        $count = $printQualityDistribution[$i] ?? 0;
                                        $percentage = ($averages['total_feedback'] > 0) ? round(($count / $averages['total_feedback']) * 100) : 0;
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="star-rating"><?php echo str_repeat('★', $i); ?></span>
                                            <span><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percentage; ?>%;" 
                                                aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                                
                                <h5>Gebruiksgemak</h5>
                                <div>
                                    <?php
                                    // Gebruiksgemak verdeling
                                    for ($i = 5; $i >= 1; $i--) {
                                        $count = $usabilityDistribution[$i] ?? 0;
                                        $percentage = ($averages['total_feedback'] > 0) ? round(($count / $averages['total_feedback']) * 100) : 0;
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="star-rating"><?php echo str_repeat('★', $i); ?></span>
                                            <span><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%;" 
                                                aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Feedback per printer -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-print me-1"></i>
                                Feedback per printer
                            </div>
                            <div class="card-body">
                                <?php if (empty($printerFeedback)): ?>
                                    <p class="text-muted">Geen printergegevens beschikbaar.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Printer</th>
                                                    <th>Aantal feedback</th>
                                                    <th>Gemiddelde kwaliteit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($printerFeedback as $printer): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></td>
                                                        <td><?php echo $printer['count']; ?></td>
                                                        <td>
                                                            <span class="star-rating">
                                                                <?php echo str_repeat('★', round($printer['avg_quality'])); ?>
                                                            </span>
                                                            (<?php echo number_format($printer['avg_quality'], 1); ?>)
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Feedback over tijd -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-1"></i>
                                Feedback over tijd
                            </div>
                            <div class="card-body">
                                <?php if (empty($feedbackOverTime)): ?>
                                    <p class="text-muted">Geen tijdgegevens beschikbaar.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Maand</th>
                                                    <th>Aantal</th>
                                                    <th>Gem. printkwaliteit</th>
                                                    <th>Gem. gebruiksgemak</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($feedbackOverTime as $month): 
                                                    // Formateer de maand voor weergave
                                                    $monthDate = DateTime::createFromFormat('Y-m', $month['month']);
                                                    $formattedMonth = $monthDate ? $monthDate->format('M Y') : $month['month'];
                                                ?>
                                                    <tr>
                                                        <td><?php echo $formattedMonth; ?></td>
                                                        <td><?php echo $month['count']; ?></td>
                                                        <td>
                                                            <span class="star-rating">
                                                                <?php echo str_repeat('★', round($month['avg_quality'])); ?>
                                                            </span>
                                                            (<?php echo number_format($month['avg_quality'], 1); ?>)
                                                        </td>
                                                        <td>
                                                            <span class="star-rating">
                                                                <?php echo str_repeat('★', round($month['avg_usability'])); ?>
                                                            </span>
                                                            (<?php echo number_format($month['avg_usability'], 1); ?>)
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