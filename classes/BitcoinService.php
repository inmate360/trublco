<?php
require_once __DIR__ . '/../config/bitcoin.php';

class BitcoinService {
    private $db;
    private $config;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = require __DIR__ . '/../config/bitcoin.php';
    }
    
    /**
     * Get current Bitcoin price in USD
     */
    public function getBitcoinPrice() {
        // Check cache first
        $query = "SELECT price_usd, updated_at FROM bitcoin_price_cache 
                  ORDER BY updated_at DESC LIMIT 1";
        $stmt = $this->db->query($query);
        $cached = $stmt->fetch();
        
        if($cached && (time() - strtotime($cached['updated_at'])) < $this->config['price_cache_duration']) {
            return floatval($cached['price_usd']);
        }
        
        // Fetch from Coinbase API
        try {
            $ch = curl_init('https://api.coinbase.com/v2/exchange-rates?currency=BTC');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if(isset($data['data']['rates']['USD'])) {
                $price = floatval($data['data']['rates']['USD']);
                
                // Update cache
                $query = "INSERT INTO bitcoin_price_cache (price_usd, source) VALUES (:price, 'coinbase')";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['price' => $price]);
                
                return $price;
            }
        } catch(Exception $e) {
            error_log("Bitcoin price fetch error: " . $e->getMessage());
        }
        
        // Fallback to cached price
        return $cached ? floatval($cached['price_usd']) : 50000;
    }
    
    /**
     * Convert USD to BTC
     */
    public function usdToBtc($usd_amount) {
        $btc_price = $this->getBitcoinPrice();
        return round($usd_amount / $btc_price, 8);
    }
    
    /**
     * Convert BTC to USD
     */
    public function btcToUsd($btc_amount) {
        $btc_price = $this->getBitcoinPrice();
        return round($btc_amount * $btc_price, 2);
    }
    
    /**
     * Get or create user wallet
     */
    public function getUserWallet($user_id) {
        $query = "SELECT * FROM bitcoin_wallets WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        $wallet = $stmt->fetch();
        
        if(!$wallet) {
            // Create wallet
            $query = "INSERT INTO bitcoin_wallets (user_id, wallet_label) VALUES (:user_id, 'Main Wallet')";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id]);
            
            return $this->getUserWallet($user_id);
        }
        
        return $wallet;
    }
    
    /**
     * Create Coinbase Commerce Charge for Premium Subscription
     */
    public function createSubscriptionCharge($user_id, $plan_type) {
        if(!isset($this->config['plans'][$plan_type])) {
            return ['success' => false, 'error' => 'Invalid plan type'];
        }
        
        $plan = $this->config['plans'][$plan_type];
        $amount_usd = $plan['price_usd'];
        $amount_btc = $this->usdToBtc($amount_usd);
        
        try {
            $ch = curl_init($this->config['coinbase']['api_url'] . '/charges');
            
            $data = [
                'name' => 'Turnpage ' . $plan['name'] . ' Subscription',
                'description' => $plan['name'] . ' membership for ' . $plan['duration_days'] . ' days',
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $amount_usd,
                    'currency' => 'USD'
                ],
                'metadata' => [
                    'user_id' => $user_id,
                    'plan_type' => $plan_type,
                    'platform' => 'turnpage'
                ],
                'redirect_url' => 'https://turnpage.io/subscription-success.php',
                'cancel_url' => 'https://turnpage.io/subscription-cancelled.php'
            ];
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-CC-Api-Key: ' . $this->config['coinbase']['api_key'],
                'X-CC-Version: 2018-03-22'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if($http_code === 201 && isset($result['data'])) {
                $charge = $result['data'];
                
                // Save to database
                $query = "INSERT INTO coinbase_charges 
                          (user_id, charge_id, hosted_url, amount_btc, amount_usd, description, status, expires_at, metadata) 
                          VALUES (:user_id, :charge_id, :hosted_url, :amount_btc, :amount_usd, :description, 'pending', :expires_at, :metadata)";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'user_id' => $user_id,
                    'charge_id' => $charge['code'],
                    'hosted_url' => $charge['hosted_url'],
                    'amount_btc' => $amount_btc,
                    'amount_usd' => $amount_usd,
                    'description' => $plan['name'] . ' Subscription',
                    'expires_at' => $charge['expires_at'],
                    'metadata' => json_encode(['plan_type' => $plan_type])
                ]);
                
                // Create subscription record
                $subscription_id = $this->createSubscription($user_id, $plan_type, $amount_btc, $amount_usd);
                
                return [
                    'success' => true,
                    'charge_id' => $charge['code'],
                    'hosted_url' => $charge['hosted_url'],
                    'amount_btc' => $amount_btc,
                    'amount_usd' => $amount_usd,
                    'subscription_id' => $subscription_id
                ];
            }
            
            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Failed to create charge'
            ];
            
        } catch(Exception $e) {
            error_log("Coinbase charge error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment system error'];
        }
    }
    
    /**
     * Create subscription record
     */
    private function createSubscription($user_id, $plan_type, $amount_btc, $amount_usd) {
        $plan = $this->config['plans'][$plan_type];
        
        $query = "INSERT INTO premium_subscriptions 
                  (user_id, plan_type, payment_method, amount_btc, amount_usd, status) 
                  VALUES (:user_id, :plan_type, 'coinbase', :amount_btc, :amount_usd, 'pending')";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'user_id' => $user_id,
            'plan_type' => $plan_type,
            'amount_btc' => $amount_btc,
            'amount_usd' => $amount_usd
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Activate subscription after payment
     */
    public function activateSubscription($charge_id) {
        // Get charge info
        $query = "SELECT * FROM coinbase_charges WHERE charge_id = :charge_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['charge_id' => $charge_id]);
        $charge = $stmt->fetch();
        
        if(!$charge) {
            return ['success' => false, 'error' => 'Charge not found'];
        }
        
        $metadata = json_decode($charge['metadata'], true);
        $plan_type = $metadata['plan_type'];
        $plan = $this->config['plans'][$plan_type];
        
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));
        
        try {
            $this->db->beginTransaction();
            
            // Update subscription
            $query = "UPDATE premium_subscriptions 
                      SET status = 'active', start_date = :start_date, end_date = :end_date 
                      WHERE user_id = :user_id AND plan_type = :plan_type AND status = 'pending'";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'start_date' => $start_date,
                'end_date' => $end_date,
                'user_id' => $charge['user_id'],
                'plan_type' => $plan_type
            ]);
            
            // Update user premium status
            $query = "UPDATE users SET is_premium = 1, premium_expires = :end_date WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'end_date' => $end_date,
                'user_id' => $charge['user_id']
            ]);
            
            // Update charge status
            $query = "UPDATE coinbase_charges SET status = 'confirmed', confirmed_at = NOW() WHERE charge_id = :charge_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['charge_id' => $charge_id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Subscription activated'];
            
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("Subscription activation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to activate subscription'];
        }
    }
    
    /**
     * Create payment request (user to user)
     */
    public function createPaymentRequest($from_user_id, $to_user_id, $amount_usd, $description) {
        $amount_btc = $this->usdToBtc($amount_usd);
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        try {
            $query = "INSERT INTO payment_requests 
                      (from_user_id, to_user_id, amount_btc, amount_usd, description, expires_at) 
                      VALUES (:from_user_id, :to_user_id, :amount_btc, :amount_usd, :description, :expires_at)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'from_user_id' => $from_user_id,
                'to_user_id' => $to_user_id,
                'amount_btc' => $amount_btc,
                'amount_usd' => $amount_usd,
                'description' => $description,
                'expires_at' => $expires_at
            ]);
            
            return [
                'success' => true,
                'request_id' => $this->db->lastInsertId(),
                'amount_btc' => $amount_btc,
                'amount_usd' => $amount_usd
            ];
            
        } catch(Exception $e) {
            error_log("Payment request error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create payment request'];
        }
    }
    
    /**
     * Get user's payment requests
     */
    public function getUserPaymentRequests($user_id, $type = 'received') {
        $field = $type === 'received' ? 'to_user_id' : 'from_user_id';
        
        $query = "SELECT pr.*, 
                  sender.username as from_username,
                  receiver.username as to_username
                  FROM payment_requests pr
                  LEFT JOIN users sender ON pr.from_user_id = sender.id
                  LEFT JOIN users receiver ON pr.to_user_id = receiver.id
                  WHERE pr.$field = :user_id 
                  AND pr.status IN ('pending', 'paid')
                  ORDER BY pr.created_at DESC
                  LIMIT 50";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($user_id, $limit = 50) {
        $query = "SELECT bt.*,
                  sender.username as from_username,
                  receiver.username as to_username
                  FROM bitcoin_transactions bt
                  LEFT JOIN users sender ON bt.from_user_id = sender.id
                  LEFT JOIN users receiver ON bt.to_user_id = receiver.id
                  WHERE bt.from_user_id = :user_id OR bt.to_user_id = :user_id
                  ORDER BY bt.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Transfer BTC between users
     */
    public function transferBtc($from_user_id, $to_username, $amount_btc) {
        try {
            $this->db->beginTransaction();

            // Get sender wallet
            $sender_wallet = $this->getUserWallet($from_user_id);
            if ($sender_wallet['btc_balance'] < $amount_btc) {
                throw new Exception("Insufficient balance");
            }

            // Get receiver
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $to_username]);
            $receiver = $stmt->fetch();
            if (!$receiver) {
                throw new Exception("Receiver not found");
            }
            $to_user_id = $receiver['id'];

            if ($from_user_id == $to_user_id) {
                throw new Exception("Cannot send to yourself");
            }

            // Update balances
            $stmt = $this->db->prepare("UPDATE bitcoin_wallets SET btc_balance = btc_balance - :amount, total_sent = total_sent + :amount WHERE user_id = :user_id");
            $stmt->execute(['amount' => $amount_btc, 'user_id' => $from_user_id]);

            $stmt = $this->db->prepare("UPDATE bitcoin_wallets SET btc_balance = btc_balance + :amount, total_received = total_received + :amount WHERE user_id = :user_id");
            $stmt->execute(['amount' => $amount_btc, 'user_id' => $to_user_id]);

            // Record transaction
            $stmt = $this->db->prepare("INSERT INTO bitcoin_transactions (from_user_id, to_user_id, amount, type, status) VALUES (:from, :to, :amount, 'transfer', 'confirmed')");
            $stmt->execute(['from' => $from_user_id, 'to' => $to_user_id, 'amount' => $amount_btc]);

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deposit BTC (Simulated for this environment)
     */
    public function depositBtc($user_id, $amount_btc) {
        try {
            $stmt = $this->db->prepare("UPDATE bitcoin_wallets SET btc_balance = btc_balance + :amount, total_received = total_received + :amount WHERE user_id = :user_id");
            $stmt->execute(['amount' => $amount_btc, 'user_id' => $user_id]);

            $stmt = $this->db->prepare("INSERT INTO bitcoin_transactions (to_user_id, amount, type, status) VALUES (:to, :amount, 'deposit', 'confirmed')");
            $stmt->execute(['to' => $user_id, 'amount' => $amount_btc]);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Buy Premium with BTC
     */
    public function buyPremiumWithBtc($user_id, $plan_type) {
        if (!isset($this->config['plans'][$plan_type])) {
            return ['success' => false, 'error' => 'Invalid plan'];
        }

        $plan = $this->config['plans'][$plan_type];
        $amount_btc = $this->usdToBtc($plan['price_usd']);

        try {
            $this->db->beginTransaction();

            $wallet = $this->getUserWallet($user_id);
            if ($wallet['btc_balance'] < $amount_btc) {
                throw new Exception("Insufficient balance. You need " . number_format($amount_btc, 8) . " BTC");
            }

            // Deduct balance
            $stmt = $this->db->prepare("UPDATE bitcoin_wallets SET btc_balance = btc_balance - :amount, total_sent = total_sent + :amount WHERE user_id = :user_id");
            $stmt->execute(['amount' => $amount_btc, 'user_id' => $user_id]);

            // Record transaction
            $stmt = $this->db->prepare("INSERT INTO bitcoin_transactions (from_user_id, amount, type, status, description) VALUES (:from, :amount, 'premium_purchase', 'confirmed', :desc)");
            $stmt->execute(['from' => $user_id, 'amount' => $amount_btc, 'desc' => "Premium Plan: " . $plan['name']]);

            // Activate Premium
            $end_date = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));
            $stmt = $this->db->prepare("UPDATE users SET is_premium = 1, premium_expires = :expires WHERE id = :user_id");
            $stmt->execute(['expires' => $end_date, 'user_id' => $user_id]);

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>