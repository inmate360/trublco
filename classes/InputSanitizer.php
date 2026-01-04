<?php
class InputSanitizer {
    
    public static function cleanString($input, $maxLength = null) {
        if($input === null) {
            return '';
        }
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Strip tags
        $input = strip_tags($input);
        
        // Convert special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        // Limit length
        if($maxLength !== null && strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }
    
    public static function cleanEmail($email) {
        $email = trim(strtolower($email));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check for disposable email domains
        if(self::isDisposableEmail($email)) {
            return false;
        }
        
        return $email;
    }
    
    public static function cleanUsername($username) {
        // Remove whitespace
        $username = trim($username);
        
        // Remove special characters except underscore and hyphen
        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
        
        // Limit length
        $username = substr($username, 0, 30);
        
        // Check for reserved words
        $reserved = ['admin', 'moderator', 'system', 'turnpage', 'support', 'help'];
        if(in_array(strtolower($username), $reserved)) {
            return false;
        }
        
        return $username;
    }
    
    public static function cleanHTML($html, $allowedTags = []) {
        if(empty($allowedTags)) {
            return strip_tags($html);
        }
        
        // Create allowed tags string
        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
        
        // Strip tags
        $html = strip_tags($html, $allowedTagsString);
        
        // Remove dangerous attributes
        $html = preg_replace('/<(\w+)[^>]*?(javascript:|on\w+\s*=)[^>]*?>/i', '', $html);
        
        return $html;
    }
    
    public static function cleanURL($url) {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if(!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Only allow http and https
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if(!in_array($scheme, ['http', 'https'])) {
            return false;
        }
        
        return $url;
    }
    
    public static function cleanPhone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validate length (US phone numbers)
        if(strlen($phone) !== 10 && strlen($phone) !== 11) {
            return false;
        }
        
        return $phone;
    }
    
    public static function cleanInt($value, $min = null, $max = null) {
        $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        $value = (int)$value;
        
        if($min !== null && $value < $min) {
            return $min;
        }
        
        if($max !== null && $value > $max) {
            return $max;
        }
        
        return $value;
    }
    
    public static function cleanFloat($value, $min = null, $max = null) {
        $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $value = (float)$value;
        
        if($min !== null && $value < $min) {
            return $min;
        }
        
        if($max !== null && $value > $max) {
            return $max;
        }
        
        return $value;
    }
    
    public static function cleanArray($array, $callback) {
        if(!is_array($array)) {
            return [];
        }
        
        return array_map($callback, $array);
    }
    
    private static function isDisposableEmail($email) {
        $domain = substr(strrchr($email, "@"), 1);
        
        // List of common disposable email domains
        $disposable = [
            'tempmail.com', '10minutemail.com', 'guerrillamail.com',
            'mailinator.com', 'throwaway.email', 'temp-mail.org',
            'fakeinbox.com', 'trashmail.com', 'getnada.com'
        ];
        
        return in_array($domain, $disposable);
    }
    
    public static function detectXSS($input) {
        $dangerous = [
            '<script', 'javascript:', 'onerror=', 'onload=',
            'onclick=', 'onmouseover=', '<iframe', 'eval(',
            'base64_decode', 'document.cookie'
        ];
        
        $inputLower = strtolower($input);
        
        foreach($dangerous as $pattern) {
            if(strpos($inputLower, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function detectSQLInjection($input) {
        $patterns = [
            "/(union|select|insert|update|delete|drop|create|alter|exec|execute)/i",
            "/(\-\-|;|\/\*|\*\/|'|\")/",
            "/(or|and)\s+\d+\s*=\s*\d+/i"
        ];
        
        foreach($patterns as $pattern) {
            if(preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
}
?>