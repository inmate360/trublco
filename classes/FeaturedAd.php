<?php
/**
 * FeaturedAd Class - Handles featured ad requests and management
 */
class FeaturedAd {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get pricing options
    public function getPricingOptions() {
        $query = "SELECT * FROM featured_pricing WHERE is_active = TRUE ORDER BY display_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Get pricing by duration
    public function getPricingByDuration($days) {
        $query = "SELECT * FROM featured_pricing WHERE duration_days = :days AND is_active = TRUE LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Create featured request
    public function createRequest($listing_id, $user_id, $duration_days, $payment_intent_id = null) {
        $pricing = $this->getPricingByDuration($duration_days);
        
        if(!$pricing) {
            return ['success' => false, 'error' => 'Invalid duration selected'];
        }

        // Check if listing exists and belongs to user
        $query = "SELECT * FROM listings WHERE id = :listing_id AND user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':listing_id', $listing_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() == 0) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        // Check if already has pending or active featured request
        $query = "SELECT * FROM featured_requests 
                  WHERE listing_id = :listing_id 
                  AND status IN ('pending', 'approved') 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':listing_id', $listing_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'This listing already has an active featured request'];
        }

        // Create request
        $query = "INSERT INTO featured_requests 
                  (listing_id, user_id, duration_days, price, stripe_payment_intent_id) 
                  VALUES (:listing_id, :user_id, :duration, :price, :payment_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':listing_id', $listing_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':duration', $duration_days);
        $stmt->bindParam(':price', $pricing['price']);
        $stmt->bindParam(':payment_id', $payment_intent_id);
        
        if($stmt->execute()) {
            return ['success' => true, 'request_id' => $this->conn->lastInsertId()];
        }
        
        return ['success' => false, 'error' => 'Failed to create request'];
    }

    // Get pending requests (for moderators)
    public function getPendingRequests() {
        $query = "SELECT fr.*, l.title, l.description, u.username, u.email
                  FROM featured_requests fr
                  LEFT JOIN listings l ON fr.listing_id = l.id
                  LEFT JOIN users u ON fr.user_id = u.id
                  WHERE fr.status = 'pending'
                  ORDER BY fr.requested_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Get user's featured requests
    public function getUserRequests($user_id) {
        $query = "SELECT fr.*, l.title, m.user_id as reviewer_user_id, u.username as reviewer_name
                  FROM featured_requests fr
                  LEFT JOIN listings l ON fr.listing_id = l.id
                  LEFT JOIN moderators m ON fr.reviewed_by = m.id
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE fr.user_id = :user_id
                  ORDER BY fr.requested_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Approve featured request (moderator)
    public function approveRequest($request_id, $moderator_id, $notes = '') {
        $request = $this->getRequestById($request_id);
        
        if(!$request || $request['status'] != 'pending') {
            return ['success' => false, 'error' => 'Invalid request'];
        }

        $starts_at = date('Y-m-d H:i:s');
        $ends_at = date('Y-m-d H:i:s', strtotime("+{$request['duration_days']} days"));

        // Update request status
        $query = "UPDATE featured_requests 
                  SET status = 'approved', 
                      reviewed_by = :mod_id, 
                      reviewed_at = NOW(), 
                      review_notes = :notes,
                      starts_at = :starts_at,
                      ends_at = :ends_at
                  WHERE id = :request_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':mod_id', $moderator_id);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':starts_at', $starts_at);
        $stmt->bindParam(':ends_at', $ends_at);
        $stmt->bindParam(':request_id', $request_id);
        
        if($stmt->execute()) {
            // Create featured listing entry
            $this->activateFeaturedListing($request, $starts_at, $ends_at);
            
            // Create notification
            $this->createNotification($request['user_id'], 'featured_approved', 
                'Featured Ad Approved', 
                'Your featured ad request has been approved and is now active!',
                '/my-listings.php');
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to approve request'];
    }

    // Reject featured request (moderator)
    public function rejectRequest($request_id, $moderator_id, $reason) {
        $request = $this->getRequestById($request_id);
        
        if(!$request || $request['status'] != 'pending') {
            return ['success' => false, 'error' => 'Invalid request'];
        }

        $query = "UPDATE featured_requests 
                  SET status = 'rejected', 
                      reviewed_by = :mod_id, 
                      reviewed_at = NOW(), 
                      review_notes = :reason
                  WHERE id = :request_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':mod_id', $moderator_id);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':request_id', $request_id);
        
