<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Gebruikersbeheer - 3D Printer Reserveringssysteem';
$currentPage = 'admin-users';

// Verwerken van acties
$successMessage = '';
$errorMessage = '';

// Gebruiker verwijderen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = $_GET['delete'];
    
    // Controleer of het niet de eigen account is
    if ($userId == $_SESSION['User_ID']) {
        $errorMessage = 'Je kunt je eigen account niet verwijderen.';
    } else {
        try {
            // Begin transactie
            $conn->beginTransaction();
            
            // Eerst alle reserveringen van deze gebruiker verwijderen/afhandelen
            $stmt = $conn->prepare("DELETE FROM Reservatie WHERE User_ID = ?");
            $stmt->execute([$userId]);
            
            // Daarna de gebruiker verwijderen
            $stmt = $conn->prepare("DELETE FROM User WHERE User_ID = ?");
            $stmt->execute([$userId]);
            
            $conn->commit();
            $successMessage = 'Gebruiker is succesvol verwijderd.';
        } catch (PDOException $e) {
            $conn->rollBack();
            $errorMessage = 'Fout bij verwijderen van gebruiker: ' . $e->getMessage();
        }
    }
}

// Gebruiker status wijzigen naar beheerder of terug
if (isset($_GET['make_admin']) && is_numeric($_GET['make_admin'])) {
    $userId = $_GET['make_admin'];
    
    try {
        $stmt = $conn->prepare("UPDATE User SET Type = 'beheerder' WHERE User_ID = ?");
        $stmt->execute([$userId]);
        $successMessage = 'Gebruiker is succesvol gepromoveerd naar beheerder.';
    } catch (PDOException $e) {
        $errorMessage = 'Fout bij promoveren van gebruiker: ' . $e->getMessage();
    }
}

if (isset($_GET['remove_admin']) && is_numeric($_GET['remove_admin'])) {
    $userId = $_GET['remove_admin'];
    
    // Controleer of het niet de eigen account is
    if ($userId == $_SESSION['User_ID']) {
        $errorMessage = 'Je kunt je eigen beheerdersrechten niet intrekken.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE User SET Type = 'student' WHERE User_ID = ?");
            $stmt->execute([$userId]);
            $successMessage = 'Beheerdersrechten zijn ingetrokken.';
        } catch (PDOException $e) {
            $errorMessage = 'Fout bij intrekken van beheerdersrechten: ' . $e->getMessage();
        }
    }
}

// Gebruikers ophalen
try {
    // Bepaal sortering
    $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'User_ID';
    $validSortFields = ['User_ID', 'Voornaam', 'Naam', 'Emailadres', 'Type', 'AanmaakAccount', 'LaatsteAanmelding'];
    $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'User_ID';
    
    // Bepaal sorteervolgorde
    $sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
    
    // Zoekfilter
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchWhere = '';
    $searchParams = [];
    
    if (!empty($searchTerm)) {
        $searchWhere = " WHERE (Voornaam LIKE ? OR Naam LIKE ? OR Emailadres LIKE ?)";
        $searchParams = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
    }
    
    // Paginering
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 20; // Aantal items per pagina
    $offset = ($page - 1) * $perPage;
    
    // Totaal aantal gebruikers voor paginering
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM User" . $searchWhere);
    if (!empty($searchParams)) {
        $stmt->execute($searchParams);
    } else {
        $stmt->execute();
    }
    $totalUsers = $stmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $perPage);
    
    // Gebruikers ophalen
    $stmt = $conn->prepare(
        "SELECT User_ID, Voornaam, Naam, Emailadres, Telefoon, Type, AanmaakAccount, LaatsteAanmelding, HuidigActief 
        FROM User" . $searchWhere . " 
        ORDER BY $sortBy $sortOrder 
        LIMIT $perPage OFFSET $offset"
    );
    
    if (!empty($searchParams)) {
        $stmt->execute($searchParams);
    } else {
        $stmt->execute();
    }
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $errorMessage = 'Fout bij ophalen van gebruikers: ' . $e->getMessage();
    $users = [];
    $totalPages = 0;
}

