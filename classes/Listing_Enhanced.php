<?php
declare(strict_types=1);

/**
 * Listing Class - Handles classifieds/personals listings with validation
 * 
 * @version 2.0
 */
class Listing
{
    private PDO $db;

    // Listing statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';

    // Contact methods
    public const CONTACT_MESSAGE = 'message';
    public const CONTACT_EMAIL = 'email';
    public const CONTACT_PHONE = 'phone';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Create a new listing
     * 
     * @param array $data Listing data
     * @return int|false Listing ID or false on failure
     * @throws InvalidArgumentException If required fields are missing
     */
    public function create(array $data)
    {
        $this->validateListingData($data, true);

        try {
            $query = "INSERT INTO listings 
                      (user_id, category_id, city_id, title, description, photo_url, 
                       contact_method, status, is_featured, views, created_at) 
                      VALUES 
                      (:user_id, :category_id, :city_id, :title, :description, :photo_url, 
                       :contact_method, :status, :is_featured, :views, NOW())";

            $stmt = $this->db->prepare($query);

            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':category_id' => $data['category_id'],
                ':city_id' => $data['city_id'],
                ':title' => htmlspecialchars(trim($data['title']), ENT_QUOTES, 'UTF-8'),
                ':description' => htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8'),
                ':photo_url' => $data['photo_url'] ?? null,
                ':contact_method' => $data['contact_method'] ?? self::CONTACT_MESSAGE,
                ':status' => self::STATUS_ACTIVE,
                ':is_featured' => $data['is_featured'] ?? false,
                ':views' => 0
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Listing::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get listing by ID with all related data
     * 
     * @param int $listingId Listing ID
     * @return array|null Listing data or null
     */
    public function getById(int $listingId): ?array
    {
        try {
            $query = "SELECT l.*, 
                      c.name as category_name, c.slug as category_slug,
                      ct.name as city_name, ct.state_id,
                      s.name as state_name, s.abbreviation as state_abbr,
                      u.username, u.created_at as user_member_since, u.verified as user_verified
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN states s ON ct.state_id = s.id
                      LEFT JOIN users u ON l.user_id = u.id
                      WHERE l.id = :id
                      LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $listingId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Listing::getById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update listing
     * 
     * @param int $listingId Listing ID
     * @param int $userId User ID (for ownership verification)
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update(int $listingId, int $userId, array $data): bool
    {
        $this->validateListingData($data, false);

        try {
            // Verify ownership
            if (!$this->isOwner($listingId, $userId)) {
                error_log("Listing::update - User {$userId} is not owner of listing {$listingId}");
                return false;
            }

            $updates = [];
            $params = [':listing_id' => $listingId, ':user_id' => $userId];

            // Build dynamic update query based on provided data
            $allowedFields = ['category_id', 'city_id', 'title', 'description', 'photo_url', 'contact_method'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = :{$field}";

                    if (in_array($field, ['title', 'description'])) {
                        $params[":{$field}"] = htmlspecialchars(trim($data[$field]), ENT_QUOTES, 'UTF-8');
                    } else {
                        $params[":{$field}"] = $data[$field];
                    }
                }
            }

            if (empty($updates)) {
                return false;
            }

            $updates[] = "updated_at = NOW()";
            $query = "UPDATE listings SET " . implode(', ', $updates) . 
                     " WHERE id = :listing_id AND user_id = :user_id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Listing::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete listing (soft delete)
     * 
     * @param int $listingId Listing ID
     * @param int $userId User ID (for ownership verification)
     * @return bool Success status
     */
    public function delete(int $listingId, int $userId): bool
    {
        try {
            // Verify ownership
            if (!$this->isOwner($listingId, $userId)) {
                return false;
            }

            $query = "UPDATE listings 
                      SET is_deleted = TRUE, deleted_at = NOW(), status = :status 
                      WHERE id = :listing_id AND user_id = :user_id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':status' => self::STATUS_INACTIVE,
                ':listing_id' => $listingId,
                ':user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Listing::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's listings
     * 
     * @param int $userId User ID
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array Array of listings
     */
    public function getUserListings(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "SELECT l.*, c.name as category_name, ct.name as city_name
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      WHERE l.user_id = :user_id AND (l.is_deleted IS NULL OR l.is_deleted = FALSE)
                      ORDER BY l.created_at DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Listing::getUserListings error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Increment view count
     * 
     * @param int $listingId Listing ID
     * @return bool Success status
     */
    public function incrementViews(int $listingId): bool
    {
        try {
            $query = "UPDATE listings SET views = views + 1 WHERE id = :listing_id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':listing_id' => $listingId]);
        } catch (PDOException $e) {
            error_log("Listing::incrementViews error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search listings with filters
     * 
     * @param array $filters Search filters
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array Search results
     */
    public function search(array $filters, int $limit = 50, int $offset = 0): array
    {
        try {
            $conditions = ["(l.is_deleted IS NULL OR l.is_deleted = FALSE)", "l.status = :status"];
            $params = [':status' => self::STATUS_ACTIVE];

            // Search term
            if (!empty($filters['search'])) {
                $conditions[] = "(l.title LIKE :search OR l.description LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            // City filter
            if (!empty($filters['city_id'])) {
                $conditions[] = "l.city_id = :city_id";
                $params[':city_id'] = (int)$filters['city_id'];
            }

            // Category filter
            if (!empty($filters['category_id'])) {
                $conditions[] = "l.category_id = :category_id";
                $params[':category_id'] = (int)$filters['category_id'];
            }

            // Date range filter
            if (!empty($filters['date_from'])) {
                $conditions[] = "l.created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $conditions[] = "l.created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $whereClause = "WHERE " . implode(' AND ', $conditions);

            // Order by
            $orderBy = "l.created_at DESC";
            if (!empty($filters['sort'])) {
                $orderBy = match($filters['sort']) {
                    'views' => 'l.views DESC',
                    'title' => 'l.title ASC',
                    'oldest' => 'l.created_at ASC',
                    default => 'l.created_at DESC'
                };
            }

            $query = "SELECT l.*, c.name as category_name, ct.name as city_name, u.username
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN users u ON l.user_id = u.id
                      {$whereClause}
                      ORDER BY {$orderBy}
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Listing::search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get featured listings
     * 
     * @param int|null $cityId Optional city filter
     * @param int $limit Maximum results
     * @return array Featured listings
     */
    public function getFeatured(?int $cityId = null, int $limit = 10): array
    {
        try {
            $conditions = [
                "l.is_featured = TRUE",
                "(l.is_deleted IS NULL OR l.is_deleted = FALSE)",
                "l.status = :status"
            ];

            $params = [':status' => self::STATUS_ACTIVE];

            if ($cityId !== null) {
                $conditions[] = "l.city_id = :city_id";
                $params[':city_id'] = $cityId;
            }

            $whereClause = "WHERE " . implode(' AND ', $conditions);

            $query = "SELECT l.*, c.name as category_name, ct.name as city_name, u.username
                      FROM listings l
                      LEFT JOIN categories c ON l.category_id = c.id
                      LEFT JOIN cities ct ON l.city_id = ct.id
                      LEFT JOIN users u ON l.user_id = u.id
                      {$whereClause}
                      ORDER BY l.created_at DESC
                      LIMIT :limit";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Listing::getFeatured error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user is the owner of a listing
     * 
     * @param int $listingId Listing ID
     * @param int $userId User ID
     * @return bool True if owner
     */
    public function isOwner(int $listingId, int $userId): bool
    {
        try {
            $query = "SELECT id FROM listings WHERE id = :listing_id AND user_id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':listing_id' => $listingId, ':user_id' => $userId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Listing::isOwner error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate listing data
     * 
     * @param array $data Listing data
     * @param bool $isCreate Whether this is for creation (requires all fields)
     * @throws InvalidArgumentException If validation fails
     */
    private function validateListingData(array $data, bool $isCreate): void
    {
        if ($isCreate) {
            $required = ['user_id', 'category_id', 'city_id', 'title', 'description'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Missing required field: {$field}");
                }
            }
        }

        // Validate title
        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (strlen($title) < 3) {
                throw new InvalidArgumentException("Title must be at least 3 characters");
            }
            if (strlen($title) > 200) {
                throw new InvalidArgumentException("Title must not exceed 200 characters");
            }
        }

        // Validate description
        if (isset($data['description'])) {
            $description = trim($data['description']);
            if (strlen($description) < 10) {
                throw new InvalidArgumentException("Description must be at least 10 characters");
            }
            if (strlen($description) > 5000) {
                throw new InvalidArgumentException("Description must not exceed 5000 characters");
            }
        }

        // Validate contact method
        if (isset($data['contact_method'])) {
            $validMethods = [self::CONTACT_MESSAGE, self::CONTACT_EMAIL, self::CONTACT_PHONE];
            if (!in_array($data['contact_method'], $validMethods)) {
                throw new InvalidArgumentException("Invalid contact method");
            }
        }
    }
}
