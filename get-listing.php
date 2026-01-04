<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$listing_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT l.*, c.name as city_name, c.slug as city_slug, 
              s.name as state_name, s.abbreviation as state_abbr,
              cat.name as category_name, 
              u.username, u.is_verified, u.is_premium, u.id as poster_id
              FROM listings l
              LEFT JOIN cities c ON l.city_id = c.id
              LEFT JOIN states s ON c.state_id = s.id
              LEFT JOIN categories cat ON l.category_id = cat.id
              LEFT JOIN users u ON l.user_id = u.id
              WHERE l.id = :id AND l.status = 'active'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $listing_id, PDO::PARAM_INT);
    $stmt->execute();

    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$listing) {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found']);
        exit;
    }

    // Increment view count
    $update_query = "UPDATE listings SET views = views + 1 WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $listing_id, PDO::PARAM_INT);
    $update_stmt->execute();

    // Add user session info
    $listing['is_own_listing'] = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $listing['poster_id'];
    $listing['is_logged_in'] = isset($_SESSION['user_id']);

    echo json_encode($listing);

} catch(PDOException $e) {
    error_log("Error in get-listing.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
