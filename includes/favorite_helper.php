<?php
function isListingFavorited($db, $user_id, $listing_id) {
    if(!$user_id || !$listing_id) {
        return false;
    }
    
    try {
        // Check if favorites table exists
        $check = "SHOW TABLES LIKE 'favorites'";
        $stmt = $db->prepare($check);
        $stmt->execute();
        
        if($stmt->rowCount() == 0) {
            return false;
        }
        
        $query = "SELECT id FROM favorites WHERE user_id = :user_id AND listing_id = :listing_id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':listing_id', $listing_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        error_log("Error checking favorite status: " . $e->getMessage());
        return false;
    }
}

function getFavoritesCount($db, $user_id) {
    if(!$user_id) {
        return 0;
    }
    
    try {
        // Check if favorites table exists
        $check = "SHOW TABLES LIKE 'favorites'";
        $stmt = $db->prepare($check);
        $stmt->execute();
        
        if($stmt->rowCount() == 0) {
            return 0;
        }
        
        $query = "SELECT COUNT(*) as count FROM favorites WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    } catch(PDOException $e) {
        error_log("Error getting favorites count: " . $e->getMessage());
        return 0;
    }
}
?>