// Aantal reserveringen per gebruiker ophalen
$userReservations = [];
try {
    foreach ($users as $user) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Reservatie WHERE User_ID = ?");
        $stmt->execute([$user['User_ID']]);
        $userReservations[$user['User_ID']] = $stmt->fetch()['count'];
    }
} catch (PDOException $e) {
    // Bij fout gewoon doorgaan, aantal reserveringen is niet kritisch
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
                    <h1 class="h2">Gebruikersbeheer</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_user.php" class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-user-plus"></i> Nieuwe gebruiker
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Afdrukken
                        </button>
                    </div>
                </div>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Zoek- en filteropties -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="users.php" method="get" class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" placeholder="Zoek op naam of e-mail" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-outline-secondary" type="submit">Zoeken</button>
                                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                        <a href="users.php" class="btn btn-outline-danger">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="sort" class="form-select">
                                    <option value="User_ID" <?php echo ($sortBy == 'User_ID') ? 'selected' : ''; ?>>Sorteer op ID</option>
                                    <option value="Naam" <?php echo ($sortBy == 'Naam') ? 'selected' : ''; ?>>Sorteer op achternaam</option>
                                    <option value="Type" <?php echo ($sortBy == 'Type') ? 'selected' : ''; ?>>Sorteer op type</option>
                                    <option value="AanmaakAccount" <?php echo ($sortBy == 'AanmaakAccount') ? 'selected' : ''; ?>>Sorteer op registratiedatum</option>
                                    <option value="LaatsteAanmelding" <?php echo ($sortBy == 'LaatsteAanmelding') ? 'selected' : ''; ?>>Sorteer op laatste login</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="order" class="form-select">
                                    <option value="asc" <?php echo ($sortOrder == 'ASC') ? 'selected' : ''; ?>>Oplopend</option>
                                    <option value="desc" <?php echo ($sortOrder == 'DESC') ? 'selected' : ''; ?>>Aflopend</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Gebruikerstabel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Gebruikersoverzicht
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Naam</th>
                                        <th>E-mail</th>
                                        <th>Type</th>
                                        <th>Geregistreerd</th>
                                        <th>Laatste login</th>
                                        <th>Status</th>
                                        <th>Reserveringen</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Geen gebruikers gevonden</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['User_ID']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['Voornaam'] . ' ' . $user['Naam']); ?></strong>
                                                    <?php if ($user['User_ID'] == $_SESSION['User_ID']): ?>
                                                        <span class="badge bg-info">Jij</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['Emailadres']); ?></td>
                                                <td>
                                                    <?php if ($user['Type'] == 'beheerder'): ?>
                                                        <span class="badge bg-danger">Beheerder</span>
                                                    <?php elseif ($user['Type'] == 'onderzoeker'): ?>
                                                        <span class="badge bg-warning text-dark">Onderzoeker</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Student</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d-m-Y', strtotime($user['AanmaakAccount'])); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($user['LaatsteAanmelding']) {
                                                        echo date('d-m-Y H:i', strtotime($user['LaatsteAanmelding']));
                                                    } else {
                                                        echo '<span class="text-muted">Nooit</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['HuidigActief']): ?>
                                                        <span class="badge bg-success">Actief</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactief</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $count = isset($userReservations[$user['User_ID']]) ? $userReservations[$user['User_ID']] : 0;
                                                    echo $count;
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit_user.php?id=<?php echo $user['User_ID']; ?>" class="btn btn-primary" title="Bewerken">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view_user_reservations.php?id=<?php echo $user['User_ID']; ?>" class="btn btn-info" title="Reserveringen bekijken">
                                                            <i class="fas fa-calendar"></i>
                                                        </a>
                                                        
                                                        <?php if ($user['Type'] != 'beheerder'): ?>
                                                            <a href="users.php?make_admin=<?php echo $user['User_ID']; ?>" 
                                                               class="btn btn-warning" 
                                                               title="Promoveren naar beheerder"
                                                               onclick="return confirm('Weet je zeker dat je deze gebruiker wilt promoveren naar beheerder?')">
                                                                <i class="fas fa-user-shield"></i>
                                                            </a>
                                                        <?php elseif ($user['User_ID'] != $_SESSION['User_ID']): ?>
                                                            <a href="users.php?remove_admin=<?php echo $user['User_ID']; ?>" 
                                                               class="btn btn-secondary" 
                                                               title="Beheerdersrechten intrekken"
                                                               onclick="return confirm('Weet je zeker dat je de beheerdersrechten wilt intrekken?')">
                                                                <i class="fas fa-user-minus"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($user['User_ID'] != $_SESSION['User_ID']): ?>
                                                            <a href="users.php?delete=<?php echo $user['User_ID']; ?>" 
                                                               class="btn btn-danger btn-delete" 
                                                               title="Verwijderen"
                                                               onclick="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen? Alle reserveringen worden ook verwijderd!')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginering -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Gebruikerspagina's">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
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
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>