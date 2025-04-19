<?php
session_start();
require_once '../config.php';

// Ensure user is admin
requireAdmin();

$currentPage = 'admin';
$pageTitle = 'Printer Toevoegen - Admin';

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
        // Insert printer
        $stmt = $conn->prepare("
            INSERT INTO printers (name, model, description, location, color_capability, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([$name, $model, $description, $location, $colorCapability, $status]);
        
        if ($result) {
            setFlashMessage('Printer succesvol toegevoegd.', 'success');
            redirect('admin/printers.php');
        } else {
            $error = 'Er is een fout opgetreden bij het toevoegen van de printer.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Printer Toevoegen</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item"><a href="printers.php">Printers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Toevoegen</li>
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
            <form method="post" action="add-printer.php" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Naam *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">
                            Naam is verplicht.
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="model" class="form-label">Model *</label>
                        <input type="text" class="form-control" id="model" name="model" required>
                        <div class="invalid-feedback">
                            Model is verplicht.
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Beschrijving</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="location" class="form-label">Locatie</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="available">Beschikbaar</option>
                            <option value="maintenance">Onderhoud</option>
                            <option value="unavailable">Niet Beschikbaar</option>
                        </select>
                        <div class="invalid-feedback">
                            Kies een status.
                        </div>
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="color_capability" name="color_capability">
                    <label class="form-check-label" for="color_capability">Ondersteunt kleurenprints</label>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="printers.php" class="btn btn-secondary">Annuleren</a>
                    <button type="submit" class="btn btn-primary">Printer Toevoegen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>