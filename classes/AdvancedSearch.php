<?php
/**
 * AdvancedSearch Class - Handles advanced search with filters and location-based matching
 */
class AdvancedSearch {
    private $conn;
    private $userProfile;

    public function __construct($db) {
        $this->conn = $db;
        $this->userProfile = new UserProfile($db);
    }

    // Search users with advanced filters
    public function searchUsers($user_id, $filters = []) {
        $userLocation = $this->userProfile->getUserLocation($user_id);
        $userPrefs = $this->userProfile->getPreferences($user_id);
        
        // Build base query
        $query = "SELECT DISTINCT u.id, u.username, u.created_at,
                         up.bio, up.height, up.body_type, up.ethnicity, up.relationship_status,
                         up.occupation, up.education, up.interests, up.profile_completion,
                         ul.latitude, ul.longitude, ul.city_id,
                         (SELECT file_path FROM user_photos WHERE user_id = u.id AND is_primary = TRUE LIMIT 1) as photo,
                         c.name as city_name, s.abbreviation as state_abbr,
                         u.is_online, u.last_activity
                  FROM users u
                  LEFT JOIN user_profiles up ON u.id = up.user_id
                  LEFT JOIN user_locations ul ON u.id = ul.user_id
                  LEFT JOIN cities c ON ul.city_id = c.id
                  LEFT JOIN states s ON c.state_id = s.id
                  WHERE u.id != :user_id";
        
        $params = [':user_id' => $user_id];
        
        // Check for blocks
        $query .= " AND u.id NOT IN (
                        SELECT blocked_id FROM user_blocks WHERE blocker_id = :user_id2
                        UNION
                        SELECT blocker_id FROM user_blocks WHERE blocked_id = :user_id3
                    )";
        $params[':user_id2'] = $user_id;
        $params[':user_id3'] = $user_id;
        
        // Apply filters
        if(!empty($filters['min_age'])) {
            $query .= " AND TIMESTAMPDIFF(YEAR, u.created_at, CURDATE()) >= :min_age";
            $params[':min_age'] = $filters['min_age'];
        }
        
        if(!empty($filters['max_age'])) {
            $query .= " AND TIMESTAMPDIFF(YEAR, u.created_at, CURDATE()) <= :max_age";
            $params[':max_age'] = $filters['max_age'];
        }
        
