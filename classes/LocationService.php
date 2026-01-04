<?php
class LocationService {
    private $db;
    
    // Earth radius in kilometers
    const EARTH_RADIUS_KM = 6371;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Update user's current location
     */
    public function updateUserLocation($user_id, $latitude, $longitude, $auto_detected = false) {
        // Validate coordinates
        if(!$this->isValidCoordinates($latitude, $longitude)) {
            return ['success' => false, 'error' => 'Invalid coordinates'];
        }
        
        // Get city from coordinates
        $city_id = $this->getCityFromCoordinates($latitude, $longitude);
        
        $query = "UPDATE users 
                  SET current_latitude = :lat, 
                      current_longitude = :lng, 
                      location_updated_at = NOW(),
                      auto_location = :auto
                  WHERE id = :user_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':lat', $latitude);
            $stmt->bindParam(':lng', $longitude);
            $stmt->bindParam(':auto', $auto_detected, PDO::PARAM_BOOL);
            $stmt->bindParam(':user_id', $user_id);
            
            if($stmt->execute()) {
                // Log location history
                $this->logLocationHistory($user_id, $latitude, $longitude, $city_id);
                
                // Update nearby cache
                $this->updateNearbyCache($user_id);
                
                return ['success' => true, 'city_id' => $city_id];
            }
            
