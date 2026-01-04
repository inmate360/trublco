<?php
class AdvertisingManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get AdSense unit for placement
     */
    public function getAdSenseUnit($location, $position) {
        $query = "SELECT au.* 
                  FROM adsense_units au
                  LEFT JOIN ad_placements ap ON au.placement_id = ap.id
                  WHERE ap.location = :location 
                  AND ap.position = :position
                  AND au.is_active = TRUE
                  AND ap.is_active = TRUE
                  ORDER BY RAND()
                  LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':position', $position);
            $stmt->execute();
            
            $unit = $stmt->fetch();
            
            if($unit) {
                // Track impression
                $this->trackImpression('adsense', $unit['id']);
                
                // Update impression count
                $this->db->exec("UPDATE adsense_units SET impressions = impressions + 1 WHERE id = {$unit['id']}");
            }
            
            return $unit;
        } catch(PDOException $e) {
            error_log("Error getting AdSense unit: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get banner ad for rotation
     */
    public function getBannerAd($location, $position) {
        $query = "SELECT ba.* 
                  FROM banner_ads ba
                  LEFT JOIN ad_placements ap ON ba.placement_id = ap.id
                  WHERE ap.location = :location 
                  AND ap.position = :position
                  AND ba.is_active = TRUE
                  AND ba.start_date <= CURDATE()
                  AND ba.end_date >= CURDATE()
                  AND (ba.daily_budget IS NULL OR ba.total_spent < ba.daily_budget)
                  ORDER BY ba.priority DESC, RAND()
                  LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':position', $position);
            $stmt->execute();
            
            $banner = $stmt->fetch();
            
            if($banner) {
                // Track impression
                $this->trackImpression('banner', $banner['id']);
                
                // Update impression count
                $this->db->exec("UPDATE banner_ads SET impressions = impressions + 1 WHERE id = {$banner['id']}");
            }
            
            return $banner;
        } catch(PDOException $e) {
            error_log("Error getting banner ad: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get native ads to mix with listings
     */
    public function getNativeAds($category_id = null, $city_id = null, $limit = 3) {
        $query = "SELECT * FROM native_ads 
                  WHERE is_active = TRUE
                  AND (category_id IS NULL OR category_id = :category_id)
                  AND (city_id IS NULL OR city_id = :city_id)
                  AND total_spent < daily_budget
                  ORDER BY RAND()
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':city_id', $city_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $ads = $stmt->fetchAll();
            
            // Track impressions
            foreach($ads as $ad) {
                $this->trackImpression('native', $ad['id']);
                $this->db->exec("UPDATE native_ads SET impressions = impressions + 1 WHERE id = {$ad['id']}");
            }
            
            return $ads;
        } catch(PDOException $e) {
            error_log("Error getting native ads: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get premium/featured listings
     */
    public function getPremiumListings($limit = 6) {
        $query = "SELECT l.*, pl.placement_type, pl.impressions_limit, pl.current_impressions,
                  c.name as category_name, ct.name as city_name, u.username
                  FROM premium_listings pl
                  LEFT JOIN listings l ON pl.listing_id = l.id
                  LEFT JOIN categories c ON l.category_id = c.id
                  LEFT JOIN cities ct ON l.city_id = ct.id
                  LEFT JOIN users u ON l.user_id = u.id
                  WHERE pl.is_active = TRUE
                  AND pl.start_date <= NOW()
                  AND pl.end_date >= NOW()
                  AND (pl.impressions_limit IS NULL OR pl.current_impressions < pl.impressions_limit)
                  AND l.status = 'active'
                  ORDER BY RAND()
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $listings = $stmt->fetchAll();
            
            // Track impressions
            foreach($listings as $listing) {
                $this->trackImpression('premium_listing', $listing['id']);
                $this->db->exec("UPDATE premium_listings SET current_impressions = current_impressions + 1 WHERE listing_id = {$listing['id']}");
            }
            
            return $listings;
        } catch(PDOException $e) {
            error_log("Error getting premium listings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get sponsored profiles
     */
    public function getSponsoredProfiles($limit = 10) {
        $query = "SELECT u.*, sp.boost_level, sp.profile_badge
                  FROM sponsored_profiles sp
                  LEFT JOIN users u ON sp.user_id = u.id
                  WHERE sp.is_active = TRUE
                  AND sp.start_date <= NOW()
                  AND sp.end_date >= NOW()
                  ORDER BY sp.boost_level DESC, RAND()
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $profiles = $stmt->fetchAll();
            
            // Track impressions
            foreach($profiles as $profile) {
                $this->trackImpression('sponsored_profile', $profile['id']);
                $this->db->exec("UPDATE sponsored_profiles SET profile_views = profile_views + 1 WHERE user_id = {$profile['id']}");
            }
            
            return $profiles;
        } catch(PDOException $e) {
            error_log("Error getting sponsored profiles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user has active sponsored profile
     */
    public function hasSponsoredProfile($user_id) {
        $query = "SELECT * FROM sponsored_profiles 
                  WHERE user_id = :user_id 
                  AND is_active = TRUE
                  AND start_date <= NOW()
                  AND end_date >= NOW()
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Purchase premium listing
     */
    public function purchasePremiumListing($listing_id, $user_id, $placement_type, $duration_days, $cost) {
        $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
        
        $query = "INSERT INTO premium_listings 
                  (listing_id, user_id, placement_type, duration_days, cost, end_date, start_date)
                  VALUES (:listing_id, :user_id, :placement_type, :duration_days, :cost, :end_date, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':listing_id', $listing_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':placement_type', $placement_type);
            $stmt->bindParam(':duration_days', $duration_days);
            $stmt->bindParam(':cost', $cost);
            $stmt->bindParam(':end_date', $end_date);
            
            if($stmt->execute()) {
                // Update listing to featured
                $this->db->exec("UPDATE listings SET is_featured = TRUE WHERE id = {$listing_id}");
                
                return ['success' => true, 'premium_id' => $this->db->lastInsertId()];
            }
            
            return ['success' => false, 'error' => 'Failed to purchase premium listing'];
        } catch(PDOException $e) {
            error_log("Error purchasing premium listing: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Purchase sponsored profile
     */
    public function purchaseSponsoredProfile($user_id, $boost_level, $duration_days, $cost) {
        $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
        
        // Check if already has active sponsorship
        if($this->hasSponsoredProfile($user_id)) {
            return ['success' => false, 'error' => 'You already have an active sponsored profile'];
        }
        
        $query = "INSERT INTO sponsored_profiles 
                  (user_id, boost_level, duration_days, cost, end_date, start_date)
                  VALUES (:user_id, :boost_level, :duration_days, :cost, :end_date, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':boost_level', $boost_level);
            $stmt->bindParam(':duration_days', $duration_days);
            $stmt->bindParam(':cost', $cost);
            $stmt->bindParam(':end_date', $end_date);
            
            if($stmt->execute()) {
                return ['success' => true, 'sponsorship_id' => $this->db->lastInsertId()];
            }
            
            return ['success' => false, 'error' => 'Failed to purchase sponsored profile'];
        } catch(PDOException $e) {
            error_log("Error purchasing sponsored profile: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Track ad click
     */
    public function trackClick($ad_type, $ad_id, $user_id = null) {
        $ip = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $page_url = $_SERVER['HTTP_REFERER'] ?? '';
        
        $query = "INSERT INTO ad_clicks 
                  (ad_type, ad_id, user_id, ip_address, user_agent, page_url, clicked_at)
                  VALUES (:ad_type, :ad_id, :user_id, :ip, :user_agent, :page_url, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ad_type', $ad_type);
            $stmt->bindParam(':ad_id', $ad_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':user_agent', $user_agent);
            $stmt->bindParam(':page_url', $page_url);
            $stmt->execute();
            
            // Update click count and spending based on ad type
            $this->updateClickMetrics($ad_type, $ad_id);
            
            return true;
        } catch(PDOException $e) {
            error_log("Error tracking click: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get ad performance stats
     */
    public function getAdStats($ad_type, $ad_id) {
        $query = "SELECT 
                  (SELECT COUNT(*) FROM ad_impressions WHERE ad_type = :type1 AND ad_id = :id1) as impressions,
                  (SELECT COUNT(*) FROM ad_clicks WHERE ad_type = :type2 AND ad_id = :id2) as clicks";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':type1', $ad_type);
            $stmt->bindParam(':id1', $ad_id);
            $stmt->bindParam(':type2', $ad_type);
            $stmt->bindParam(':id2', $ad_id);
            $stmt->execute();
            
            $stats = $stmt->fetch();
            $stats['ctr'] = $stats['impressions'] > 0 ? ($stats['clicks'] / $stats['impressions']) * 100 : 0;
            
            return $stats;
        } catch(PDOException $e) {
            error_log("Error getting ad stats: " . $e->getMessage());
            return ['impressions' => 0, 'clicks' => 0, 'ctr' => 0];
        }
    }
    
    /**
     * Private helper methods
     */
    private function trackImpression($ad_type, $ad_id, $user_id = null) {
        $ip = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $page_url = $_SERVER['REQUEST_URI'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        $query = "INSERT INTO ad_impressions 
                  (ad_type, ad_id, user_id, ip_address, user_agent, page_url, referrer, viewed_at)
                  VALUES (:ad_type, :ad_id, :user_id, :ip, :user_agent, :page_url, :referrer, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ad_type', $ad_type);
            $stmt->bindParam(':ad_id', $ad_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':user_agent', $user_agent);
            $stmt->bindParam(':page_url', $page_url);
            $stmt->bindParam(':referrer', $referrer);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error tracking impression: " . $e->getMessage());
        }
    }
    
    private function updateClickMetrics($ad_type, $ad_id) {
        try {
            switch($ad_type) {
                case 'banner':
                    $query = "UPDATE banner_ads SET clicks = clicks + 1 WHERE id = :id";
                    if($this->exec($query, ['id' => $ad_id])) {
                        // Calculate cost
                        $banner = $this->db->query("SELECT cost_per_click FROM banner_ads WHERE id = {$ad_id}")->fetch();
                        if($banner && $banner['cost_per_click']) {
                            $this->db->exec("UPDATE banner_ads SET total_spent = total_spent + {$banner['cost_per_click']} WHERE id = {$ad_id}");
                        }
                    }
                    break;
                    
                case 'native':
                    $query = "UPDATE native_ads SET clicks = clicks + 1 WHERE id = :id";
                    if($this->exec($query, ['id' => $ad_id])) {
                        $native = $this->db->query("SELECT cpc FROM native_ads WHERE id = {$ad_id}")->fetch();
                        if($native && $native['cpc']) {
                            $this->db->exec("UPDATE native_ads SET total_spent = total_spent + {$native['cpc']} WHERE id = {$ad_id}");
                        }
                    }
                    break;
                    
                case 'premium_listing':
                    $this->db->exec("UPDATE premium_listings SET clicks = clicks + 1 WHERE id = {$ad_id}");
                    break;
                    
                case 'adsense':
                    $this->db->exec("UPDATE adsense_units SET clicks = clicks + 1 WHERE id = {$ad_id}");
                    break;
            }
        } catch(PDOException $e) {
            error_log("Error updating click metrics: " . $e->getMessage());
        }
    }
    
    private function exec($query, $params) {
        $stmt = $this->db->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        return $stmt->execute();
    }
    
    private function getClientIP() {
        if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}
?>