<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';

if(!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: my-listings.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$listing = new Listing($db);

$listing_id = $_GET['id'];

// Verify ownership
$query = "SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $listing_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if($stmt->rowCount() == 0) {
    $_SESSION['error'] = 'Listing not found or access denied';
    header('Location: my-listings.php');
    exit();
}

// Delete the listing
if($listing->delete($listing_id, $_SESSION['user_id'])) {
    $_SESSION['success'] = 'Listing deleted successfully';
} else {
    $_SESSION['error'] = 'Failed to delete listing';
}

header('Location: my-listings.php');
exit();
?>