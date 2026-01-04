<?php
/**
 * Subscription Class - Handles user subscriptions
 */
class Subscription {
    private $conn;
    private $table = 'user_subscriptions';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new subscription
    public function create($user_id, $plan_id, $stripe_data = []) {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, plan_id, stripe_customer_id, stripe_subscription_id, 
                   status, current_period_start, current_period_end) 
                  VALUES (:user_id, :plan_id, :stripe_customer_id, :stripe_subscription_id, 
                          :status, :period_start, :period_end)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':plan_id', $plan_id);
        $stmt->bindParam(':stripe_customer_id', $stripe_data['customer_id']);
        $stmt->bindParam(':stripe_subscription_id', $stripe_data['subscription_id']);
        $stmt->bindParam(':status', $stripe_data['status']);
        $stmt->bindParam(':period_start', $stripe_data['period_start']);
        $stmt->bindParam(':period_end', $stripe_data['period_end']);
        
        if($stmt->execute()) {
            // Update user's current plan
            $this->updateUserPlan($user_id, $plan_id);
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    // Get user's active subscription
    public function getUserSubscription($user_id) {
        $query = "SELECT s.*, p.name as plan_name, p.price, p.billing_cycle, 
                         p.max_active_listings, p.badge_color, p.badge_text
                  FROM " . $this->table . " s
                  LEFT JOIN membership_plans p ON s.plan_id = p.id
                  WHERE s.user_id = :user_id 
                  AND s.status IN ('active', 'trial')
                  ORDER BY s.created_at DESC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Update subscription status
    public function updateStatus($subscription_id, $status, $period_end = null) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status";
        
        if($period_end) {
            $query .= ", current_period_end = :period_end";
        }
        
        $query .= " WHERE stripe_subscription_id = :subscription_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':subscription_id', $subscription_id);
        
        if($period_end) {
            $stmt->bindParam(':period_end', $period_end);
        }
        
        return $stmt->execute();
    }

    // Cancel subscription
    public function cancel($user_id, $immediately = false) {
        $query = "UPDATE " . $this->table . " 
                  SET cancel_at_period_end = TRUE, 
                      status = :status
                  WHERE user_id = :user_id 
                  AND status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $status = $immediately ? 'canceled' : 'active';
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }

    // Check if user can perform action based on subscription
    public function canUserPost($user_id) {
        $subscription = $this->getUserSubscription($user_id);
        
        if(!$subscription) {
            return ['can_post' => false, 'reason' => 'No active subscription'];
        }

        // Count active listings
        $query = "SELECT COUNT(*) as count FROM listings 
                  WHERE user_id = :user_id AND status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $max_listings = $subscription['max_active_listings'];
        $current_count = $result['count'];
        
        if($current_count >= $max_listings) {
            return [
                'can_post' => false, 
                'reason' => "You've reached your limit of {$max_listings} active listings. Upgrade your plan for more."
            ];
        }
        
        return ['can_post' => true, 'remaining' => $max_listings - $current_count];
    }

    // Update user's current plan
    private function updateUserPlan($user_id, $plan_id) {
        $query = "UPDATE users 
                  SET current_plan_id = :plan_id,
                      is_premium = :is_premium
                  WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $is_premium = ($plan_id > 1) ? 1 : 0; // Plans with ID > 1 are premium
        $stmt->bindParam(':plan_id', $plan_id);
        $stmt->bindParam(':is_premium', $is_premium);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    // Get subscription statistics
    public function getUserStats($user_id) {
        $subscription = $this->getUserSubscription($user_id);
        
        // Get listing count
        $query = "SELECT COUNT(*) as total_listings,
                         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_listings,
                         SUM(views) as total_views
                  FROM listings 
                  WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        return [
            'subscription' => $subscription,
            'stats' => $stats
        ];
    }
}
?>