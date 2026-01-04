<?php
/**
 * MembershipPlan Class - Handles membership plans
 */
class MembershipPlan {
    private $conn;
    private $table = 'membership_plans';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all active plans
    public function getAllActive() {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE is_active = TRUE 
                  ORDER BY display_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Get plan by slug
    public function getBySlug($slug) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE slug = :slug AND is_active = TRUE 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Get plan by ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Get free plan
    public function getFreePlan() {
        return $this->getBySlug('free');
    }
}
?>