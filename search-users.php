<?php
/**
 * AJAX Endpoint - Search Users for Messaging
 * Returns list of users matching search query
 */
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get search query
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if(empty($search) || strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Search for users by username (exclude current user)
    $query = "SELECT id, username, 
              CASE WHEN creator = 1 THEN 1 ELSE 0 END as is_creator
              FROM users 
              WHERE id != :current_user 
              AND username LIKE :search 
              ORDER BY username ASC 
              LIMIT 20";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':current_user', $_SESSION['user_id']);
    $search_param = '%' . $search . '%';
    $stmt->bindParam(':search', $search_param);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);
} catch(Exception $e) {
    error_log('Error searching users: ' . $e->getMessage());
    echo json_encode([]);
}
?>
