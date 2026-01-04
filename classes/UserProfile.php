<?php
class UserProfile {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create or update profile
    public function saveProfile($user_id, $data) {
        // Check if profile exists
        $query = "SELECT id FROM user_profiles WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $this->updateProfile($user_id, $data);
        } else {
            return $this->createProfile($user_id, $data);
        }
    }
    
    // Create profile
    private function createProfile($user_id, $data) {
        $query = "INSERT INTO user_profiles
                  (user_id, bio, height, body_type, ethnicity, relationship_status,
                   looking_for, interests, occupation, education, smoking, drinking,
                   has_kids, wants_kids, languages, display_distance, show_age, show_online_status)
                  VALUES
                  (:user_id, :bio, :height, :body_type, :ethnicity, :relationship_status,
                   :looking_for, :interests, :occupation, :education, :smoking, :drinking,
                   :has_kids, :wants_kids, :languages, :display_distance, :show_age, :show_online_status)";
        
        $stmt = $this->conn->prepare($query);
        $this->bindProfileParams($stmt, $user_id, $data);
        
        if($stmt->execute()) {
            $this->calculateProfileCompletion($user_id);
            return true;
        }
        
        return false;
    }
    
    // Update profile
    private function updateProfile($user_id, $data) {
        $query = "UPDATE user_profiles SET
                  bio = :bio, height = :height, body_type = :body_type,
                  ethnicity = :ethnicity, relationship_status = :relationship_status,
                  looking_for = :looking_for, interests = :interests,
                  occupation = :occupation, education = :education,
                  smoking = :smoking, drinking = :drinking, has_kids = :has_kids,
                  wants_kids = :wants_kids, languages = :languages,
                  display_distance = :display_distance, show_age = :show_age,
                  show_online_status = :show_online_status
                  WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $this->bindProfileParams($stmt, $user_id, $data);
        
        if($stmt->execute()) {
            $this->calculateProfileCompletion($user_id);
            return true;
        }
        
        return false;
    }
    
    // Bind parameters
    private function bindProfileParams($stmt, $user_id, $data) {
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':bio', $data['bio']);
        $stmt->bindParam(':height', $data['height']);
        $stmt->bindParam(':body_type', $data['body_type']);
        $stmt->bindParam(':ethnicity', $data['ethnicity']);
        $stmt->bindParam(':relationship_status', $data['relationship_status']);
        
        $looking_for = json_encode($data['looking_for'] ?? []);
        $interests = json_encode($data['interests'] ?? []);
        $languages = json_encode($data['languages'] ?? []);
        
        $stmt->bindParam(':looking_for', $looking_for);
        $stmt->bindParam(':interests', $interests);
        $stmt->bindParam(':occupation', $data['occupation']);
        $stmt->bindParam(':education', $data['education']);
        $stmt->bindParam(':smoking', $data['smoking']);
        $stmt->bindParam(':drinking', $data['drinking']);
        $stmt->bindParam(':has_kids', $data['has_kids'], PDO::PARAM_BOOL);
        $stmt->bindParam(':wants_kids', $data['wants_kids']);
        $stmt->bindParam(':languages', $languages);
        $stmt->bindParam(':display_distance', $data['display_distance'], PDO::PARAM_BOOL);
        $stmt->bindParam(':show_age', $data['show_age'], PDO::PARAM_BOOL);
        $stmt->bindParam(':show_online_status', $data['show_online_status'], PDO::PARAM_BOOL);
    }
    
    // Get profile
    public function getProfile($user_id) {
        $query = "SELECT up.*, u.username, u.email, u.avatar, u.created_at as member_since, 
                  u.is_online, u.last_activity, u.is_admin, u.verified, u.creator,
                  TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as age
                  FROM user_profiles up
                  LEFT JOIN users u ON up.user_id = u.id
                  WHERE up.user_id = :user_id
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($profile) {
            // Decode JSON fields
            $profile['looking_for'] = json_decode($profile['looking_for'], true) ?? [];
            $profile['interests'] = json_decode($profile['interests'], true) ?? [];
            $profile['languages'] = json_decode($profile['languages'], true) ?? [];
        }
        
        return $profile;
    }
    
    // Calculate profile completion percentage
    private function calculateProfileCompletion($user_id) {
        $profile = $this->getProfile($user_id);
        if(!$profile) return 0;
        
        $fields = ['bio', 'height', 'body_type', 'ethnicity', 'relationship_status',
                   'occupation', 'education', 'smoking', 'drinking'];
        
        $completed = 0;
        $total = count($fields) + 3; // +3 for arrays
        
        foreach($fields as $field) {
            if(!empty($profile[$field])) $completed++;
        }
        
        if(!empty($profile['looking_for'])) $completed++;
        if(!empty($profile['interests'])) $completed++;
        if(!empty($profile['languages'])) $completed++;
        
        $percentage = round(($completed / $total) * 100);
        
        $query = "UPDATE user_profiles SET profile_completion = :percentage WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':percentage', $percentage);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $percentage;
    }
    
    // Save user location
    public function saveLocation($user_id, $city_id, $latitude, $longitude, $postal_code = null, $max_distance = 50) {
        $query = "INSERT INTO user_locations
                  (user_id, city_id, latitude, longitude, postal_code, max_distance)
                  VALUES (:user_id, :city_id, :latitude, :longitude, :postal_code, :max_distance)
                  ON DUPLICATE KEY UPDATE
                  city_id = :city_id2, latitude = :latitude2, longitude = :longitude2,
                  postal_code = :postal_code2, max_distance = :max_distance2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':city_id', $city_id);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':postal_code', $postal_code);
        $stmt->bindParam(':max_distance', $max_distance);
        $stmt->bindParam(':city_id2', $city_id);
        $stmt->bindParam(':latitude2', $latitude);
        $stmt->bindParam(':longitude2', $longitude);
        $stmt->bindParam(':postal_code2', $postal_code);
        $stmt->bindParam(':max_distance2', $max_distance);
        
        return $stmt->execute();
    }
    
    // Get user location
    public function getUserLocation($user_id) {
        $query = "SELECT * FROM user_locations WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Calculate distance between two users (Haversine formula)
    public function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 3959; // miles (use 6371 for km)
        
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                 cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        return round($angle * $earthRadius, 1);
    }
    
    // Save preferences
    public function savePreferences($user_id, $preferences) {
        $query = "INSERT INTO user_preferences
                  (user_id, min_age, max_age, preferred_gender, preferred_body_types,
                   preferred_ethnicity, max_distance, only_with_photos, only_verified,
                   preferred_relationship_status, deal_breakers)
                  VALUES
                  (:user_id, :min_age, :max_age, :preferred_gender, :preferred_body_types,
                   :preferred_ethnicity, :max_distance, :only_with_photos, :only_verified,
                   :preferred_relationship_status, :deal_breakers)
                  ON DUPLICATE KEY UPDATE
                  min_age = :min_age2, max_age = :max_age2, preferred_gender = :preferred_gender2,
                  preferred_body_types = :preferred_body_types2, preferred_ethnicity = :preferred_ethnicity2,
                  max_distance = :max_distance2, only_with_photos = :only_with_photos2,
                  only_verified = :only_verified2, preferred_relationship_status = :preferred_relationship_status2,
                  deal_breakers = :deal_breakers2";
        
        $stmt = $this->conn->prepare($query);
        
        $preferred_gender = json_encode($preferences['preferred_gender'] ?? []);
        $preferred_body_types = json_encode($preferences['preferred_body_types'] ?? []);
        $preferred_ethnicity = json_encode($preferences['preferred_ethnicity'] ?? []);
        $preferred_relationship = json_encode($preferences['preferred_relationship_status'] ?? []);
        $deal_breakers = json_encode($preferences['deal_breakers'] ?? []);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':min_age', $preferences['min_age']);
        $stmt->bindParam(':max_age', $preferences['max_age']);
        $stmt->bindParam(':preferred_gender', $preferred_gender);
        $stmt->bindParam(':preferred_body_types', $preferred_body_types);
        $stmt->bindParam(':preferred_ethnicity', $preferred_ethnicity);
        $stmt->bindParam(':max_distance', $preferences['max_distance']);
        $stmt->bindParam(':only_with_photos', $preferences['only_with_photos'], PDO::PARAM_BOOL);
        $stmt->bindParam(':only_verified', $preferences['only_verified'], PDO::PARAM_BOOL);
        $stmt->bindParam(':preferred_relationship_status', $preferred_relationship);
        $stmt->bindParam(':deal_breakers', $deal_breakers);
        
        // Duplicate key bindings
        $stmt->bindParam(':min_age2', $preferences['min_age']);
        $stmt->bindParam(':max_age2', $preferences['max_age']);
        $stmt->bindParam(':preferred_gender2', $preferred_gender);
        $stmt->bindParam(':preferred_body_types2', $preferred_body_types);
        $stmt->bindParam(':preferred_ethnicity2', $preferred_ethnicity);
        $stmt->bindParam(':max_distance2', $preferences['max_distance']);
        $stmt->bindParam(':only_with_photos2', $preferences['only_with_photos'], PDO::PARAM_BOOL);
        $stmt->bindParam(':only_verified2', $preferences['only_verified'], PDO::PARAM_BOOL);
        $stmt->bindParam(':preferred_relationship_status2', $preferred_relationship);
        $stmt->bindParam(':deal_breakers2', $deal_breakers);
        
        return $stmt->execute();
    }
    
    // Get preferences
    public function getPreferences($user_id) {
        $query = "SELECT * FROM user_preferences WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($prefs) {
            $prefs['preferred_gender'] = json_decode($prefs['preferred_gender'], true) ?? [];
            $prefs['preferred_body_types'] = json_decode($prefs['preferred_body_types'], true) ?? [];
            $prefs['preferred_ethnicity'] = json_decode($prefs['preferred_ethnicity'], true) ?? [];
            $prefs['preferred_relationship_status'] = json_decode($prefs['preferred_relationship_status'], true) ?? [];
            $prefs['deal_breakers'] = json_decode($prefs['deal_breakers'], true) ?? [];
        }
        
        return $prefs;
    }
    
    // Record profile view
    public function recordView($viewerid, $viewedid) {
    if($viewerid == $viewedid) {
        return; // Don't count self-views
    }
    
    $query = "INSERT INTO profileviews (viewerid, viewedid, lastviewed) 
              VALUES (:viewerid, :viewedid, CURRENT_TIMESTAMP) 
              ON DUPLICATE KEY UPDATE lastviewed = CURRENT_TIMESTAMP";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':viewerid', $viewerid);
    $stmt->bindParam(':viewedid', $viewedid);
    return $stmt->execute();
}

    
    // Get who viewed my profile
    public function getProfileViews($user_id, $limit = 20) {
        $query = "SELECT pv.*, u.username, up.bio,
                  (SELECT file_path FROM user_photos WHERE user_id = pv.viewer_id AND is_primary = TRUE LIMIT 1) as photo
                  FROM profile_views pv
                  LEFT JOIN users u ON pv.viewer_id = u.id
                  LEFT JOIN user_profiles up ON pv.viewer_id = up.user_id
                  WHERE pv.viewed_id = :user_id
                  ORDER BY pv.last_viewed DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add to favorites
    public function addFavorite($user_id, $favorited_user_id) {
        $query = "INSERT INTO user_favorites (user_id, favorited_user_id)
                  VALUES (:user_id, :favorited_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':favorited_id', $favorited_user_id);
        
        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            return false; // Already favorited
        }
    }
    
    // Remove from favorites
    public function removeFavorite($user_id, $favorited_user_id) {
        $query = "DELETE FROM user_favorites WHERE user_id = :user_id AND favorited_user_id = :favorited_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':favorited_id', $favorited_user_id);
        return $stmt->execute();
    }
    
    // Check if favorited
    public function isFavorited($user_id, $favorited_user_id) {
        $query = "SELECT id FROM user_favorites WHERE user_id = :user_id AND favorited_user_id = :favorited_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':favorited_id', $favorited_user_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    // Get favorites
    public function getFavorites($user_id, $limit = 50) {
        $query = "SELECT u.id, u.username, up.bio, up.body_type, up.relationship_status,
                  (SELECT file_path FROM user_photos WHERE user_id = u.id AND is_primary = TRUE LIMIT 1) as photo,
                  ul.latitude, ul.longitude, uf.created_at as favorited_at
                  FROM user_favorites uf
                  LEFT JOIN users u ON uf.favorited_user_id = u.id
                  LEFT JOIN user_profiles up ON u.id = up.user_id
                  LEFT JOIN user_locations ul ON u.id = ul.user_id
                  WHERE uf.user_id = :user_id
                  ORDER BY uf.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Block user
    public function blockUser($blocker_id, $blocked_id, $reason = null) {
        $query = "INSERT INTO user_blocks (blocker_id, blocked_id, reason)
                  VALUES (:blocker_id, :blocked_id, :reason)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':blocker_id', $blocker_id);
        $stmt->bindParam(':blocked_id', $blocked_id);
        $stmt->bindParam(':reason', $reason);
        
        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Check if blocked
    public function isBlocked($blocker_id, $blocked_id) {
        $query = "SELECT id FROM user_blocks
                  WHERE (blocker_id = :id1 AND blocked_id = :id2)
                  OR (blocker_id = :id2 AND blocked_id = :id1)
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id1', $blocker_id);
        $stmt->bindParam(':id2', $blocked_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Upload and save user avatar
     * @param int $user_id User ID
     * @param array $file File data from $_FILES
     * @return bool|string True on success, error message on failure
     */
    public function uploadAvatar($user_id, $file) {
        // Validate file
        if (!isset($file['tmp_name'])) {
            return 'Invalid file upload';
        }
        
        // Check file size (2MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            return 'File size exceeds 2MB limit';
        }
        
        // Validate image type using exif_imagetype for security
        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
        $detected_type = @exif_imagetype($file['tmp_name']);
        
        if (!in_array($detected_type, $allowed_types)) {
            return 'Only JPEG and PNG images are allowed';
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/avatars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = $detected_type === IMAGETYPE_PNG ? 'png' : 'jpg';
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        $db_path = '/uploads/avatars/' . $filename;
        
        // Process and resize image
        $processed = $this->processImage($file['tmp_name'], $filepath, $detected_type);
        
        if (!$processed) {
            return 'Failed to process image';
        }
        
        // Delete old avatar if exists
        $query = "SELECT avatar FROM users WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $old_avatar = $stmt->fetchColumn();
        
        if ($old_avatar && file_exists(__DIR__ . '/..' . $old_avatar)) {
            @unlink(__DIR__ . '/..' . $old_avatar);
        }
        
        // Update database
        $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':avatar', $db_path);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return 'Failed to update database';
    }
    
    /**
     * Process and resize image
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param int $image_type Image type constant
     * @param int $max_width Maximum width (default 400)
     * @param int $max_height Maximum height (default 400)
     * @return bool Success status
     */
    private function processImage($source, $destination, $image_type, $max_width = 400, $max_height = 400) {
        // Create image resource from source
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $source_image = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $source_image = @imagecreatefrompng($source);
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            return false;
        }
        
        // Get original dimensions
        $orig_width = imagesx($source_image);
        $orig_height = imagesy($source_image);
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($max_width / $orig_width, $max_height / $orig_height);
        $new_width = round($orig_width * $ratio);
        $new_height = round($orig_height * $ratio);
        
        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($image_type === IMAGETYPE_PNG) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
            imagefill($new_image, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $new_image, $source_image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $orig_width, $orig_height
        );
        
        // Save image
        $result = false;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($new_image, $destination, 90);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($new_image, $destination, 8);
                break;
        }
        
        // Free memory
        imagedestroy($source_image);
        imagedestroy($new_image);
        
        return $result;
    }
    
    /**
     * Remove user avatar
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function removeAvatar($user_id) {
        // Get current avatar path
        $query = "SELECT avatar FROM users WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $avatar = $stmt->fetchColumn();
        
        // Delete file if exists
        if ($avatar && $avatar !== '/assets/images/default-avatar.png') {
            $filepath = __DIR__ . '/..' . $avatar;
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
        
        // Update database to default avatar
        $default_avatar = '/assets/images/default-avatar.png';
        $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':avatar', $default_avatar);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
    
    /**
     * Upload gallery photo from base64 data
     * @param int $user_id User ID
     * @param string $base64_data Base64 encoded image data
     * @return bool|string True on success, error message on failure
     */
    public function uploadGalleryPhoto($user_id, $base64_data) {
        // Decode base64 image
        $imageData = $base64_data;
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);
        
        if (!$decodedImage) {
            return 'Invalid image data';
        }
        
        // Validate decoded image size (5MB max)
        if (strlen($decodedImage) > 5 * 1024 * 1024) {
            return 'Image size exceeds 5MB limit';
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/gallery/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'gallery_' . $user_id . '_' . uniqid() . '_' . time() . '.jpg';
        $filepath = $upload_dir . $filename;
        $db_path = '/uploads/gallery/' . $filename;
        
        // Save file
        if (!file_put_contents($filepath, $decodedImage)) {
            return 'Failed to save file';
        }
        
        // Get next display order
        $query = "SELECT COALESCE(MAX(display_order), 0) + 1 as next_order 
                  FROM user_photos WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $next_order = $stmt->fetchColumn();
        
        // Check if this is the first photo (make it primary)
        $query = "SELECT COUNT(*) FROM user_photos WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $photo_count = $stmt->fetchColumn();
        $is_primary = ($photo_count == 0) ? 1 : 0;
        
        // If first photo, also update user avatar
        if ($is_primary) {
            $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':avatar', $db_path);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        }
        
        // Insert into database
        $query = "INSERT INTO user_photos (user_id, file_path, is_primary, display_order, uploaded_at) 
                  VALUES (:user_id, :file_path, :is_primary, :display_order, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':file_path', $db_path);
        $stmt->bindParam(':is_primary', $is_primary);
        $stmt->bindParam(':display_order', $next_order);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return 'Failed to save to database';
    }
    
    /**
     * Delete gallery photo
     * @param int $user_id User ID
     * @param int $photo_id Photo ID
     * @return bool Success status
     */
    public function deleteGalleryPhoto($user_id, $photo_id) {
        // Get photo info
        $query = "SELECT file_path, is_primary FROM user_photos 
                  WHERE id = :photo_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            return false;
        }
        
        // Delete file from filesystem
        $filepath = __DIR__ . '/..' . $photo['file_path'];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        
        // Delete from database
        $query = "DELETE FROM user_photos WHERE id = :photo_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            // If it was primary, set another photo as primary
            if ($photo['is_primary']) {
                $query = "UPDATE user_photos SET is_primary = 1 
                          WHERE user_id = :user_id 
                          ORDER BY display_order ASC LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                
                // Update user avatar to new primary photo
                $query = "SELECT file_path FROM user_photos 
                          WHERE user_id = :user_id AND is_primary = 1 LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $new_primary = $stmt->fetchColumn();
                
                if ($new_primary) {
                    $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':avatar', $new_primary);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                } else {
                    // No photos left, set default avatar
                    $default_avatar = '/assets/images/default-avatar.png';
                    $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':avatar', $default_avatar);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Set primary photo
     * @param int $user_id User ID
     * @param int $photo_id Photo ID
     * @return bool Success status
     */
    public function setPrimaryPhoto($user_id, $photo_id) {
        // Verify photo belongs to user
        $query = "SELECT file_path FROM user_photos WHERE id = :photo_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            return false;
        }
        
        // Remove primary flag from all photos
        $query = "UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Set new primary photo
        $query = "UPDATE user_photos SET is_primary = 1 
                  WHERE id = :photo_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            // Update user avatar to match primary photo
            $query = "UPDATE users SET avatar = :avatar WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':avatar', $photo['file_path']);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all user photos
     * @param int $user_id User ID
     * @return array Array of photo records
     */
    public function getUserPhotos($user_id) {
        $query = "SELECT * FROM user_photos 
                  WHERE user_id = :user_id 
                  ORDER BY is_primary DESC, display_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update photo display order
     * @param int $user_id User ID
     * @param array $photo_order Array of photo IDs in new order
     * @return bool Success status
     */
    public function updatePhotoOrder($user_id, $photo_order) {
        $order = 0;
        foreach ($photo_order as $photo_id) {
            $query = "UPDATE user_photos SET display_order = :order 
                      WHERE id = :photo_id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order', $order);
            $stmt->bindParam(':photo_id', $photo_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $order++;
        }
        return true;
    }
    
    /**
     * Get photo count for user
     * @param int $user_id User ID
     * @return int Photo count
     */
    public function getPhotoCount($user_id) {
        $query = "SELECT COUNT(*) FROM user_photos WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Set banner photo
     * @param int $user_id User ID
     * @param int $photo_id Photo ID
     * @return bool Success status
     */
    public function setBannerPhoto($user_id, $photo_id) {
        // Verify photo belongs to user
        $query = "SELECT id FROM user_photos WHERE id = :photo_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            return false;
        }
        
        // Remove banner flag from all photos
        $query = "UPDATE user_photos SET is_banner = 0 WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Set new banner photo
        $query = "UPDATE user_photos SET is_banner = 1 
                  WHERE id = :photo_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':photo_id', $photo_id);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
    }
}
?>
