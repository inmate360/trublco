<?php
class SessionSecurity {
    
    public static function init() {
        // Don't start if already started
        if(session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', self::isHTTPS());
        ini_set('session.cookie_samesite', 'Lax');
        
        // Set session name
        session_name('TURNPAGE_SESSION');
        
        // Set session parameters
        session_set_cookie_params([
            'lifetime' => 86400, // 24 hours
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => self::isHTTPS(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
        
        // Validate session
        self::validate();
        
        // Regenerate session ID periodically
        self::regenerate();
    }
    
    private static function validate() {
        // Check if session is hijacked
        if(!isset($_SESSION['USER_AGENT'])) {
            $_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        if($_SESSION['USER_AGENT'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::destroy();
            return false;
        }
        
        // Check IP (optional, can cause issues with mobile networks)
        if(!isset($_SESSION['USER_IP'])) {
            $_SESSION['USER_IP'] = RateLimiter::getClientIP();
        }
        
        // Check session timeout
        if(isset($_SESSION['LAST_ACTIVITY'])) {
            if(time() - $_SESSION['LAST_ACTIVITY'] > 7200) { // 2 hours
                self::destroy();
                return false;
            }
        }
        
        $_SESSION['LAST_ACTIVITY'] = time();
        
        return true;
    }
    
    private static function regenerate() {
        if(!isset($_SESSION['CREATED'])) {
            $_SESSION['CREATED'] = time();
        } else if(time() - $_SESSION['CREATED'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
        }
    }
    
    public static function destroy() {
        $_SESSION = [];
        
        if(isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
    
    private static function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}
?>