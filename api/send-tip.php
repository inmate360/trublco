<?php
session_start();
require_once '../config/database.php';
require_once '../classes/CoinsSystem.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$to_user_id = (int)($data['to_user_id'] ?? 0);
$amount = floatval($data['amount'] ?? 0);
$message = $data['message'] ?? '';
$content_id = isset($data['content_id']) ? (int)$data['content_id'] : null;

if($amount < 10) {
    echo json_encode(['success' => false, 'error' => 'Minimum tip is 10 coins']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$coinsSystem = new CoinsSystem($db);

try {
    $db->beginTransaction();
    
    // Transfer coins
    $transfer = $coinsSystem->transferCoins(
        $_SESSION['user_id'],
        $to_user_id,
        $amount,
        'tip',
        'Tip' . ($message ? ': ' . $message : '')
    );
    
    if(!$transfer['success']) {
        throw new Exception($transfer['error']);
    }
    
    // Record tip
    $query = "INSERT INTO tips (from_user_id, to_user_id, amount, message, content_id) 
              VALUES (:from_user, :to_user, :amount, :message, :content_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':from_user', $_SESSION['user_id']);
    $stmt->bindParam(':to_user', $to_user_id);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':content_id', $content_id);
    $stmt->execute();
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Tip sent successfully!']);
    
} catch(Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}