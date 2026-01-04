<?php
class Listing {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create a new listing
     */
    public function create($user_id, $category_id, $city_id, $title, $description, $photo_url = null, $contact_method = 'message') {
        try {
            // Check which columns exist in listings table
            $query = "SHOW COLUMNS FROM listings";
            $stmt = $this->db->query($query);
            $existing_columns = [];
            while($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing_columns[] = $col['Field'];
            }
            
            // Build insert query based on available columns
            $columns = ['user_id', 'category_id', 'city_id', 'title', 'description'];
            $values = [':user_id', ':category_id', ':city_id', ':title', ':description'];
            $params = [
                'user_id' => $user_id,
                'category_id' => $category_id,
                'city_id' => $city_id,
                'title' => $title,
                'description' => $description
            ];
            
            // Add photo_url if column exists and value provided
            if(in_array('photo_url', $existing_columns) && $photo_url) {
                $columns[] = 'photo_url';
                $values[] = ':photo_url';
                $params['photo_url'] = $photo_url;
            }
            
            // Add contact_method if column exists
            if(in_array('contact_method', $existing_columns)) {
                $columns[] = 'contact_method';
                $values[] = ':contact_method';
                $params['contact_method'] = $contact_method;
            }
            
            // Add status if column exists
            if(in_array('status', $existing_columns)) {
                $columns[] = 'status';
                $values[] = ':status';
                $params['status'] = 'active';
            }
            
            // Add is_featured if column exists
            if(in_array('is_featured', $existing_columns)) {
                $columns[] = 'is_featured';
                $values[] = ':is_featured';
                $params['is_featured'] = 0;
            }
            
            // Add views if column exists
            if(in_array('views', $existing_columns)) {
                $columns[] = 'views';
                $values[] = ':views';
                $params['views'] = 0;
            }
            
            $query = "INSERT INTO listings (" . implode(', ', $columns) . ") 
                      VALUES (" . implode(', ', $values) . ")";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            
            if($result) {
                return $this->db->lastInsertId();
            }
            
            return false;
            
        } catch(PDOException $e) {
            error_log("Listing create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get listing by ID
     */
    public function getById($listing_id) {
        try {
            $query = "SELECT l.*, c.name as category_name, ct.name as city_name, u.username
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN users u ON l.user_id = u.id
                      WHERE l.id = :id
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $listing_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Listing getById error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update listing
     */
    public function update($listing_id, $user_id, $category_id, $city_id, $title, $description, $photo_url = null) {
        try {
            $query = "UPDATE listings 
                      SET category_id = :category_id,
                          city_id = :city_id,
                          title = :title,
                          description = :description";
            
            $params = [
                'category_id' => $category_id,
                'city_id' => $city_id,
                'title' => $title,
                'description' => $description,
                'listing_id' => $listing_id,
                'user_id' => $user_id
            ];
            
            if($photo_url) {
                $query .= ", photo_url = :photo_url";
                $params['photo_url'] = $photo_url;
            }
            
            $query .= " WHERE id = :listing_id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
            
        } catch(PDOException $e) {
            error_log("Listing update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete listing (soft delete)
     */
    public function delete($listing_id, $user_id) {
        try {
            // Check if is_deleted column exists
            $query = "SHOW COLUMNS FROM listings LIKE 'is_deleted'";
            $stmt = $this->db->query($query);
            
            if($stmt->rowCount() > 0) {
                // Soft delete
                $query = "UPDATE listings 
                          SET is_deleted = TRUE, deleted_at = NOW() 
                          WHERE id = :listing_id AND user_id = :user_id";
            } else {
                // Hard delete
                $query = "DELETE FROM listings 
                          WHERE id = :listing_id AND user_id = :user_id";
            }
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'listing_id' => $listing_id,
                'user_id' => $user_id
            ]);
            
        } catch(PDOException $e) {
            error_log("Listing delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's listings
     */
    public function getUserListings($user_id, $limit = 50) {
        try {
            // Check if is_deleted column exists
            $query = "SHOW COLUMNS FROM listings LIKE 'is_deleted'";
            $stmt = $this->db->query($query);
            $has_is_deleted = $stmt->rowCount() > 0;
            
            $where = $has_is_deleted ? "WHERE l.user_id = :user_id AND l.is_deleted = FALSE" : "WHERE l.user_id = :user_id";
            
            $query = "SELECT l.*, c.name as category_name, ct.name as city_name
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      $where
                      ORDER BY l.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Listing getUserListings error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Increment views
     */
    public function incrementViews($listing_id) {
        try {
            // Check if views column exists
            $query = "SHOW COLUMNS FROM listings LIKE 'views'";
            $stmt = $this->db->query($query);
            
            if($stmt->rowCount() > 0) {
                $query = "UPDATE listings SET views = views + 1 WHERE id = :listing_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['listing_id' => $listing_id]);
            }
            
            return true;
            
        } catch(PDOException $e) {
            error_log("Listing incrementViews error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search listings
     */
    public function search($search_term, $city_id = null, $category_id = null, $limit = 50) {
        try {
            // Check if is_deleted column exists
            $query = "SHOW COLUMNS FROM listings LIKE 'is_deleted'";
            $stmt = $this->db->query($query);
            $has_is_deleted = $stmt->rowCount() > 0;
            
            $where_conditions = [];
            $params = ['search' => "%$search_term%"];
            
            $where_conditions[] = "(l.title LIKE :search OR l.description LIKE :search)";
            
            if($has_is_deleted) {
                $where_conditions[] = "l.is_deleted = FALSE";
            }
            
            if($city_id) {
                $where_conditions[] = "l.city_id = :city_id";
                $params['city_id'] = $city_id;
            }
            
            if($category_id) {
                $where_conditions[] = "l.category_id = :category_id";
                $params['category_id'] = $category_id;
            }
            
            $where_clause = "WHERE " . implode(' AND ', $where_conditions);
            
            $query = "SELECT l.*, c.name as category_name, ct.name as city_name, u.username
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN users u ON l.user_id = u.id
                      $where_clause
                      ORDER BY l.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Listing search error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get featured listings
     */
    public function getFeatured($city_id = null, $limit = 10) {
        try {
            // Check which columns exist
            $query = "SHOW COLUMNS FROM listings";
            $stmt = $this->db->query($query);
            $existing_columns = [];
            while($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing_columns[] = $col['Field'];
            }
            
            $has_is_deleted = in_array('is_deleted', $existing_columns);
            $has_is_featured = in_array('is_featured', $existing_columns);
            
            if(!$has_is_featured) {
                return []; // Can't get featured if column doesn't exist
            }
            
            $where_conditions = ["l.is_featured = TRUE"];
            
            if($has_is_deleted) {
                $where_conditions[] = "l.is_deleted = FALSE";
            }
            
            if($city_id) {
                $where_conditions[] = "l.city_id = :city_id";
            }
            
            $where_clause = "WHERE " . implode(' AND ', $where_conditions);
            
            $query = "SELECT l.*, c.name as category_name, ct.name as city_name, u.username
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN users u ON l.user_id = u.id
                      $where_clause
                      ORDER BY l.created_at DESC
                      LIMIT :limit";
            
            $stmt = $this->db->prepare($query);
            if($city_id) {
                $stmt->bindParam(':city_id', $city_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Listing getFeatured error: " . $e->getMessage());
            return [];
        }
    }
}
?>