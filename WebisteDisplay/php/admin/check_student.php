<?php
// Include database connection
require_once '../db_connection.php';

// Get the student ID from query parameter
$studentId = $_GET['studentId'] ?? '';

// Validate input
if (empty($studentId)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    // Check if the student exists in the database
    $stmt = $conn->prepare("
        SELECT v.*, u.User_ID, u.Voornaam, u.Naam, u.Emailadres, o.naam as opleiding_naam
        FROM vives v 
        JOIN user u ON v.User_ID = u.User_ID 
        LEFT JOIN opleidingen o ON v.opleiding_id = o.id
        WHERE v.Vives_id = ?
    ");
    $stmt->execute([$studentId]);
    
    if ($student = $stmt->fetch()) {
        // Student exists, return student info
        echo json_encode([
            'exists' => true,
            'student' => $student
        ]);
    } else {
        // Student not found
        echo json_encode(['exists' => false]);
    }
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
?>