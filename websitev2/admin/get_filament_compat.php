<?php
// Admin toegang controle
require_once 'admin.php';

header('Content-Type: application/json');

if (!isset($_GET['printer_id']) || !is_numeric($_GET['printer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid printer ID']);
    exit;
}

$printer_id = intval($_GET['printer_id']);

try {
    $stmt = $conn->prepare("
        SELECT filament_id 
        FROM Filament_compatibiliteit 
        WHERE printer_id = ?
    ");
    $stmt->execute([$printer_id]);
    $filament_ids = [];
    
    while ($row = $stmt->fetch()) {
        $filament_ids[] = $row['filament_id'];
    }
    
    echo json_encode([
        'success' => true,
        'filament_ids' => $filament_ids
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>