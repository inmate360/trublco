<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$message_id = (int)($data['message_id'] ?? 0);

if(!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Update the log to mark as dismissed
$query = "UPDATE romance_scam_logs 
          SET user_dismissed = TRUE 
          WHERE message_id = :message_id 
          AND recipient_id = :recipient_id";

$stmt = $db->prepare($query);
$stmt->execute([
    ':message_id' => $message_id,
    ':recipient_id' => $_SESSION['user_id']
]);

echo json_encode(['success' => true]);
?>
