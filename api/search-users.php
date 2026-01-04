<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if(strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Query too short']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$sql = "SELECT id, username, last_seen 
        FROM users 
        WHERE username LIKE :query 
        AND id != :current_user
        AND is_banned = 0
        ORDER BY username ASC 
        LIMIT 10";

$stmt = $db->prepare($sql);
$searchTerm = $query . '%';
$stmt->bindParam(':query', $searchTerm);
$stmt->bindParam(':current_user', $_SESSION['user_id']);
$stmt->execute();

$users = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'users' => $users
]);
