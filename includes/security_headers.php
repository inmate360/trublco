<?php
class SecurityHeaders {
    
    public static function set() {
        // Prevent clickjacking
        header("X-Frame-Options: SAMEORIGIN");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // XSS Protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Permissions Policy
        header("Permissions-Policy: geolocation=(self), microphone=(), camera=()");
        
        // Content Security Policy
        $csp = self::getCSP();
        header("Content-Security-Policy: " . $csp);
        
        // HSTS (only if HTTPS)
        if(self::isHTTPS()) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
        
        // Remove PHP version header
        header_remove("X-Powered-By");
    }
    
    private static function getCSP() {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            "media-src 'self' data: https:",
            "connect-src 'self' https://api.turnpage.io",
            "frame-src 'self' https://www.google.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "upgrade-insecure-requests"
        ];
        
        return implode("; ", $directives);
    }
    
    private static function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}
?>