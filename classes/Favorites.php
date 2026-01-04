<?php
class Favorites {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Add listing to favorites
     */
    public function add($user_id, $listing_id) {
        // Check if already favorited
        if($this->isFavorited($user_id, $listing_id)) {
            return ['success' => false, 'error' => 'Already in favorites'];
        }
        
        $query = "INSERT INTO favorites (user_id, listing_id, created_at) 
                  VALUES (:user_id, :listing_id, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':listing_id', $listing_id);
            
            if($stmt->execute()) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to add favorite'];
        } catch(PDOException $e) {
            error_log("Error adding favorite: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Remove from favorites
     */
    public function remove($user_id, $listing_id) {
        $query = "DELETE FROM favorites WHERE user_id = :user_id AND listing_id = :listing_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':listing_id', $listing_id);
            
            if($stmt->execute()) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to remove favorite'];
        } catch(PDOException $e) {
            error_log("Error removing favorite: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Check if listing is favorited
     */
    public function isFavorited($user_id, $listing_id) {
        $query = "SELECT id FROM favorites WHERE user_id = :user_id AND listing_id = :listing_id LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error checking favorite: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's favorites with listing details
     */
    public function getUserFavorites($user_id, $limit = 100) {
        $query = "SELECT f.*, 
                  l.id as listing_id, l.title, l.description, l.photo_url,
                  l.user_id, l.created_at as listing_created,
                  c.name as category_name,
                  ct.name as city_name,
                  u.username,
                  f.created_at as favorited_at
                  FROM favorites f
                  LEFT JOIN listings l ON f.listing_id = l.id
                  LEFT JOIN categories c ON l.category_id = c.id
                  LEFT JOIN cities ct ON l.city_id = ct.id
                  LEFT JOIN users u ON l.user_id = u.id
                  WHERE f.user_id = :user_id
                  AND l.id IS NOT NULL
                  ORDER BY f.created_at DESC
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error getting favorites: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get favorite count for listing
     */
    public function getListingFavoriteCount($listing_id) {
        $query = "SELECT COUNT(*) as count FROM favorites WHERE listing_id = :listing_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch(PDOException $e) {
            error_log("Error getting favorite count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Clear all favorites for user
     */
    public function clearAll($user_id) {
        $query = "DELETE FROM favorites WHERE user_id = :user_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if($stmt->execute()) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to clear favorites'];
        } catch(PDOException $e) {
            error_log("Error clearing favorites: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}
?>