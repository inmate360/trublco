<?php
class CSRF {
    /**
     * Generate a CSRF token
     */
    public static function generateToken() {
        if(!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate a CSRF token
     */
    public static function validateToken($token) {
        if(!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Destroy CSRF token
     */
    public static function destroyToken() {
        if(isset($_SESSION['csrf_token'])) {
            unset($_SESSION['csrf_token']);
        }
    }
    
    /**
     * Regenerate CSRF token
     */
    public static function regenerateToken() {
        self::destroyToken();
        return self::generateToken();
    }
    
    /**
     * Get hidden input field with CSRF token
     */
    public static function getHiddenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get CSRF token for AJAX requests
     */
    public static function getToken() {
        return self::generateToken();
    }
}
?>