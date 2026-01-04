<?php
/**
 * Get Cities API
 * Returns cities for a given state ID as JSON
 * Used by post-ad.php for dynamic city loading
 */

header('Content-Type: application/json');

// Allow CORS if needed
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get state_id from query parameter
    $state_id = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;

    if(!$state_id) {
        echo json_encode([]);
        exit();
    }

    // Get cities for this state
    $query = "SELECT id, name, slug FROM cities WHERE state_id = :state_id ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':state_id', $state_id, PDO::PARAM_INT);
    $stmt->execute();

    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($cities);

} catch(PDOException $e) {
    error_log("Error fetching cities: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to load cities',
        'message' => $e->getMessage()
    ]);
}
?>
