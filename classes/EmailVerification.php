<?php
class EmailVerification {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createTable();
    }
    
    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            verified_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user (user_id)
        )";
        
        try {
            $this->db->exec($query);
            
            // Add email_verified column to users table
            $this->db->exec("ALTER TABLE users 
                           ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE AFTER email");
            $this->db->exec("ALTER TABLE users 
                           ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL AFTER email_verified");
        } catch(PDOException $e) {
            error_log("Error creating email verification table: " . $e->getMessage());
        }
    }
    
    public function sendVerification($user_id, $email) {
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        
        // Delete old tokens for this user
        $query = "DELETE FROM email_verifications WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Insert new token
        $query = "INSERT INTO email_verifications (user_id, email, token, expires_at) 
                  VALUES (:user_id, :email, :token, :expires_at)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        if($stmt->execute()) {
            // Send email
            return $this->sendEmail($email, $token);
        }
        
        return false;
    }
    
    private function sendEmail($email, $token) {
        $verifyUrl = "https://turnpage.io/verify-email.php?token=" . $token;
        
        $subject = "Verify Your Email - Turnpage";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #0A0F1E; color: #ffffff; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #4267F5, #1D9BF0); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #141B2E; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #4267F5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; color: #6B7280; margin-top: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; color: white;'>Welcome to Turnpage!</h1>
                </div>
                <div class='content'>
                    <h2>Verify Your Email Address</h2>
                    <p>Thank you for registering with Turnpage. To complete your registration, please verify your email address by clicking the button below:</p>
                    <div style='text-align: center;'>
                        <a href='{$verifyUrl}' class='button'>Verify Email Address</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #4267F5;'>{$verifyUrl}</p>
                    <p style='margin-top: 30px; color: #9CA3AF;'><strong>Note:</strong> This link will expire in 24 hours.</p>
                    <p style='color: #9CA3AF; font-size: 14px;'>If you didn't create an account with Turnpage, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>© 2025 Turnpage. All rights reserved.</p>
                    <p>2261 Market Street #4626, San Francisco, CA 94114</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: Turnpage <noreply@turnpage.io>',
            'Reply-To: support@turnpage.io',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
    public function verifyToken($token) {
        $query = "SELECT ev.*, u.email as user_email 
                  FROM email_verifications ev
                  LEFT JOIN users u ON ev.user_id = u.id
                  WHERE ev.token = :token 
                  AND ev.verified_at IS NULL 
                  AND ev.expires_at > NOW()
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $verification = $stmt->fetch();
        
        if(!$verification) {
            return ['success' => false, 'message' => 'Invalid or expired verification link'];
        }
        
        // Mark as verified
        $query = "UPDATE email_verifications 
                  SET verified_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $verification['id']);
        $stmt->execute();
        
        // Update user
        $query = "UPDATE users 
                  SET email_verified = TRUE, email_verified_at = NOW() 
                  WHERE id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $verification['user_id']);
        $stmt->execute();
        
        return ['success' => true, 'user_id' => $verification['user_id']];
    }
    
    public function isVerified($user_id) {
        $query = "SELECT email_verified FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $user = $stmt->fetch();
        return $user && $user['email_verified'];
    }
}
?>