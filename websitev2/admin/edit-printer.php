<?php
session_start();
require_once '../config.php';

// Ensure user is admin
requireAdmin();

$currentPage = 'admin';
$pageTitle = 'Printer Bewerken - Admin';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Ongeldige printer ID.', 'danger');
    redirect('admin/printers.php');
}

$printerId = $_GET['id'];

// Get printer details
$stmt = $conn->prepare("SELECT * FROM printers WHERE id = ?");
$stmt->execute([$printerId]);
$printer = $stmt->fetch();

if (!$printer) {
    setFlashMessage('Printer niet gevonden.', 'danger');
    redirect('admin/printers.php');
}

$error = '';
$success = '';

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $model = sanitizeInput($_POST['model']);
    $description = sanitizeInput($_POST['description']);
    $location = sanitizeInput($_POST['location']);
    $colorCapability = isset($_POST['color_capability']) ? 1 : 0;
    $status = sanitizeInput($_POST['status']);
    
    // Validate inputs
    if (empty($name) || empty($model)) {
        $error = 'Naam en model zijn verplichte velden.';
    } else {
        // Update printer
        $stmt = $conn->prepare("
            UPDATE printers 
            SET name = ?, model = ?, description = ?, location = ?, color_capability = ?, status = ? 
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$name, $model, $description, $location, $colorCapability, $status, $printerId]);
        
        if ($result) {
            $success = 'Printer succesvol bijgewerkt.';
            
            // Refresh printer data
            $stmt = $conn->prepare("SELECT * FROM printers WHERE id = ?");
            $stmt->execute([$printerId]);
            $printer = $stmt->fetch();
        } else {
            $error = 'Er is een fout opgetreden bij het bijwerken van de printer.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Printer Bewerken</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item"><a href="printers.php">Printers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bewerken</li>
            </ol>
        </nav>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="post" action="edit-printer.php?id=<?php echo $printerId; ?>" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Naam *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($printer['name']); ?>" required>
                        <div class="invalid-feedback">
                            Naam is verplicht.
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="model" class="form-label">Model *</label>
                        <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($printer['model']); ?>" required>
                        <div class="invalid-feedback">
                            Model is verplicht.
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Beschrijving</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($printer['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="location" class="form-label">Locatie</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($printer['location'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="available" <?php echo $printer['status'] === 'available' ? 'selected' : ''; ?>>Beschikbaar</option>
                            <option value="maintenance" <?php echo $printer['status'] === 'maintenance' ? 'selected' : ''; ?>>Onderhoud</option>
                            <option value="unavailable" <?php echo $printer['status'] === 'unavailable' ? 'selected' : ''; ?>>Niet Beschikbaar</option>
                        </select>
                        <div class="invalid-feedback">
                            Kies een status.
                        </div>
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="color_capability" name="color_capability" <?php echo $printer['color_capability'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="color_capability">Ondersteunt kleurenprints</label>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="printers.php" class="btn btn-secondary">Terug naar Printers</a>
                    <button type="submit" class="btn btn-primary">Wijzigingen Opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>