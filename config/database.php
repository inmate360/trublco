<?php
/**
 * Database Configuration
 */
class Database {
    private $host = 'ls-c0de346bbcecf4fb03f150cfc282aa30da2c7a66.cyxeec0wo49l.us-east-1.rds.amazonaws.com';
    private $db_name = 'doublelist_clone';
    private $username = 'doublelist_clone';
    private $password = '&x';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
?>
