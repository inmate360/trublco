<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$state_id = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;

if(!$state_id) {
    echo json_encode([]);
    exit;
}

try {
    $query = "SELECT id, name, slug FROM cities WHERE state_id = :state_id ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':state_id', $state_id);
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($cities);
} catch(PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([]);
}
?>
