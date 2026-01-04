<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'classes/Favorites.php';

$database = new Database();
$db = $database->getConnection();

$listing_id = (int)($_GET['id'] ?? 0);

if(!$listing_id) {
    header('Location: index.php');
    exit();
}

// Get listing with user details
$query = "SELECT l.*, 
          c.name as category_name,
          ct.name as city_name,
          u.username,
          u.created_at as user_created,
          u.is_online,
          u.last_seen
          FROM listings l
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN users u ON l.user_id = u.id
          WHERE l.id = :listing_id
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':listing_id', $listing_id);
$stmt->execute();
$listing = $stmt->fetch();

if(!$listing) {
    header('Location: index.php');
    exit();
}

// Increment view count
$listingObj = new Listing($db);
$listingObj->incrementViews($listing_id);

// Check if favorited
$is_favorited = false;
if(isset($_SESSION['user_id'])) {
    $favorites = new Favorites($db);
    $is_favorited = $favorites->isFavorited($_SESSION['user_id'], $listing_id);
}

$is_own_listing = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $listing['user_id'];

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.listing-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.listing-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    color: white;
}

.listing-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    font-size: 0.9rem;
    opacity: 0.95;
    margin-top: 1rem;
}

.listing-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
    margin-bottom: 2rem;
}

.listing-main {
    min-width: 0;
}

.listing-image {
    width: 100%;
    height: auto;
    max-height: 600px;
    object-fit: cover;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.listing-body {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.listing-description {
    line-height: 1.8;
    color: var(--text-white);
    white-space: pre-wrap;
    word-wrap: break-word;
}

.listing-sidebar {
    position: sticky;
    top: 80px;
    height: fit-content;
}

.sidebar-card {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.sidebar-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 1rem;
}

.user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 1rem;
    position: relative;
}

.online-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 20px;
    height: 20px;
    background: var(--success-green);
    border: 3px solid var(--card-bg);
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.action-btn {
    width: 100%;
    padding: 0.875rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-message {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.btn-message:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(66, 103, 245, 0.4);
}

.btn-favorite {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    color: var(--text-white);
}

.btn-favorite.favorited {
    background: var(--danger-red);
    border-color: var(--danger-red);
    color: white;
}

.btn-favorite:hover {
    transform: translateY(-2px);
}

.btn-report {
    background: transparent;
    border: 2px solid var(--border-color);
    color: var(--text-gray);
    font-size: 0.9rem;
}

.btn-report:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.listing-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1.5rem;
}

.tag {
    padding: 0.5rem 1rem;
    background: rgba(66, 103, 245, 0.1);
    border: 1px solid rgba(66, 103, 245, 0.3);
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--primary-blue);
}

.warning-banner {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid var(--danger-red);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

/* Mobile Respons
