<?php

/**
 * Category Class - Handles listing categories with caching and error handling
 * 
 * @author Your Name
 * @version 2.0
 */
class Category
{
    private PDO $conn;
    private string $table = 'categories';
    private ?array $cache = null;
    private int $cacheExpiry = 3600; // 1 hour

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Get all categories with caching
     * 
     * @param bool $forceRefresh Force cache refresh
     * @return array Array of category objects
     * @throws RuntimeException If database query fails
     */
    public function getAll(bool $forceRefresh = false): array
    {
        // Check cache first
        if (!$forceRefresh && $this->cache !== null) {
            return $this->cache;
        }

        try {
            $query = "SELECT * FROM {$this->table} WHERE is_active = TRUE ORDER BY display_order ASC, name ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $this->cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->cache;
        } catch (PDOException $e) {
            error_log("Category::getAll error: " . $e->getMessage());
            throw new RuntimeException("Failed to fetch categories", 0, $e);
        }
    }

    /**
     * Get category by slug
     * 
     * @param string $slug Category slug
     * @return array|null Category data or null if not found
     * @throws InvalidArgumentException If slug is empty
     */
    public function getBySlug(string $slug): ?array
    {
        if (empty(trim($slug))) {
            throw new InvalidArgumentException("Slug cannot be empty");
        }

        try {
            $query = "SELECT * FROM {$this->table} WHERE slug = :slug AND is_active = TRUE LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Category::getBySlug error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get category by ID
     * 
     * @param int $id Category ID
     * @return array|null Category data or null if not found
     * @throws InvalidArgumentException If ID is invalid
     */
    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException("Invalid category ID");
        }

        try {
            $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Category::getById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get categories with listing counts
     * 
     * @param int|null $cityId Optional city filter
     * @return array Categories with listing counts
     */
    public function getWithCounts(?int $cityId = null): array
    {
        try {
            $cityFilter = $cityId ? "AND l.city_id = :city_id" : "";

            $query = "SELECT c.*, COUNT(l.id) as listing_count
                      FROM {$this->table} c
                      LEFT JOIN listings l ON c.id = l.category_id AND l.status = 'active' {$cityFilter}
                      WHERE c.is_active = TRUE
                      GROUP BY c.id
                      ORDER BY c.display_order ASC, c.name ASC";

            $stmt = $this->conn->prepare($query);

            if ($cityId) {
                $stmt->bindParam(':city_id', $cityId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Category::getWithCounts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }

    /**
     * Check if category exists
     * 
     * @param int $id Category ID
     * @return bool True if exists
     */
    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }
}