            return ['success' => false, 'error' => 'Failed to update location'];
        } catch(PDOException $e) {
            error_log("Error updating user location: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get nearby users within radius
     */
    public function getNearbyUsers($user_id, $radius_km = 50, $limit = 50) {
        // Get user's current location
        $query = "SELECT current_latitude, current_longitude, show_distance 
                  FROM users WHERE id = :user_id LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if(!$user || !$user['current_latitude']) {
            return ['success' => false, 'error' => 'Location not set'];
        }
        
        $lat = $user['current_latitude'];
        $lng = $user['current_longitude'];
        
        // Calculate bounding box for initial filter (performance optimization)
        $bounds = $this->getBoundingBox($lat, $lng, $radius_km);
        
        // Query nearby users using Haversine formula
        $query = "SELECT u.id, u.username, u.created_at, u.is_online, u.last_seen,
                  u.current_latitude, u.current_longitude, u.show_distance,
                  (
                      " . self::EARTH_RADIUS_KM . " * acos(
                          cos(radians(:lat1)) * cos(radians(u.current_latitude)) *
                          cos(radians(u.current_longitude) - radians(:lng1)) +
                          sin(radians(:lat2)) * sin(radians(u.current_latitude))
                      )
                  ) AS distance_km
                  FROM users u
                  WHERE u.id != :user_id
                  AND u.current_latitude IS NOT NULL
                  AND u.current_longitude IS NOT NULL
                  AND u.current_latitude BETWEEN :min_lat AND :max_lat
                  AND u.current_longitude BETWEEN :min_lng AND :max_lng
                  AND u.is_suspended = FALSE
                  AND u.is_banned = FALSE
                  HAVING distance_km <= :radius
                  ORDER BY distance_km ASC
                  LIMIT :limit";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':lat1', $lat);
            $stmt->bindParam(':lng1', $lng);
            $stmt->bindParam(':lat2', $lat);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':min_lat', $bounds['min_lat']);
            $stmt->bindParam(':max_lat', $bounds['max_lat']);
            $stmt->bindParam(':min_lng', $bounds['min_lng']);
            $stmt->bindParam(':max_lng', $bounds['max_lng']);
            $stmt->bindParam(':radius', $radius_km);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $users = $stmt->fetchAll();
            
            // Format distance display
            foreach($users as &$u) {
                if(!$u['show_distance']) {
                    $u['distance_display'] = 'Hidden';
                } else {
                    $u['distance_display'] = $this->formatDistance($u['distance_km']);
                }
            }
            
            return ['success' => true, 'users' => $users, 'count' => count($users)];
        } catch(PDOException $e) {
            error_log("Error getting nearby users: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get nearby listings
     */
    public function getNearbyListings($latitude, $longitude, $radius_km = 50, $filters = []) {
        $bounds = $this->getBoundingBox($latitude, $longitude, $radius_km);
        
        $query = "SELECT l.*, 
                  c.name as category_name,
                  ct.name as city_name,
                  s.abbreviation as state_abbr,
                  u.username,
                  (
                      " . self::EARTH_RADIUS_KM . " * acos(
                          cos(radians(:lat1)) * cos(radians(l.latitude)) *
                          cos(radians(l.longitude) - radians(:lng1)) +
                          sin(radians(:lat2)) * sin(radians(l.latitude))
                      )
                  ) AS distance_km
                  FROM listings l
                  LEFT JOIN categories c ON l.category_id = c.id
                  LEFT JOIN cities ct ON l.city_id = ct.id
                  LEFT JOIN states s ON ct.state_id = s.id
                  LEFT JOIN users u ON l.user_id = u.id
                  WHERE l.status = 'active'
                  AND l.latitude IS NOT NULL
                  AND l.longitude IS NOT NULL
                  AND l.latitude BETWEEN :min_lat AND :max_lat
                  AND l.longitude BETWEEN :min_lng AND :max_lng";
        
        // Add filters
        if(!empty($filters['category_id'])) {
            $query .= " AND l.category_id = :category_id";
        }
        if(!empty($filters['neighborhood'])) {
            $query .= " AND l.neighborhood = :neighborhood";
        }
        if(!empty($filters['zip_code'])) {
            $query .= " AND l.zip_code = :zip_code";
        }
        
        $query .= " HAVING distance_km <= :radius
                    ORDER BY distance_km ASC
                    LIMIT 100";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':lat1', $latitude);
            $stmt->bindParam(':lng1', $longitude);
            $stmt->bindParam(':lat2', $latitude);
            $stmt->bindParam(':min_lat', $bounds['min_lat']);
            $stmt->bindParam(':max_lat', $bounds['max_lat']);
            $stmt->bindParam(':min_lng', $bounds['min_lng']);
            $stmt->bindParam(':max_lng', $bounds['max_lng']);
            $stmt->bindParam(':radius', $radius_km);
            
            if(!empty($filters['category_id'])) {
                $stmt->bindParam(':category_id', $filters['category_id']);
            }
            if(!empty($filters['neighborhood'])) {
                $stmt->bindParam(':neighborhood', $filters['neighborhood']);
            }
            if(!empty($filters['zip_code'])) {
                $stmt->bindParam(':zip_code', $filters['zip_code']);
            }
            
            $stmt->execute();
            $listings = $stmt->fetchAll();
            
            // Format distances
            foreach($listings as &$listing) {
                $listing['distance_display'] = $this->formatDistance($listing['distance_km']);
            }
            
            return ['success' => true, 'listings' => $listings];
        } catch(PDOException $e) {
            error_log("Error getting nearby listings: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Create travel listing (multi-city posting)
     */
    public function createTravelListing($listing_id, $city_ids, $start_date, $end_date) {
        try {
            $this->db->beginTransaction();
            
            foreach($city_ids as $city_id) {
                $query = "INSERT INTO travel_listings (listing_id, city_id, start_date, end_date) 
                          VALUES (:listing_id, :city_id, :start_date, :end_date)";
                
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':listing_id', $listing_id);
                $stmt->bindParam(':city_id', $city_id);
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
                $stmt->execute();
            }
            
            $this->db->commit();
            return ['success' => true];
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating travel listing: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get active travel listings for a city
     */
    public function getTravelListings($city_id) {
        $query = "SELECT l.*, tl.start_date, tl.end_date, tl.city_id as travel_city_id,
                  c.name as category_name, u.username,
                  home_city.name as home_city_name
                  FROM travel_listings tl
                  LEFT JOIN listings l ON tl.listing_id = l.id
                  LEFT JOIN categories c ON l.category_id = c.id
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN cities home_city ON l.city_id = home_city.id
                  WHERE tl.city_id = :city_id
                  AND tl.is_active = TRUE
                  AND tl.start_date <= CURDATE()
                  AND tl.end_date >= CURDATE()
                  AND l.status = 'active'
                  ORDER BY tl.created_at DESC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':city_id', $city_id);
            $stmt->execute();
            
            return ['success' => true, 'listings' => $stmt->fetchAll()];
        } catch(PDOException $e) {
            error_log("Error getting travel listings: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Get neighborhoods for a city
     */
    public function getNeighborhoods($city_id) {
        $query = "SELECT * FROM neighborhoods WHERE city_id = :city_id ORDER BY name ASC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':city_id', $city_id);
            $stmt->execute();
            
            return ['success' => true, 'neighborhoods' => $stmt->fetchAll()];
        } catch(PDOException $e) {
            error_log("Error getting neighborhoods: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    /**
     * Geocode address to coordinates
     */
    public function geocodeAddress($address) {
        // Using OpenStreetMap Nominatim (free, no API key required)
        $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Turnpage/1.0');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if(!empty($data) && isset($data[0])) {
            return [
                'success' => true,
                'latitude' => (float)$data[0]['lat'],
                'longitude' => (float)$data[0]['lon'],
                'display_name' => $data[0]['display_name']
            ];
        }
        
        return ['success' => false, 'error' => 'Address not found'];
    }
    
    /**
     * Reverse geocode coordinates to address
     */
    public function reverseGeocode($latitude, $longitude) {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Turnpage/1.0');
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if(!empty($data) && isset($data['address'])) {
            return [
                'success' => true,
                'address' => $data['address'],
                'display_name' => $data['display_name'],
                'neighborhood' => $data['address']['neighbourhood'] ?? $data['address']['suburb'] ?? null,
                'city' => $data['address']['city'] ?? $data['address']['town'] ?? null,
                'state' => $data['address']['state'] ?? null,
                'zip_code' => $data['address']['postcode'] ?? null
            ];
        }
        
        return ['success' => false, 'error' => 'Location not found'];
    }
    
    /**
     * Calculate distance between two points
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);
        
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        
        $a = sin($dlat/2) * sin($dlat/2) + 
             cos($lat1) * cos($lat2) * 
             sin($dlng/2) * sin($dlng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return self::EARTH_RADIUS_KM * $c;
    }
    
    /**
     * Toggle distance visibility
     */
    public function toggleDistanceVisibility($user_id, $show_distance) {
        $query = "UPDATE users SET show_distance = :show WHERE id = :user_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':show', $show_distance, PDO::PARAM_BOOL);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error toggling distance visibility: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Private helper methods
     */
    private function isValidCoordinates($lat, $lng) {
        return is_numeric($lat) && is_numeric($lng) &&
               $lat >= -90 && $lat <= 90 &&
               $lng >= -180 && $lng <= 180;
    }
    
    private function getBoundingBox($lat, $lng, $radius_km) {
        $lat_change = $radius_km / 111.2; // 1 degree latitude = ~111.2km
        $lng_change = abs(cos(deg2rad($lat)) * $radius_km / 111.2);
        
        return [
            'min_lat' => $lat - $lat_change,
            'max_lat' => $lat + $lat_change,
            'min_lng' => $lng - $lng_change,
            'max_lng' => $lng + $lng_change
        ];
    }
    
    private function formatDistance($km) {
        if($km < 1) {
            return round($km * 1000) . ' meters';
        } elseif($km < 10) {
            return round($km, 1) . ' km';
        } else {
            return round($km) . ' km';
        }
    }
    
    private function getCityFromCoordinates($lat, $lng) {
        // Find nearest city in database
        $query = "SELECT id FROM cities 
                  WHERE latitude IS NOT NULL 
                  AND longitude IS NOT NULL
                  ORDER BY (
                      (latitude - :lat) * (latitude - :lat) +
                      (longitude - :lng) * (longitude - :lng)
                  ) ASC
                  LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':lat', $lat);
            $stmt->bindParam(':lng', $lng);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? $result['id'] : null;
        } catch(PDOException $e) {
            return null;
        }
    }
    
    private function logLocationHistory($user_id, $lat, $lng, $city_id) {
        $query = "INSERT INTO user_location_history (user_id, latitude, longitude, city_id) 
                  VALUES (:user_id, :lat, :lng, :city_id)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':lat', $lat);
            $stmt->bindParam(':lng', $lng);
            $stmt->bindParam(':city_id', $city_id);
            $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error logging location history: " . $e->getMessage());
        }
    }
    
    private function updateNearbyCache($user_id) {
        // This runs in background to update the cache
        // Can be implemented as a cron job for better performance
    }
}
?>