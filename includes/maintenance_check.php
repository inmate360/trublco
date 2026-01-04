<?php
function checkMaintenanceMode($db) {
    // Allow CLI/cron access
    if(php_sapi_name() === 'cli') {
        return false;
    }
    
    // Don't block admin pages
    $current_page = basename($_SERVER['PHP_SELF']);
    $admin_pages = ['login.php', 'logout.php', 'maintenance.php'];
    if(in_array($current_page, $admin_pages)) {
        return false;
    }
    
    // Don't block admin directory
    if(strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        return false;
    }
    
    try {
        // Get maintenance settings from database
        $query = "SELECT setting_key, setting_value FROM site_settings 
                  WHERE setting_key IN ('maintenance_mode', 'coming_soon_mode', 'allow_admin_access')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $maintenance_mode = isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == '1';
        $coming_soon_mode = isset($settings['coming_soon_mode']) && $settings['coming_soon_mode'] == '1';
        $allow_admin = isset($settings['allow_admin_access']) ? $settings['allow_admin_access'] == '1' : true;
        
        // Check if current user is admin
        $is_admin = false;
        if(isset($_SESSION['user_id'])) {
            $query = "SELECT is_admin FROM users WHERE id = :user_id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch();
            $is_admin = $user && $user['is_admin'];
        }
        
        // Allow admin access if enabled
        if($allow_admin && $is_admin) {
            return false;
        }
        
        // Check if maintenance or coming soon is active
        return $maintenance_mode || $coming_soon_mode;
        
    } catch(PDOException $e) {
        error_log("Maintenance check error: " . $e->getMessage());
        return false; // Don't block on error
    }
}

function getMaintenanceSettings($db) {
    $query = "SELECT setting_key, setting_value FROM site_settings 
              WHERE setting_key IN ('maintenance_mode', 'maintenance_message', 'maintenance_title', 
                                    'coming_soon_mode', 'coming_soon_message', 'coming_soon_launch_date')";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch(PDOException $e) {
        error_log("Get maintenance settings error: " . $e->getMessage());
        return [];
    }
}
?>