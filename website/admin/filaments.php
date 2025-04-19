<?php
// Admin toegang controle
require_once 'admin.php';

$pageTitle = 'Filament Beheer - 3D Printer Reserveringssysteem';
$currentPage = 'admin-filaments';

// Verwerken van formulier inzendingen
$success = '';
$error = '';

// Filament toevoegen
if (isset($_POST['add_filament'])) {
    $type = $_POST['type'] ?? '';
    $kleur = $_POST['kleur'] ?? '';
    $voorraad = isset($_POST['voorraad']) ? (float)$_POST['voorraad'] : 0;
    
    if (empty($type) || empty($kleur)) {
        $error = 'Type en kleur zijn verplichte velden.';
    } else {
        try {
            // Genereer een nieuw ID (auto increment simuleren)
            $stmtMaxId = $conn->query("SELECT MAX(id) as maxId FROM Filament");
            $result = $stmtMaxId->fetch();
            $newFilamentId = ($result['maxId'] ?? 0) + 1;
            
            $stmt = $conn->prepare("
                INSERT INTO Filament (id, Type, Kleur, voorraad) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$newFilamentId, $type, $kleur, $voorraad]);
            
            $success = 'Filament succesvol toegevoegd.';
        } catch (PDOException $e) {
            $error = 'Fout bij het toevoegen van filament: ' . $e->getMessage();
        }
    }
}

// Filament bewerken
if (isset($_POST['edit_filament'])) {
    $id = (int)$_POST['id'];
    $type = $_POST['type'] ?? '';
    $kleur = $_POST['kleur'] ?? '';
    $voorraad = isset($_POST['voorraad']) ? (float)$_POST['voorraad'] : 0;
    
    if (empty($type) || empty($kleur)) {
        $error = 'Type en kleur zijn verplichte velden.';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE Filament 
                SET Type = ?, Kleur = ?, voorraad = ? 
                WHERE id = ?
            ");
            $stmt->execute([$type, $kleur, $voorraad, $id]);
            
            $success = 'Filament succesvol bijgewerkt.';
        } catch (PDOException $e) {
            $error = 'Fout bij het bijwerken van filament: ' . $e->getMessage();
        }
    }
}

// Filament verwijderen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Controleer eerst of het filament in gebruik is bij reserveringen
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Reservatie WHERE filament_id = ?");
        $stmt->execute([$id]);
        $inUse = $stmt->fetch()['count'] > 0;
        
        if ($inUse) {
            $error = 'Dit filament kan niet worden verwijderd omdat het in gebruik is bij één of meer reserveringen.';
        } else {
            // Verwijder eerst uit compatibiliteit tabel
            $stmt = $conn->prepare("DELETE FROM Filament_compatibiliteit WHERE filament_id = ?");
            $stmt->execute([$id]);
            
            // Dan verwijder het filament zelf
            $stmt = $conn->prepare("DELETE FROM Filament WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = 'Filament succesvol verwijderd.';
        }
    } catch (PDOException $e) {
        $error = 'Fout bij het verwijderen van filament: ' . $e->getMessage();
    }
}

// Filament ophalen om te bewerken
$editFilament = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM Filament WHERE id = ?");
        $stmt->execute([$id]);
        $editFilament = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Fout bij het ophalen van filament: ' . $e->getMessage();
    }
}

