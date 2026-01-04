<?php
session_start();
require_once '../config/database.php';
require_once '../classes/PrivateMessaging.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if(strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Query too short']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pm = new PrivateMessaging($db);

    $users = $pm->searchUsers($query, 10);

    // Filter out current user
    $users = array_filter($users, function($user) {
        return $user['id'] != $_SESSION['user_id'];
    });

    echo json_encode([
        'success' => true,
        'users' => array_values($users)
    ]);

} catch(PDOException $e) {
    error_log("Search users error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
