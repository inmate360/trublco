<?php
session_start();
require_once '../config/database.php';
require_once '../classes/ThemeSwitcher.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$themeSwitcher = new ThemeSwitcher($db);

$action = $_POST['action'] ?? '';

if($action === 'update_theme') {
    $theme = $_POST['theme'] ?? 'dark';
    $result = $themeSwitcher->updateTheme($_SESSION['user_id'], $theme);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>