// Alle filamenten ophalen
try {
    $stmt = $conn->query("SELECT * FROM Filament ORDER BY Type, Kleur");
    $filamenten = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Fout bij het ophalen van filamenten: ' . $e->getMessage();
    $filamenten = [];
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
                            <a class="nav-link active" href="filaments.php">
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
                    <h1 class="h2">Filament Beheer</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFilamentModal">
                        <i class="fas fa-plus"></i> Filament Toevoegen
                    </button>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filament list -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-layer-group me-1"></i>
                        Beschikbare Filamenten
                    </div>
                    <div class="card-body">
                        <?php if (empty($filamenten)): ?>
                            <div class="alert alert-info">Geen filamenten gevonden.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Type</th>
                                            <th>Kleur</th>
                                            <th>Voorraad (g)</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filamenten as $filament): ?>
                                            <tr>
                                                <td><?php echo $filament['id']; ?></td>
                                                <td><?php echo htmlspecialchars($filament['Type']); ?></td>
                                                <td>
                                                    <span class="color-sample" style="display:inline-block; width:20px; height:20px; background-color:<?php echo strtolower($filament['Kleur']); ?>; border:1px solid #ddd; vertical-align:middle; margin-right:5px;"></span>
                                                    <?php echo htmlspecialchars($filament['Kleur']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $voorraad = $filament['voorraad'] ?? 0;
                                                    echo $voorraad . ' g';
                                                    
                                                    // Toon waarschuwing als voorraad laag is
                                                    if ($voorraad < 100) {
                                                        echo ' <span class="badge bg-danger">Laag</span>';
                                                    } elseif ($voorraad < 500) {
                                                        echo ' <span class="badge bg-warning text-dark">Medium</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="filaments.php?edit=<?php echo $filament['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="filaments.php?delete=<?php echo $filament['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Weet je zeker dat je dit filament wilt verwijderen?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Edit form if needed -->
                <?php if ($editFilament): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-edit me-1"></i>
                            Filament Bewerken
                        </div>
                        <div class="card-body">
                            <form method="post" action="filaments.php">
                                <input type="hidden" name="id" value="<?php echo $editFilament['id']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="edit-type" class="form-label">Type</label>
                                        <select class="form-select" id="edit-type" name="type" required>
                                            <option value="PLA" <?php echo $editFilament['Type'] == 'PLA' ? 'selected' : ''; ?>>PLA</option>
                                            <option value="ABS" <?php echo $editFilament['Type'] == 'ABS' ? 'selected' : ''; ?>>ABS</option>
                                            <option value="PETG" <?php echo $editFilament['Type'] == 'PETG' ? 'selected' : ''; ?>>PETG</option>
                                            <option value="TPU" <?php echo $editFilament['Type'] == 'TPU' ? 'selected' : ''; ?>>TPU</option>
                                            <option value="Nylon" <?php echo $editFilament['Type'] == 'Nylon' ? 'selected' : ''; ?>>Nylon</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit-kleur" class="form-label">Kleur</label>
                                        <select class="form-select" id="edit-kleur" name="kleur" required>
                                            <option value="rood" <?php echo $editFilament['Kleur'] == 'rood' ? 'selected' : ''; ?>>Rood</option>
                                            <option value="blauw" <?php echo $editFilament['Kleur'] == 'blauw' ? 'selected' : ''; ?>>Blauw</option>
                                            <option value="groen" <?php echo $editFilament['Kleur'] == 'groen' ? 'selected' : ''; ?>>Groen</option>
                                            <option value="zwart" <?php echo $editFilament['Kleur'] == 'zwart' ? 'selected' : ''; ?>>Zwart</option>
                                            <option value="wit" <?php echo $editFilament['Kleur'] == 'wit' ? 'selected' : ''; ?>>Wit</option>
                                            <option value="geel" <?php echo $editFilament['Kleur'] == 'geel' ? 'selected' : ''; ?>>Geel</option>
                                            <option value="transparant" <?php echo $editFilament['Kleur'] == 'transparant' ? 'selected' : ''; ?>>Transparant</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="edit-voorraad" class="form-label">Voorraad (gram)</label>
                                    <input type="number" class="form-control" id="edit-voorraad" name="voorraad" value="<?php echo $editFilament['voorraad'] ?? 0; ?>" min="0" step="0.01">
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="filaments.php" class="btn btn-secondary">Annuleren</a>
                                    <button type="submit" name="edit_filament" class="btn btn-primary">Opslaan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Add Filament Modal -->
    <div class="modal fade" id="addFilamentModal" tabindex="-1" aria-labelledby="addFilamentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFilamentModalLabel">Nieuw Filament Toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="filaments.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Selecteer type</option>
                                <option value="PLA">PLA</option>
                                <option value="ABS">ABS</option>
                                <option value="PETG">PETG</option>
                                <option value="TPU">TPU</option>
                                <option value="Nylon">Nylon</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="kleur" class="form-label">Kleur</label>
                            <select class="form-select" id="kleur" name="kleur" required>
                                <option value="">Selecteer kleur</option>
                                <option value="rood">Rood</option>
                                <option value="blauw">Blauw</option>
                                <option value="groen">Groen</option>
                                <option value="zwart">Zwart</option>
                                <option value="wit">Wit</option>
                                <option value="geel">Geel</option>
                                <option value="transparant">Transparant</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="voorraad" class="form-label">Voorraad (gram)</label>
                            <input type="number" class="form-control" id="voorraad" name="voorraad" value="1000" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" name="add_filament" class="btn btn-primary">Toevoegen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>