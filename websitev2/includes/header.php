<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : '3D Printer Reserveringssysteem'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <?php
    // Debug commentaar in HTML broncode - kijk in View Source om dit te zien
    if (isset($_SESSION)) {
        echo "<!-- DEBUG: User_ID: " . (isset($_SESSION['User_ID']) ? $_SESSION['User_ID'] : 'niet ingesteld') . " -->";
        echo "<!-- DEBUG: Type: " . (isset($_SESSION['Type']) ? $_SESSION['Type'] : 'niet ingesteld') . " -->";
        echo "<!-- DEBUG: Is Admin?: " . (isset($_SESSION['Type']) && $_SESSION['Type'] == 'beheerder' ? 'JA' : 'NEE') . " -->";
    }
    ?>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-cube me-2"></i>
                    3D Printer Reservering
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'home' ? 'active' : ''; ?>" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'printers' ? 'active' : ''; ?>" href="printers.php">Printers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'calendar' ? 'active' : ''; ?>" href="calendar.php">Kalender</a>
                        </li>
                        <?php if (isset($_SESSION['User_ID'])): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage == 'reservations' ? 'active' : ''; ?>" href="reservations.php">Mijn Reserveringen</a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- BEGIN ADMIN MENU -->
                        <?php if (isset($_SESSION['Type']) && $_SESSION['Type'] === 'beheerder'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cogs me-1"></i> Beheer
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="admin/index.php">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="admin/users.php">Gebruikers</a></li>
                                    <li><a class="dropdown-item" href="admin/printers.php">Printers</a></li>
                                    <li><a class="dropdown-item" href="admin/reservations.php">Reserveringen</a></li>
                                    <li><a class="dropdown-item" href="admin/filaments.php">Filament</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <!-- EINDE ADMIN MENU -->
                    </ul>
                    
                    <div class="d-flex">
                        <?php if (isset($_SESSION['User_ID'])): ?>
                            <!-- INGELOGDE GEBRUIKER WEERGAVE -->
                            <div class="dropdown">
                                <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?php echo htmlspecialchars($_SESSION['Voornaam']); ?>
                                    <?php if (isset($_SESSION['Type']) && $_SESSION['Type'] === 'beheerder'): ?>
                                        <span class="badge bg-danger ms-1">Beheerder</span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="profile.php">Mijn Profiel</a></li>
                                    <li><a class="dropdown-item" href="reservations.php">Mijn Reserveringen</a></li>
                                    <?php if (isset($_SESSION['Type']) && $_SESSION['Type'] === 'beheerder'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="admin/index.php">Beheerdersdashboard</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php">Uitloggen</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <!-- NIET INGELOGDE GEBRUIKER WEERGAVE -->
                            <a href="login.php" class="btn btn-outline-light me-2">Inloggen</a>
                            <a href="register.php" class="btn btn-light">Registreren</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main class="py-4">
    
    <?php 
    // ADMIN DEBUG LINK - verwijder in productie
    if (isset($_SESSION['User_ID'])): 
    ?>
    <div class="container">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Admin Debug:</strong> 
            <?php echo isset($_SESSION['Type']) ? "Type = " . $_SESSION['Type'] : "Geen Type ingesteld"; ?> |
            <a href="admin_check.php" class="alert-link">Admin Toegang Check/Fix Tool</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>