        if($stmt->execute()) {
            // Refund payment if applicable (implement refund logic)
            
            // Create notification
            $this->createNotification($request['user_id'], 'featured_rejected', 
                'Featured Ad Rejected', 
                'Your featured ad request was not approved. Reason: ' . $reason,
                '/my-listings.php');
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Failed to reject request'];
    }

    // Activate featured listing
    private function activateFeaturedListing($request, $starts_at, $ends_at) {
        $query = "INSERT INTO featured_listings 
                  (listing_id, user_id, request_id, featured_from, featured_until) 
                  VALUES (:listing_id, :user_id, :request_id, :starts_at, :ends_at)
                  ON DUPLICATE KEY UPDATE 
                  featured_from = :starts_at2, 
                  featured_until = :ends_at2,
                  is_active = TRUE";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':listing_id', $request['listing_id']);
        $stmt->bindParam(':user_id', $request['user_id']);
        $stmt->bindParam(':request_id', $request['id']);
        $stmt->bindParam(':starts_at', $starts_at);
        $stmt->bindParam(':ends_at', $ends_at);
        $stmt->bindParam(':starts_at2', $starts_at);
        $stmt->bindParam(':ends_at2', $ends_at);
        
        return $stmt->execute();
    }

    // Get active featured listings
    public function getActiveFeaturedListings($category_id = null, $limit = 10) {
        $query = "SELECT l.*, fl.featured_until, c.name as category_name, c.slug as category_slug,
                         COALESCE(img.file_path, '') as primary_image
                  FROM featured_listings fl
                  INNER JOIN listings l ON fl.listing_id = l.id
                  LEFT JOIN categories c ON l.category_id = c.id
                  LEFT JOIN listing_images img ON l.id = img.listing_id AND img.is_primary = TRUE
                  WHERE fl.is_active = TRUE 
                  AND fl.featured_until > NOW()
                  AND l.status = 'active'";
        
        if($category_id) {
            $query .= " AND l.category_id = :category_id";
        }
        
        $query .= " ORDER BY fl.created_at DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        
        if($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            // Return empty array if table doesn't exist or other error
            error_log("Featured listings error: " . $e->getMessage());
            return [];
        }
    }

    // Track impression
    public function trackImpression($listing_id) {
        try {
            $query = "UPDATE featured_listings 
                      SET impressions = impressions + 1 
                      WHERE listing_id = :listing_id AND is_active = TRUE";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->execute();
        } catch(PDOException $e) {
            // Silently fail
            error_log("Track impression error: " . $e->getMessage());
        }
    }

    // Track click
    public function trackClick($listing_id) {
        try {
            $query = "UPDATE featured_listings 
                      SET clicks = clicks + 1 
                      WHERE listing_id = :listing_id AND is_active = TRUE";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->execute();
        } catch(PDOException $e) {
            // Silently fail
            error_log("Track click error: " . $e->getMessage());
        }
    }

    // Get request by ID
    public function getRequestById($id) {
        $query = "SELECT * FROM featured_requests WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Create notification
    private function createNotification($user_id, $type, $title, $message, $link = null) {
        try {
            $query = "INSERT INTO notifications (user_id, type, title, message, link) 
                      VALUES (:user_id, :type, :title, :message, :link)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':link', $link);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Create notification error: " . $e->getMessage());
        }
    }

    // Expire old featured listings (cron job)
    public function expireOldListings() {
        try {
            $query = "UPDATE featured_listings 
                      SET is_active = FALSE 
                      WHERE featured_until < NOW() AND is_active = TRUE";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            // Update request status
            $query = "UPDATE featured_requests 
                      SET status = 'expired' 
                      WHERE ends_at < NOW() AND status = 'approved'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Expire listings error: " . $e->getMessage());
        }
    }
}
?>