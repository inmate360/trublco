<?php
class ThemeSwitcher {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get user's theme preference
     */
    public function getUserTheme($user_id) {
        $query = "SELECT theme_preference FROM users WHERE id = :user_id LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['theme_preference'] ?? 'dark';
        } catch(PDOException $e) {
            return 'dark';
        }
    }
    
    /**
     * Update user's theme preference
     */
    public function updateTheme($user_id, $theme) {
        $allowed_themes = ['dark', 'light', 'auto'];
        
        if(!in_array($theme, $allowed_themes)) {
            return ['success' => false, 'error' => 'Invalid theme'];
        }
        
        $query = "UPDATE users SET theme_preference = :theme WHERE id = :user_id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':theme', $theme);
            $stmt->bindParam(':user_id', $user_id);
            
            if($stmt->execute()) {
                return ['success' => true];
            }
            
            return ['success' => false, 'error' => 'Failed to update theme'];
        } catch(PDOException $e) {
            error_log("Error updating theme: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}
?>