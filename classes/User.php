<?php
/**
 * User Class - Handles user authentication and management
 */
class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $email;
    public $password;
    public $username;
    public $phone;
    public $verified;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Register new user
    public function register() {
        $query = "INSERT INTO " . $this->table . " 
                  (email, password, username, phone) 
                  VALUES (:email, :password, :username, :phone)";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash password
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Bind parameters
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':phone', $this->phone);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }

    // Login user
    public function login() {
        $query = "SELECT id, email, password, username, verified 
                  FROM " . $this->table . " 
                  WHERE email = :email 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            
            if(password_verify($this->password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->verified = $row['verified'];
                
                // Update last login
                $this->updateLastLogin();
                
                return true;
            }
        }
        
        return false;
    }

    // Check if email exists
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }


    public function sendPasswordReset($email) {
    try {
        // Check if email exists
        $stmt = $this->conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user_data) {
            return ['success' => true, 'message' => 'If an account exists with that email, instructions have been sent'];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token
        $stmt = $this->conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) 
                                     VALUES (?, ?, ?, NOW()) 
                                     ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()");
        $stmt->execute([$email, $token, $expires, $token, $expires]);
        
        // Create reset link
        $reset_link = 'https://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . $token;
        
        // Send email
        $subject = 'Password Reset Request - Basehit.io';
        $message = "Hello " . htmlspecialchars($user_data['username']) . ",\n\n";
        $message .= "Click the link below to reset your password:\n\n";
        $message .= $reset_link . "\n\n";
        $message .= "This link expires in 1 hour.\n\n";
        $message .= "If you didn't request this, ignore this email.\n\n";
        $message .= "Best regards,\nBasehit.io Team";
        
        $headers = "From: noreply@basehit.io\r\n";
        $headers .= "Reply-To: support@basehit.io\r\n";
        
        if(mail($email, $subject, $message, $headers)) {
            return ['success' => true, 'message' => 'Reset instructions sent'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email'];
        }
    } catch(Exception $e) {
        return ['success' => false, 'message' => 'An error occurred'];
    }
}
    // Update last login
    private function updateLastLogin() {
        $query = "UPDATE " . $this->table . " 
                  SET last_login = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    // Get user by ID
    public function getUserById($id) {
        $query = "SELECT id, email, username, phone, verified, created_at 
                  FROM " . $this->table . " 
                  WHERE id = :id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>