        if(!empty($filters['body_type'])) {
            $placeholders = [];
            foreach($filters['body_type'] as $index => $type) {
                $key = ":body_type_{$index}";
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $query .= " AND up.body_type IN (" . implode(',', $placeholders) . ")";
        }
        
        if(!empty($filters['ethnicity'])) {
            $placeholders = [];
            foreach($filters['ethnicity'] as $index => $eth) {
                $key = ":ethnicity_{$index}";
                $placeholders[] = $key;
                $params[$key] = $eth;
            }
            $query .= " AND up.ethnicity IN (" . implode(',', $placeholders) . ")";
        }
        
        if(!empty($filters['relationship_status'])) {
            $placeholders = [];
            foreach($filters['relationship_status'] as $index => $status) {
                $key = ":rel_status_{$index}";
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $query .= " AND up.relationship_status IN (" . implode(',', $placeholders) . ")";
        }
        
        if(!empty($filters['height_min'])) {
            $query .= " AND up.height >= :height_min";
            $params[':height_min'] = $filters['height_min'];
        }
        
        if(!empty($filters['height_max'])) {
            $query .= " AND up.height <= :height_max";
            $params[':height_max'] = $filters['height_max'];
        }
        
        if(!empty($filters['education'])) {
            $query .= " AND up.education = :education";
            $params[':education'] = $filters['education'];
        }
        
        if(!empty($filters['smoking'])) {
            $query .= " AND up.smoking = :smoking";
            $params[':smoking'] = $filters['smoking'];
        }
        
        if(!empty($filters['drinking'])) {
            $query .= " AND up.drinking = :drinking";
            $params[':drinking'] = $filters['drinking'];
        }
        
        if(isset($filters['has_kids']) && $filters['has_kids'] !== '') {
            $query .= " AND up.has_kids = :has_kids";
            $params[':has_kids'] = $filters['has_kids'];
        }
        
        if(!empty($filters['wants_kids'])) {
            $query .= " AND up.wants_kids = :wants_kids";
            $params[':wants_kids'] = $filters['wants_kids'];
        }
        
        if(!empty($filters['only_with_photos'])) {
            $query .= " AND EXISTS (SELECT 1 FROM user_photos WHERE user_id = u.id LIMIT 1)";
        }
        
        if(!empty($filters['only_online'])) {
            $query .= " AND u.is_online = TRUE";
        }
        
        if(!empty($filters['keyword'])) {
            $query .= " AND (up.bio LIKE :keyword OR up.interests LIKE :keyword OR u.username LIKE :keyword)";
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }
        
        // Location-based filtering
        if($userLocation && !empty($filters['max_distance'])) {
            $query .= " AND ul.latitude IS NOT NULL AND ul.longitude IS NOT NULL";
        }
        
        $query .= " ORDER BY u.is_online DESC, u.last_activity DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $query .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $this->conn->prepare($query);
        
        foreach($params as $key => $value) {
            if($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        // Calculate distances if location available
        if($userLocation && !empty($results)) {
            foreach($results as &$result) {
                if($result['latitude'] && $result['longitude']) {
                    $distance = $this->userProfile->calculateDistance(
                        $userLocation['latitude'],
                        $userLocation['longitude'],
                        $result['latitude'],
                        $result['longitude']
                    );
                    $result['distance'] = $distance;
                    
                    // Filter by max distance if specified
                    if(!empty($filters['max_distance']) && $distance > $filters['max_distance']) {
                        unset($result);
                    }
                } else {
                    $result['distance'] = null;
                }
            }
            
            // Remove filtered results
            $results = array_values(array_filter($results));
            
            // Sort by distance if filtering by distance
            if(!empty($filters['max_distance'])) {
                usort($results, function($a, $b) {
                    if($a['distance'] === null) return 1;
                    if($b['distance'] === null) return -1;
                    return $a['distance'] <=> $b['distance'];
                });
            }
        }
        
        return $results;
    }

    // Get match suggestions based on preferences
    public function getMatchSuggestions($user_id, $limit = 20) {
        $prefs = $this->userProfile->getPreferences($user_id);
        $userLocation = $this->userProfile->getUserLocation($user_id);
        
        $filters = [];
        
        if($prefs) {
            $filters['min_age'] = $prefs['min_age'];
            $filters['max_age'] = $prefs['max_age'];
            $filters['max_distance'] = $prefs['max_distance'];
            
            if(!empty($prefs['preferred_body_types'])) {
                $filters['body_type'] = $prefs['preferred_body_types'];
            }
            
            if(!empty($prefs['preferred_ethnicity'])) {
                $filters['ethnicity'] = $prefs['preferred_ethnicity'];
            }
            
            if(!empty($prefs['preferred_relationship_status'])) {
                $filters['relationship_status'] = $prefs['preferred_relationship_status'];
            }
            
            if($prefs['only_with_photos']) {
                $filters['only_with_photos'] = true;
            }
        }
        
        $filters['limit'] = $limit;
        
        return $this->searchUsers($user_id, $filters);
    }

    // Get nearby users
    public function getNearbyUsers($user_id, $radius = 25, $limit = 50) {
        $userLocation = $this->userProfile->getUserLocation($user_id);
        
        if(!$userLocation || !$userLocation['latitude'] || !$userLocation['longitude']) {
            return [];
        }
        
        $filters = [
            'max_distance' => $radius,
            'limit' => $limit
        ];
        
        return $this->searchUsers($user_id, $filters);
    }

    // Get online users
    public function getOnlineUsers($user_id, $limit = 50) {
        $filters = [
            'only_online' => true,
            'limit' => $limit
        ];
        
        return $this->searchUsers($user_id, $filters);
    }

    // Quick search by username
    public function quickSearch($user_id, $keyword, $limit = 20) {
        $filters = [
            'keyword' => $keyword,
            'limit' => $limit
        ];
        
        return $this->searchUsers($user_id, $filters);
    }
}
?>