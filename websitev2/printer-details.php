<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$currentPage = 'printers';
$pageTitle = 'Printer Details - 3D Printer Reserveringssysteem';

// Controleer of printer ID is opgegeven
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: printers.php');
    exit;
}

$printerId = $_GET['id'];

// Printer gegevens ophalen
try {
    $stmt = $conn->prepare("
        SELECT * FROM Printer 
        WHERE Printer_ID = ?
    ");
    $stmt->execute([$printerId]);
    $printer = $stmt->fetch();
    
    if (!$printer) {
        header('Location: printers.php');
        exit;
    }
    
    // Haal actieve reserveringen op voor deze printer
    $stmt = $conn->prepare("
        SELECT r.*, u.Voornaam, u.Naam
        FROM Reservatie r
        JOIN User u ON r.User_ID = u.User_ID
        WHERE r.Printer_ID = ? 
          AND r.PRINT_END > NOW()
        ORDER BY r.PRINT_START
        LIMIT 5
    ");
    $stmt->execute([$printerId]);
    $reservations = $stmt->fetchAll();
    
    // Haal beschikbare filamenten op
    $stmt = $conn->query("SELECT * FROM Filament ORDER BY Type, Kleur");
    $filaments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Er is een fout opgetreden: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="printers.php">Printers</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></li>
            </ol>
        </nav>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-5">
            <div class="card mb-4">
                <img src="<?php echo htmlspecialchars($printer['foto']); ?>" 
    class="card-img-top" 
    alt="<?php echo htmlspecialchars($printer['Versie_Toestel']); ?>" 
    onerror="this.outerHTML='<div class=\'card-img-top d-flex justify-content-center align-items-center bg-light\' style=\'height: 200px;\'><p class=\'text-muted mb-0\'>Geen afbeelding beschikbaar voor <?php echo htmlspecialchars($printer['Versie_Toestel']); ?></p></div>'">                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="card-title mb-0"><?php echo htmlspecialchars($printer['Versie_Toestel']); ?></h3>
                        <span class="badge bg-<?php 
                            if ($printer['Status'] === 'beschikbaar') echo 'success';
                            elseif ($printer['Status'] === 'onderhoud') echo 'warning';
                            elseif ($printer['Status'] === 'in_gebruik') echo 'primary';
                            else echo 'danger';
                        ?> py-2 px-3">
                            <?php 
                                if ($printer['Status'] === 'beschikbaar') echo 'Beschikbaar';
                                elseif ($printer['Status'] === 'onderhoud') echo 'Onderhoud';
                                elseif ($printer['Status'] === 'in_gebruik') echo 'In gebruik';
                                else echo ucfirst($printer['Status']);
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($printer['netwerkadres'])): ?>
                    <p class="card-text">
                        <i class="fas fa-network-wired text-muted me-2"></i> <strong>Netwerkadres:</strong> 
                        <a href="http://<?php echo htmlspecialchars($printer['netwerkadres']); ?>" target="_blank">
                            <?php echo htmlspecialchars($printer['netwerkadres']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($printer['Software'])): ?>
                    <p class="card-text">
                        <i class="fas fa-code text-muted me-2"></i> <strong>Software:</strong> 
                        <?php echo htmlspecialchars($printer['Software']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($printer['lokaal_id'])): ?>
                    <p class="card-text">
                        <i class="fas fa-map-marker-alt text-muted me-2"></i> <strong>Locatie:</strong> 
                        <?php 
                            // Je kunt hier een query toevoegen om de lokaal naam op te halen
                            echo "Lokaal " . htmlspecialchars($printer['lokaal_id']); 
                        ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="card-text mb-3">
                        <i class="fas fa-info-circle text-muted me-2"></i> <strong>Kenmerken:</strong>
                        <ul class="mt-2">
                            <?php if (!empty($printer['Software'])): ?>
                                <li>Software: <?php echo htmlspecialchars($printer['Software']); ?></li>
                            <?php endif; ?>
                            
                            <li>Versie: <?php echo htmlspecialchars($printer['Versie_Toestel']); ?></li>
                            
                            <?php if (isset($printer['Kwaliteit']) && !empty($printer['Kwaliteit'])): ?>
                                <li>Printkwaliteit: <?php echo htmlspecialchars($printer['Kwaliteit']); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <?php if ($printer['Status'] === 'beschikbaar' && isset($_SESSION['User_ID'])): ?>
                        <div class="d-grid">
                            <a href="reserve.php?printer_id=<?php echo $printer['Printer_ID']; ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-calendar-plus me-2"></i> Reserveren
                            </a>
                        </div>
                    <?php elseif ($printer['Status'] === 'beschikbaar'): ?>
                        <div class="d-grid">
                            <a href="login.php?redirect=<?php echo urlencode('printer-details.php?id=' . $printer['Printer_ID']); ?>" class="btn btn-outline-primary btn-lg">
                                Log in om te reserveren
                            </a>
                        </div>
                    <?php elseif ($printer['Status'] === 'in_gebruik'): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> Deze printer is momenteel in gebruik. Bekijk de planning voor beschikbaarheid.
                        </div>
                    <?php elseif ($printer['Status'] === 'onderhoud'): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-tools me-2"></i> Deze printer is momenteel in onderhoud.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i> Deze printer is niet beschikbaar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-7">
            <!-- Huidige planning -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Planning</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reservations)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> Er zijn geen geplande reserveringen voor deze printer.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Tijd</th>
                                        <th>Gebruiker</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td><?php echo date('d-m-Y', strtotime($reservation['PRINT_START'])); ?></td>
                                            <td>
                                                <?php echo date('H:i', strtotime($reservation['PRINT_START'])); ?> - 
                                                <?php echo date('H:i', strtotime($reservation['PRINT_END'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($reservation['Voornaam'] . ' ' . $reservation['Naam']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    if ($reservation['Status'] === 'wachtend') echo 'warning';
                                                    elseif ($reservation['Status'] === 'actief') echo 'success';
                                                    else echo 'primary';
                                                ?>">
                                                    <?php echo ucfirst($reservation['Status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <a href="calendar.php" class="btn btn-outline-primary">
                            <i class="fas fa-calendar me-2"></i> Bekijk volledige kalender
                        </a>
                    <?php endif; ?>
                </div>
            </div>
<!-- Ondersteunde filamenten -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i> Beschikbare Filamenten</h5>
    </div>
    <div class="card-body">
        <?php if (empty($filaments)): ?>
            <div class="alert alert-info">Geen filament informatie beschikbaar.</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <?php foreach ($filaments as $filament): ?>
                    <?php 
                    // Convert color name to hex code
                    $colorCode = '#CCCCCC'; // Default gray
                    
                    // Convert common color names to hex codes
                    $colorName = strtolower($filament['Kleur']);
                    switch ($colorName) {
                        case 'rood': case 'red': $colorCode = '#FF0000'; break;
                        case 'blauw': case 'blue': $colorCode = '#0000FF'; break;
                        case 'groen': case 'green': $colorCode = '#00FF00'; break;
                        case 'zwart': case 'black': $colorCode = '#000000'; break;
                        case 'wit': case 'white': $colorCode = '#FFFFFF'; break;
                        case 'geel': case 'yellow': $colorCode = '#FFFF00'; break;
                        case 'oranje': case 'orange': $colorCode = '#FFA500'; break;
                        case 'paars': case 'purple': $colorCode = '#800080'; break;
                        case 'roze': case 'pink': $colorCode = '#FFC0CB'; break;
                        case 'hout': case 'wood': case 'bruin': case 'brown': $colorCode = '#8B4513'; break;
                        case 'zilver': case 'silver': case 'grijs': case 'gray': $colorCode = '#C0C0C0'; break;
                        case 'goud': case 'gold': $colorCode = '#FFD700'; break;
                        case 'transparant': case 'transparent': case 'clear': $colorCode = '#E6F7FF'; break;
                        // Add more colors as needed
                    }
                    ?>
                    <div class="col">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <div class="me-3" style="width: 24px; height: 24px; background-color: <?php echo $colorCode; ?>; border-radius: 50%; border: 1px solid #ddd;"></div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($filament['Type']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($filament['Kleur']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>            
            <!-- Extra informatie -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Gebruiksinstructies</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="printerInstructions">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    Hoe gebruik je deze printer?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#printerInstructions">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Reserveer de printer via dit systeem</li>
                                        <li>Meld je aan bij een begeleider op het afgesproken tijdstip</li>
                                        <li>Zorg dat je 3D model klaar is voor het printen</li>
                                        <li>Volg de veiligheidsinstructies</li>
                                        <li>Na het printen, laat de werkplek netjes achter</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    Veiligheidsvoorschriften
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#printerInstructions">
                                <div class="accordion-body">
                                    <ul>
                                        <li>Raak nooit de verwarmde onderdelen aan</li>
                                        <li>Zorg voor voldoende ventilatie tijdens het printen</li>
                                        <li>Laat de printer nooit onbeheerd achter tijdens gebruik</li>
                                        <li>Bij storingen of problemen, contacteer direct een begeleider</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    Veelgestelde vragen
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#printerInstructions">
                                <div class="accordion-body">
                                    <dl>
                                        <dt>Hoe lang mag ik de printer gebruiken?</dt>
                                        <dd>Je kunt de printer reserveren voor de tijd die je nodig hebt, echter is de maximale aaneengesloten reserveringstijd 4 uur.</dd>
                                        
                                        <dt>Moet ik mijn eigen filament meenemen?</dt>
                                        <dd>Nee, er is een selectie aan filamenten beschikbaar. Deze kun je bij je reservering opgeven.</dd>
                                        
                                        <dt>Wat als mijn print niet klaar is binnen de gereserveerde tijd?</dt>
                                        <dd>Neem contact op met een begeleider om te bespreken of je reservering verlengd kan worden.</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>