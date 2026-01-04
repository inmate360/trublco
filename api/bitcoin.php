<?php
session_start();
require_once '../config/database.php';
require_once '../classes/BitcoinService.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$bitcoin = new BitcoinService($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch($action) {
        case 'get_price':
            $price = $bitcoin->getBitcoinPrice();
            echo json_encode([
                'success' => true,
                'price_usd' => $price,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'get_wallet':
            $wallet = $bitcoin->getUserWallet($user_id);
            echo json_encode([
                'success' => true,
                'wallet' => $wallet
            ]);
            break;
            
        case 'convert_usd_to_btc':
            $usd = floatval($_POST['usd'] ?? 0);
            $btc = $bitcoin->usdToBtc($usd);
            echo json_encode([
                'success' => true,
                'usd' => $usd,
                'btc' => $btc
            ]);
            break;
            
        case 'convert_btc_to_usd':
            $btc = floatval($_POST['btc'] ?? 0);
            $usd = $bitcoin->btcToUsd($btc);
            echo json_encode([
                'success' => true,
                'btc' => $btc,
                'usd' => $usd
            ]);
            break;
            
        case 'create_subscription_charge':
            $plan_type = $_POST['plan_type'] ?? '';
            $result = $bitcoin->createSubscriptionCharge($user_id, $plan_type);
            echo json_encode($result);
            break;
            
        case 'create_payment_request':
            $to_user_id = (int)$_POST['to_user_id'];
            $amount_usd = floatval($_POST['amount_usd']);
            $description = trim($_POST['description'] ?? '');
            
            $result = $bitcoin->createPaymentRequest($user_id, $to_user_id, $amount_usd, $description);
            echo json_encode($result);
            break;
            
        case 'get_payment_requests':
            $type = $_GET['type'] ?? 'received';
            $requests = $bitcoin->getUserPaymentRequests($user_id, $type);
            echo json_encode([
                'success' => true,
                'requests' => $requests
            ]);
            break;
            
        case 'get_transactions':
            $limit = (int)($_GET['limit'] ?? 50);
            $transactions = $bitcoin->getTransactionHistory($user_id, $limit);
            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch(Exception $e) {
    error_log("Bitcoin API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred'
    ]);
}
?>