<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Favorites.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$favorites = new Favorites($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch($action) {
    case 'add':
        $listing_id = (int)$_POST['listing_id'];
        $result = $favorites->add($user_id, $listing_id);
        echo json_encode($result);
        break;
        
    case 'remove':
        $listing_id = (int)$_POST['listing_id'];
        $result = $favorites->remove($user_id, $listing_id);
        echo json_encode($result);
        break;
        
    case 'toggle':
        $listing_id = (int)$_POST['listing_id'];
        if($favorites->isFavorited($user_id, $listing_id)) {
            $result = $favorites->remove($user_id, $listing_id);
        } else {
            $result = $favorites->add($user_id, $listing_id);
        }
        $result['is_favorited'] = $favorites->isFavorited($user_id, $listing_id);
        echo json_encode($result);
        break;
        
    case 'check':
        $listing_id = (int)$_GET['listing_id'];
        $is_favorited = $favorites->isFavorited($user_id, $listing_id);
        echo json_encode(['success' => true, 'is_favorited' => $is_favorited]);
        break;
        
    case 'clear_all':
        $result = $favorites->clearAll($user_id);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>