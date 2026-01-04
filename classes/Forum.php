<?php
class Forum {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Get all active categories
    public function getCategories() {
        $query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM forum_threads WHERE category_id = c.id AND is_deleted = FALSE) as thread_count
                  FROM forum_categories c 
                  WHERE is_active = TRUE 
                  ORDER BY display_order ASC, name ASC";
        return $this->db->query($query)->fetchAll();
    }
    
    // Get category by slug
    public function getCategoryBySlug($slug) {
        $query = "SELECT * FROM forum_categories WHERE slug = :slug AND is_active = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }
    
    // Get threads for a category
    public function getThreads($category_id, $page = 1, $per_page = 20, $sort = 'latest') {
        $offset = ($page - 1) * $per_page;
        
        $order_by = match($sort) {
            'popular' => 't.views_count DESC',
            'replies' => 't.replies_count DESC',
            default => 't.is_pinned DESC, t.updated_at DESC'
        };
        
        $query = "SELECT t.*, u.username, u.role as user_role,
                  (SELECT COUNT(*) FROM forum_posts WHERE thread_id = t.id AND is_deleted = FALSE) as replies_count
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  WHERE t.category_id = :category_id AND t.is_deleted = FALSE
                  ORDER BY {$order_by}
                  LIMIT :offset, :per_page";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    // Get thread count for category
    public function getThreadCount($category_id) {
        $query = "SELECT COUNT(*) FROM forum_threads WHERE category_id = :category_id AND is_deleted = FALSE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':category_id' => $category_id]);
        return $stmt->fetchColumn();
    }
    
    // Get thread by slug
    public function getThreadBySlug($slug) {
        $query = "SELECT t.*, u.username, u.role as user_role,
                  c.name as category_name, c.slug as category_slug, c.color as category_color,
                  (SELECT COUNT(*) FROM forum_posts WHERE thread_id = t.id AND is_deleted = FALSE) as replies_count
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN forum_categories c ON t.category_id = c.id
                  WHERE t.slug = :slug AND t.is_deleted = FALSE";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }
    
    // Get posts for a thread
    public function getPosts($thread_id, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT p.*, u.username, u.role as user_role
                  FROM forum_posts p
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.thread_id = :thread_id AND p.is_deleted = FALSE
                  ORDER BY p.created_at ASC
                  LIMIT :offset, :per_page";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':thread_id', $thread_id, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    // Create new thread
    public function createThread($user_id, $category_id, $title, $content) {
        try {
            // Create slug
            $slug = $this->createSlug($title);
            
            // Check for duplicate slug
            $check = $this->db->prepare("SELECT id FROM forum_threads WHERE slug = :slug");
            $check->execute([':slug' => $slug]);
            if($check->fetch()) {
                $slug = $slug . '-' . time();
            }
            
            $query = "INSERT INTO forum_threads (user_id, category_id, title, slug, content) 
                      VALUES (:user_id, :category_id, :title, :slug, :content)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $user_id,
                ':category_id' => $category_id,
                ':title' => $title,
                ':slug' => $slug,
                ':content' => $content
            ]);
            
            return [
                'success' => true,
                'thread_id' => $this->db->lastInsertId(),
                'slug' => $slug
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to create thread: ' . $e->getMessage()
            ];
        }
    }
    
    // Create new post/reply
    public function createPost($user_id, $thread_id, $content) {
        try {
            $query = "INSERT INTO forum_posts (user_id, thread_id, content) 
                      VALUES (:user_id, :thread_id, :content)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $user_id,
                ':thread_id' => $thread_id,
                ':content' => $content
            ]);
            
            // Update thread's updated_at
            $update = "UPDATE forum_threads SET updated_at = NOW() WHERE id = :thread_id";
            $stmt2 = $this->db->prepare($update);
            $stmt2->execute([':thread_id' => $thread_id]);
            
            return [
                'success' => true,
                'post_id' => $this->db->lastInsertId()
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to post reply: ' . $e->getMessage()
            ];
        }
    }
    
    // Increment thread views
    public function incrementViews($thread_id) {
        $query = "UPDATE forum_threads SET views_count = views_count + 1 WHERE id = :thread_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':thread_id' => $thread_id]);
    }
    
    // Search threads
    public function searchThreads($search_query, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        $search_param = '%' . $search_query . '%';
        
        $query = "SELECT t.*, u.username, c.name as category_name, c.slug as category_slug, c.color,
                  (SELECT COUNT(*) FROM forum_posts WHERE thread_id = t.id AND is_deleted = FALSE) as replies_count
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN forum_categories c ON t.category_id = c.id
                  WHERE (t.title LIKE :search OR t.content LIKE :search)
                  AND t.is_deleted = FALSE
                  ORDER BY t.created_at DESC
                  LIMIT :offset, :per_page";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':search', $search_param);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    // ==================== ADMIN METHODS ====================
    
    // Admin: Pin/Unpin thread
    public function togglePin($thread_id, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_threads SET is_pinned = NOT is_pinned WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $thread_id]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Admin: Lock/Unlock thread
    public function toggleLock($thread_id, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_threads SET is_locked = NOT is_locked WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $thread_id]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Admin: Delete thread (soft delete)
    public function deleteThread($thread_id, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_threads SET is_deleted = TRUE WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $thread_id]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Admin: Delete post (soft delete)
    public function deletePost($post_id, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_posts SET is_deleted = TRUE WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $post_id]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Admin: Move thread to different category
    public function moveThread($thread_id, $new_category_id, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_threads SET category_id = :category_id WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':category_id' => $new_category_id,
                ':id' => $thread_id
            ]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Admin: Edit thread
    public function editThread($thread_id, $title, $content, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_threads SET title = :title, content = :content, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':id' => $thread_id
            ]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Admin: Edit post
    public function editPost($post_id, $content, $user_role) {
        if(!in_array($user_role, ['admin', 'moderator'])) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        
        try {
            $query = "UPDATE forum_posts SET content = :content, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':content' => $content,
                ':id' => $post_id
            ]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Get all threads for admin (including deleted)
    public function getAllThreadsAdmin($page = 1, $per_page = 50) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT t.*, u.username, c.name as category_name, c.slug as category_slug,
                  (SELECT COUNT(*) FROM forum_posts WHERE thread_id = t.id) as total_posts
                  FROM forum_threads t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN forum_categories c ON t.category_id = c.id
                  ORDER BY t.created_at DESC
                  LIMIT :offset, :per_page";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    // Helper function to create URL-friendly slug
    private function createSlug($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');
        return substr($text, 0, 100);
    }
}
