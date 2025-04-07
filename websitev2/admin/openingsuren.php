<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Openingsuren beheren - 3D Printer Reserveringssysteem';
$currentPage = 'openingsuren';

// Bericht variable voor feedback
$bericht = '';
$lokaalBericht = '';

// CRUD-operaties verwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Toevoegen van een nieuw lokaal
    if (isset($_POST['action']) && $_POST['action'] === 'add_lokaal') {
        $locatie = trim($_POST['locatie']);
        
        if (empty($locatie)) {
            $lokaalBericht = '<div class="alert alert-danger">Locatie mag niet leeg zijn.</div>';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO Lokalen (Locatie) VALUES (?)");
                $stmt->execute([$locatie]);
                $lokaalBericht = '<div class="alert alert-success">Lokaal succesvol toegevoegd!</div>';
            } catch (PDOException $e) {
                $lokaalBericht = '<div class="alert alert-danger">Fout bij het toevoegen van lokaal: ' . $e->getMessage() . '</div>';
            }
        }
    }
    
    // Toevoegen van openingsuren
    if (isset($_POST['action']) && $_POST['action'] === 'add_openingsuren') {
        $lokaal_id = $_POST['lokaal_id'];
        $start_date = $_POST['start_date'];
        $start_time = $_POST['start_time'];
        $eind_date = $_POST['eind_date'];
        $eind_time = $_POST['eind_time'];
        
        $tijdstip_start = $start_date . ' ' . $start_time . ':00';
        $tijdstip_einde = $eind_date . ' ' . $eind_time . ':00';
        
        if (strtotime($tijdstip_start) >= strtotime($tijdstip_einde)) {
            $bericht = '<div class="alert alert-danger">Eindtijd moet na starttijd liggen.</div>';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO Openingsuren (Lokaal_id, Tijdstip_start, Tijdstip_einde) VALUES (?, ?, ?)");
                $stmt->execute([$lokaal_id, $tijdstip_start, $tijdstip_einde]);
                $bericht = '<div class="alert alert-success">Openingsuren succesvol toegevoegd!</div>';
            } catch (PDOException $e) {
                $bericht = '<div class="alert alert-danger">Fout bij het toevoegen van openingsuren: ' . $e->getMessage() . '</div>';
            }
        }
    }
    
    // Verwijderen van openingsuren
    if (isset($_POST['action']) && $_POST['action'] === 'delete_openingsuren') {
        $id = $_POST['id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM Openingsuren WHERE id = ?");
            $stmt->execute([$id]);
            $bericht = '<div class="alert alert-success">Openingsuren succesvol verwijderd!</div>';
        } catch (PDOException $e) {
            $bericht = '<div class="alert alert-danger">Fout bij het verwijderen van openingsuren: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Verwijderen van lokaal
    if (isset($_POST['action']) && $_POST['action'] === 'delete_lokaal') {
        $id = $_POST['id'];
        
        try {
            // Eerst controleren of er openingsuren gekoppeld zijn aan dit lokaal
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Openingsuren WHERE Lokaal_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                $lokaalBericht = '<div class="alert alert-danger">Dit lokaal kan niet worden verwijderd omdat er nog openingsuren aan gekoppeld zijn.</div>';
            } else {
                $stmt = $conn->prepare("DELETE FROM Lokalen WHERE id = ?");
                $stmt->execute([$id]);
                $lokaalBericht = '<div class="alert alert-success">Lokaal succesvol verwijderd!</div>';
            }
        } catch (PDOException $e) {
            $lokaalBericht = '<div class="alert alert-danger">Fout bij het verwijderen van lokaal: ' . $e->getMessage() . '</div>';
        }
    }
}

// Ophalen van alle lokalen
try {
    $stmtLokalen = $conn->query("SELECT id, Locatie FROM Lokalen ORDER BY Locatie");
    $lokalen = $stmtLokalen->fetchAll();
} catch (PDOException $e) {
    $lokaalBericht = '<div class="alert alert-danger">Fout bij het ophalen van lokalen: ' . $e->getMessage() . '</div>';
}

