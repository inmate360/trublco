<?php
session_start();
require_once '../config/database.php';
require_once '../classes/PrivateMessaging.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $pm = new PrivateMessaging($db);

    $unread_count = $pm->getUnreadCount($_SESSION['user_id']);

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);

} catch(Exception $e) {
    error_log("Error in get-unread-count.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
