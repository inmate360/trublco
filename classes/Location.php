<?php
/**
 * Location Class - Handles states and cities
 */
class Location {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all active states
    public function getAllStates() {
        $query = "SELECT * FROM states WHERE is_active = TRUE ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Get state by abbreviation
    public function getStateByAbbr($abbr) {
        $query = "SELECT * FROM states WHERE abbreviation = :abbr AND is_active = TRUE LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':abbr', $abbr);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Get cities by state
    public function getCitiesByState($state_id) {
        $query = "SELECT * FROM cities WHERE state_id = :state_id AND is_active = TRUE ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':state_id', $state_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Get city by slug
    public function getCityBySlug($slug) {
        $query = "SELECT c.*, s.name as state_name, s.abbreviation as state_abbr 
                  FROM cities c
                  LEFT JOIN states s ON c.state_id = s.id
                  WHERE c.slug = :slug AND c.is_active = TRUE 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Get city by ID
    public function getCityById($id) {
        $query = "SELECT c.*, s.name as state_name, s.abbreviation as state_abbr 
                  FROM cities c
                  LEFT JOIN states s ON c.state_id = s.id
                  WHERE c.id = :id 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Update post count for city
    public function updatePostCount($city_id) {
        $query = "UPDATE cities 
                  SET post_count = (
                      SELECT COUNT(*) FROM listings 
                      WHERE city_id = :city_id AND status = 'active'
                  )
                  WHERE id = :city_id2";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':city_id', $city_id);
        $stmt->bindParam(':city_id2', $city_id);
        $stmt->execute();
    }

    // Get total locations count
    public function getTotalLocationsCount() {
        $query = "SELECT COUNT(*) as count FROM cities WHERE is_active = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'];
    }
}
?>