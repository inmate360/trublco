<?php
session_start();
require_once '../config/database.php';
require_once '../classes/LocationService.php';
require_once '../classes/RateLimiter.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$locationService = new LocationService($db);
$rateLimiter = new RateLimiter($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Rate limiting
$rateCheck = $rateLimiter->checkLimit($user_id, 'location_api', 30, 60); // 30 requests per minute
if(!$rateCheck['allowed']) {
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
    exit();
}

switch($action) {
    case 'update_location':
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];
        $auto_detected = isset($_POST['auto_detected']) && $_POST['auto_detected'] === 'true';
        
        $result = $locationService->updateUserLocation($user_id, $latitude, $longitude, $auto_detected);
        echo json_encode($result);
        break;
        
    case 'get_nearby_users':
        $radius = (int)($_GET['radius'] ?? 50);
        $limit = (int)($_GET['limit'] ?? 50);
        
        $result = $locationService->getNearbyUsers($user_id, $radius, $limit);
        echo json_encode($result);
        break;
        
    case 'get_nearby_listings':
        $latitude = (float)$_GET['latitude'];
        $longitude = (float)$_GET['longitude'];
        $radius = (int)($_GET['radius'] ?? 50);
        
        $filters = [
            'category_id' => $_GET['category_id'] ?? null,
            'neighborhood' => $_GET['neighborhood'] ?? null,
            'zip_code' => $_GET['zip_code'] ?? null
        ];
        
        $result = $locationService->getNearbyListings($latitude, $longitude, $radius, $filters);
        echo json_encode($result);
        break;
        
    case 'geocode':
        $address = $_POST['address'] ?? '';
        
        if(empty($address)) {
            echo json_encode(['success' => false, 'error' => 'Address required']);
            exit();
        }
        
        $result = $locationService->geocodeAddress($address);
        echo json_encode($result);
        break;
        
    case 'reverse_geocode':
        $latitude = (float)$_GET['latitude'];
        $longitude = (float)$_GET['longitude'];
        
        $result = $locationService->reverseGeocode($latitude, $longitude);
        echo json_encode($result);
        break;
        
    case 'toggle_distance':
        $show_distance = isset($_POST['show_distance']) && $_POST['show_distance'] === 'true';
        
        $result = $locationService->toggleDistanceVisibility($user_id, $show_distance);
        echo json_encode(['success' => $result]);
        break;
        
    case 'get_neighborhoods':
        $city_id = (int)$_GET['city_id'];
        
        $result = $locationService->getNeighborhoods($city_id);
        echo json_encode($result);
        break;
        
    case 'create_travel_listing':
        $listing_id = (int)$_POST['listing_id'];
        $city_ids = json_decode($_POST['city_ids'], true);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        
        if(empty($city_ids) || empty($start_date) || empty($end_date)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit();
        }
        
        $result = $locationService->createTravelListing($listing_id, $city_ids, $start_date, $end_date);
        echo json_encode($result);
        break;
        
    case 'get_travel_listings':
        $city_id = (int)$_GET['city_id'];
        
        $result = $locationService->getTravelListings($city_id);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>