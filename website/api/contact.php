<?php
session_start();
require_once 'config.php';

// Get the HTTP method and route
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : '';

// Router
switch ($method) {
    case 'POST':
        if ($route == 'send') {
            submitContactForm();
        } else {
            sendResponse(["error" => "Route not found"], 404);
        }
        break;
    case 'GET':
        if ($route == 'messages' && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
            getContactMessages();
        } else {
            sendResponse(["error" => "Route not found or access denied"], 404);
        }
        break;
    default:
        sendResponse(["error" => "Method not allowed"], 405);
}

// Submit contact form
function submitContactForm() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['subject']) || !isset($data['message'])) {
        sendResponse(["error" => "Missing required fields"], 400);
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(["error" => "Invalid email format"], 400);
    }
    
    // Store the contact message
    $stmt = $conn->prepare("
        INSERT INTO contact_messages (name, email, subject, message) 
        VALUES (?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $data['name'],
        $data['email'],
        $data['subject'],
        $data['message']
    ]);
    
    if ($success) {
        // Optional: Send email notification to admin
        // mail('admin@example.com', 'New Contact Form Submission', "Name: {$data['name']}\nEmail: {$data['email']}\nSubject: {$data['subject']}\nMessage: {$data['message']}");
        
        sendResponse(["message" => "Your message has been sent successfully. We'll get back to you soon."], 201);
    } else {
        sendResponse(["error" => "Failed to send message"], 500);
    }
}

// Get all contact messages (admin only)
function getContactMessages() {
    global $conn;
    
    $stmt = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $messages = $stmt->fetchAll();
    
    sendResponse($messages);
}
?>