// Ophalen van alle openingsuren
try {
    $stmtOpeningsuren = $conn->query("
        SELECT o.id, o.Tijdstip_start, o.Tijdstip_einde, l.Locatie, l.id as lokaal_id
        FROM Openingsuren o
        JOIN Lokalen l ON o.Lokaal_id = l.id
        ORDER BY o.Tijdstip_start DESC
    ");
    $openingsuren = $stmtOpeningsuren->fetchAll();
} catch (PDOException $e) {
    $bericht = '<div class="alert alert-danger">Fout bij het ophalen van openingsuren: ' . $e->getMessage() . '</div>';
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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
                            <a class="nav-link active" href="openingsuren.php">
                                <i class="fas fa-clock me-2"></i>
                                Openingsuren
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
                    <h1 class="h2"><i class="fas fa-clock me-2"></i>Openingsuren beheren</h1>
                </div>
                
                <!-- Tabs voor verschillende onderdelen -->
                <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="openingsuren-tab" data-bs-toggle="tab" data-bs-target="#openingsuren" 
                                type="button" role="tab" aria-controls="openingsuren" aria-selected="true">
                            <i class="fas fa-clock me-2"></i>Openingsuren
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="lokalen-tab" data-bs-toggle="tab" data-bs-target="#lokalen" 
                                type="button" role="tab" aria-controls="lokalen" aria-selected="false">
                            <i class="fas fa-building me-2"></i>Lokalen
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="myTabContent">
                    <!-- Openingsuren tab -->
                    <div class="tab-pane fade show active" id="openingsuren" role="tabpanel" aria-labelledby="openingsuren-tab">
                        <div class="row mb-4">
                            <div class="col-md-5">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h3 class="h5 mb-0"><i class="fas fa-plus-circle me-2"></i>Openingsuren toevoegen</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php echo $bericht; ?>
                                        
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="add_openingsuren">
                                            
                                            <div class="mb-3">
                                                <label for="lokaal_id" class="form-label">Lokaal</label>
                                                <select class="form-select" id="lokaal_id" name="lokaal_id" required>
                                                    <option value="">Selecteer lokaal</option>
                                                    <?php if(isset($lokalen) && count($lokalen) > 0): ?>
                                                        <?php foreach ($lokalen as $lokaal): ?>
                                                            <option value="<?php echo $lokaal['id']; ?>"><?php echo htmlspecialchars($lokaal['Locatie']); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <div class="form-text">Geen lokaal gevonden? Voeg eerst een lokaal toe via het "Lokalen" tabblad.</div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col">
                                                    <label for="start_date" class="form-label">Startdatum</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                                </div>
                                                <div class="col">
                                                    <label for="start_time" class="form-label">Starttijd</label>
                                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col">
                                                    <label for="eind_date" class="form-label">Einddatum</label>
                                                    <input type="date" class="form-control" id="eind_date" name="eind_date" required>
                                                </div>
                                                <div class="col">
                                                    <label for="eind_time" class="form-label">Eindtijd</label>
                                                    <input type="time" class="form-control" id="eind_time" name="eind_time" required>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Openingsuren toevoegen</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h3 class="h5 mb-0"><i class="fas fa-info-circle me-2"></i>Informatie</h3>
                                    </div>
                                    <div class="card-body">
                                        <p>Op deze pagina kunt u de openingsuren beheren voor de verschillende lokalen waar 3D printers beschikbaar zijn.</p>
                                        <p><strong>Let op:</strong></p>
                                        <ul>
                                            <li>Openingsuren bepalen wanneer gebruikers printers kunnen reserveren</li>
                                            <li>Zorg ervoor dat openingsuren niet overlappen voor hetzelfde lokaal</li>
                                            <li>Verwijder openingsuren die verlopen zijn om het systeem netjes te houden</li>
                                            <li>Reserveringen kunnen alleen worden gemaakt binnen openingsuren</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="h5 mb-0"><i class="fas fa-list me-2"></i>Overzicht openingsuren</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="openingsurenTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Lokaal</th>
                                                <th>Startdatum</th>
                                                <th>Starttijd</th>
                                                <th>Einddatum</th>
                                                <th>Eindtijd</th>
                                                <th>Acties</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($openingsuren) && count($openingsuren) > 0): ?>
                                                <?php foreach ($openingsuren as $uur): ?>
                                                    <tr>
                                                        <td><?php echo $uur['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($uur['Locatie']); ?></td>
                                                        <td><?php echo date('d-m-Y', strtotime($uur['Tijdstip_start'])); ?></td>
                                                        <td><?php echo date('H:i', strtotime($uur['Tijdstip_start'])); ?></td>
                                                        <td><?php echo date('d-m-Y', strtotime($uur['Tijdstip_einde'])); ?></td>
                                                        <td><?php echo date('H:i', strtotime($uur['Tijdstip_einde'])); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary edit-openingsuren" 
                                                                    data-bs-toggle="modal" data-bs-target="#editOpeningsurenModal"
                                                                    data-id="<?php echo $uur['id']; ?>"
                                                                    data-lokaal="<?php echo $uur['lokaal_id']; ?>"
                                                                    data-start="<?php echo $uur['Tijdstip_start']; ?>"
                                                                    data-eind="<?php echo $uur['Tijdstip_einde']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Weet je zeker dat je deze openingsuren wilt verwijderen?');">
                                                                <input type="hidden" name="action" value="delete_openingsuren">
                                                                <input type="hidden" name="id" value="<?php echo $uur['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Geen openingsuren gevonden</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lokalen tab -->
                    <div class="tab-pane fade" id="lokalen" role="tabpanel" aria-labelledby="lokalen-tab">
                        <div class="row mb-4">
                            <div class="col-md-5">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h3 class="h5 mb-0"><i class="fas fa-plus-circle me-2"></i>Lokaal toevoegen</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php echo $lokaalBericht; ?>
                                        
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="add_lokaal">
                                            
                                            <div class="mb-3">
                                                <label for="locatie" class="form-label">Locatie naam</label>
                                                <input type="text" class="form-control" id="locatie" name="locatie" required 
                                                       placeholder="Bijv. Lokaal D1.07 of Lab 3D Printen">
                                            </div>
                                            
                                            <button type="submit" class="btn btn-success">Lokaal toevoegen</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h3 class="h5 mb-0"><i class="fas fa-info-circle me-2"></i>Informatie</h3>
                                    </div>
                                    <div class="card-body">
                                        <p>Beheer hier de lokalen waar 3D printers beschikbaar zijn.</p>
                                        <p><strong>Let op:</strong></p>
                                        <ul>
                                            <li>Een lokaal kan niet worden verwijderd als er nog openingsuren aan gekoppeld zijn</li>
                                            <li>Geef een duidelijke beschrijving van de locatie zodat gebruikers het gemakkelijk kunnen vinden</li>
                                            <li>Je kunt openingsuren per lokaal instellen in het "Openingsuren" tabblad</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="h5 mb-0"><i class="fas fa-list me-2"></i>Overzicht lokalen</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="lokalenTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Locatie</th>
                                                <th>Aantal openingsuren</th>
                                                <th>Acties</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($lokalen) && count($lokalen) > 0): ?>
                                                <?php foreach ($lokalen as $lokaal): 
                                                    // Tel aantal openingsuren per lokaal
                                                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Openingsuren WHERE Lokaal_id = ?");
                                                    $stmt->execute([$lokaal['id']]);
                                                    $aantalOpeningsuren = $stmt->fetch()['total'];
                                                ?>
                                                    <tr>
                                                        <td><?php echo $lokaal['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($lokaal['Locatie']); ?></td>
                                                        <td><?php echo $aantalOpeningsuren; ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary edit-lokaal" 
                                                                    data-bs-toggle="modal" data-bs-target="#editLokaalModal"
                                                                    data-id="<?php echo $lokaal['id']; ?>"
                                                                    data-locatie="<?php echo htmlspecialchars($lokaal['Locatie']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Weet je zeker dat je dit lokaal wilt verwijderen?');">
                                                                <input type="hidden" name="action" value="delete_lokaal">
                                                                <input type="hidden" name="id" value="<?php echo $lokaal['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" <?php echo $aantalOpeningsuren > 0 ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Geen lokalen gevonden</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Edit Openingsuren Modal -->
    <div class="modal fade" id="editOpeningsurenModal" tabindex="-1" aria-labelledby="editOpeningsurenModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editOpeningsurenModalLabel">Openingsuren bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editOpeningsurenForm" method="POST" action="">
                        <input type="hidden" name="action" value="edit_openingsuren">
                        <input type="hidden" id="edit_openingsuren_id" name="id">
                        
                        <div class="mb-3">
                            <label for="edit_lokaal_id" class="form-label">Lokaal</label>
                            <select class="form-select" id="edit_lokaal_id" name="lokaal_id" required>
                                <?php if(isset($lokalen) && count($lokalen) > 0): ?>
                                    <?php foreach ($lokalen as $lokaal): ?>
                                        <option value="<?php echo $lokaal['id']; ?>"><?php echo htmlspecialchars($lokaal['Locatie']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label for="edit_start_date" class="form-label">Startdatum</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="col">
                                <label for="edit_start_time" class="form-label">Starttijd</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label for="edit_eind_date" class="form-label">Einddatum</label>
                                <input type="date" class="form-control" id="edit_eind_date" name="eind_date" required>
                            </div>
                            <div class="col">
                                <label for="edit_eind_time" class="form-label">Eindtijd</label>
                                <input type="time" class="form-control" id="edit_eind_time" name="eind_time" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" form="editOpeningsurenForm" class="btn btn-primary">Wijzigingen opslaan</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Lokaal Modal -->
    <div class="modal fade" id="editLokaalModal" tabindex="-1" aria-labelledby="editLokaalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="editLokaalModalLabel">Lokaal bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editLokaalForm" method="POST" action="">
                        <input type="hidden" name="action" value="edit_lokaal">
                        <input type="hidden" id="edit_lokaal_id_field" name="id">
                        
                        <div class="mb-3">
                            <label for="edit_locatie" class="form-label">Locatie naam</label>
                            <input type="text" class="form-control" id="edit_locatie" name="locatie" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" form="editLokaalForm" class="btn btn-success">Wijzigingen opslaan</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery voor DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Custom admin JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // DataTables initialiseren
        $('#openingsurenTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/nl-NL.json'
            },
            order: [[0, 'desc']]
        });
        
        $('#lokalenTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/nl-NL.json'
            }
        });
        
        // Automatisch invullen van datum velden
        const vandaag = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').value = vandaag;
        document.getElementById('eind_date').value = vandaag;
        
        // Edit openingsuren modal vullen
        document.querySelectorAll('.edit-openingsuren').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const lokaalId = this.getAttribute('data-lokaal');
                const startDateTime = new Date(this.getAttribute('data-start'));
                const eindDateTime = new Date(this.getAttribute('data-eind'));
                
                // Datum en tijd formatteren voor de formulier velden
                const startDate = startDateTime.toISOString().split('T')[0];
                const startTime = startDateTime.toTimeString().slice(0, 5);
                const eindDate = eindDateTime.toISOString().split('T')[0];
                const eindTime = eindDateTime.toTimeString().slice(0, 5);
                
                // Velden vullen
                document.getElementById('edit_openingsuren_id').value = id;
                document.getElementById('edit_lokaal_id').value = lokaalId;
                document.getElementById('edit_start_date').value = startDate;
                document.getElementById('edit_start_time').value = startTime;
                document.getElementById('edit_eind_date').value = eindDate;
                document.getElementById('edit_eind_time').value = eindTime;
            });
        });
        
        // Edit lokaal modal vullen
        document.querySelectorAll('.edit-lokaal').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const locatie = this.getAttribute('data-locatie');
                
                document.getElementById('edit_lokaal_id_field').value = id;
                document.getElementById('edit_locatie').value = locatie;
            });
        });
    });
    </script>
</body>
</html>