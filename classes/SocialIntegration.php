<?php
/**
 * SocialIntegration Class - Social media authentication and integration
 */
class SocialIntegration {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Connect social account
    public function connectAccount($user_id, $provider, $provider_user_id, $access_token, $profile_data) {
        $query = "INSERT INTO social_connections 
                  (user_id, provider, provider_user_id, access_token, profile_data)
                  VALUES (:user_id, :provider, :provider_id, :token, :profile_data)
                  ON DUPLICATE KEY UPDATE
                  access_token = :token2, profile_data = :profile_data2, last_sync = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        $profile_json = json_encode($profile_data);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':provider', $provider);
        $stmt->bindParam(':provider_id', $provider_user_id);
        $stmt->bindParam(':token', $access_token);
        $stmt->bindParam(':profile_data', $profile_json);
        $stmt->bindParam(':token2', $access_token);
        $stmt->bindParam(':profile_data2', $profile_json);
        
        return $stmt->execute();
    }

    // Disconnect social account
    public function disconnectAccount($user_id, $provider) {
        $query = "DELETE FROM social_connections WHERE user_id = :user_id AND provider = :provider";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':provider', $provider);
        
        return $stmt->execute();
    }

    // Get connected accounts
    public function getConnectedAccounts($user_id) {
        $query = "SELECT provider, profile_url, connected_at FROM social_connections WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Check if provider is connected
    public function isConnected($user_id, $provider) {
        $query = "SELECT id FROM social_connections WHERE user_id = :user_id AND provider = :provider LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':provider', $provider);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Find or create user by social provider
    public function findOrCreateUser($provider, $provider_user_id, $email, $name) {
        // Check if social connection exists
        $query = "SELECT user_id FROM social_connections 
                  WHERE provider = :provider AND provider_user_id = :provider_id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':provider', $provider);
        $stmt->bindParam(':provider_id', $provider_user_id);
        $stmt->execute();
        $connection = $stmt->fetch();
        
        if($connection) {
            return $connection['user_id'];
        }
        
        // Check if user exists by email
        $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if($user) {
            return $user['id'];
        }
        
        // Create new user
        $username = $this->generateUniqueUsername($name);
        $random_password = bin2hex(random_bytes(16));
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, email, password, verified) 
                  VALUES (:username, :email, :password, TRUE)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    // Generate unique username
    private function generateUniqueUsername($name) {
        $base_username = strtolower(str_replace(' ', '_', $name));
        $username = $base_username;
        $counter = 1;
        
        while($this->usernameExists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        return $username;
    }

    // Check if username exists
    private function usernameExists($username) {
        $query = "SELECT id FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Google OAuth login URL (example)
    public function getGoogleAuthUrl($redirect_uri) {
        $client_id = 'YOUR_GOOGLE_CLIENT_ID'; // Set in config
        $scope = 'email profile';
        
        return "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline'
        ]);
    }

    // Facebook OAuth login URL (example)
    public function getFacebookAuthUrl($redirect_uri) {
        $app_id = 'YOUR_FACEBOOK_APP_ID'; // Set in config
        $scope = 'email,public_profile';
        
        return "https://www.facebook.com/v12.0/dialog/oauth?" . http_build_query([
            'client_id' => $app_id,
            'redirect_uri' => $redirect_uri,
            'scope' => $scope,
            'response_type' => 'code'
        ]);
    }
}
?>