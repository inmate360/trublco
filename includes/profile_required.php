<?php
/**
 * Profile Completion Check
 * Redirects users to profile setup if profile is incomplete
 */

function checkProfileCompletion($db, $user_id, $redirect_to_setup = true) {
    try {
        $query = "SELECT 
                    username,
                    email,
                    age,
                    gender,
                    location,
                    bio,
                    profile_photo,
                    created_at
                  FROM users 
                  WHERE id = :user_id 
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if(!$user) {
            return [
                'is_complete' => false,
                'is_new_account' => false,
                'missing_fields' => [],
                'completion_percentage' => 0,
                'error' => 'User not found'
            ];
        }
        
        // Check if profile is complete
        $required_fields = [
            'age' => !empty($user['age']) && $user['age'] >= 18,
            'gender' => !empty($user['gender']),
            'location' => !empty($user['location']),
            'bio' => !empty($user['bio']) && strlen($user['bio']) >= 20
        ];
        
        $is_complete = !in_array(false, $required_fields);
        
        // Check if account is new (within 24 hours)
        $account_age = time() - strtotime($user['created_at']);
        $is_new_account = $account_age < 86400; // 24 hours
        
        // If profile incomplete and should redirect
        if(!$is_complete && $is_new_account && $redirect_to_setup) {
            $current_page = basename($_SERVER['PHP_SELF']);
            
            // Don't redirect if already on setup pages or API endpoints
            $allowed_pages = [
                'profile-setup.php',
                'logout.php',
                'settings.php',
                'profile.php'
            ];
            
            $allowed_paths = [
                '/api/',
                '/upload/',
                '/assets/'
            ];
            
            $is_allowed = false;
            
            // Check allowed pages
            foreach($allowed_pages as $page) {
                if($current_page === $page) {
                    $is_allowed = true;
                    break;
                }
            }
            
            // Check allowed paths
            if(!$is_allowed) {
                foreach($allowed_paths as $path) {
                    if(strpos($_SERVER['REQUEST_URI'], $path) !== false) {
                        $is_allowed = true;
                        break;
                    }
                }
            }
            
            if(!$is_allowed) {
                header('Location: /profile-setup.php');
                exit();
            }
        }
        
        return [
            'is_complete' => $is_complete,
            'is_new_account' => $is_new_account,
            'missing_fields' => array_keys(array_filter($required_fields, function($v) { return !$v; })),
            'completion_percentage' => round((count(array_filter($required_fields)) / count($required_fields)) * 100)
        ];
        
    } catch(PDOException $e) {
        error_log("Profile completion check error: " . $e->getMessage());
        return [
            'is_complete' => false,
            'is_new_account' => false,
            'missing_fields' => [],
            'completion_percentage' => 0,
            'error' => 'Database error'
        ];
    }
}

function requireCompleteProfile($db, $user_id) {
    $profile_check = checkProfileCompletion($db, $user_id, false);
    
    // Only redirect if incomplete and new account
    if(isset($profile_check['is_complete']) && 
       !$profile_check['is_complete'] && 
       isset($profile_check['is_new_account']) && 
       $profile_check['is_new_account']) {
        
        // Check if not already on profile setup
        $current_page = basename($_SERVER['PHP_SELF']);
        if($current_page !== 'profile-setup.php' && 
           strpos($_SERVER['REQUEST_URI'], '/api/') === false &&
           strpos($_SERVER['REQUEST_URI'], '/assets/') === false) {
            header('Location: /profile-setup.php?required=1');
            exit();
        }
    }
    
    return $profile_check;
}
?>