<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Moderator.php';

if(!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$moderator = new Moderator($db);

$mod_data = $moderator->isModerator($_SESSION['user_id']);
if(!$mod_data) {
    header('Location: ../index.php');
    exit();
}

// Get report details
$query = "SELECT r.*, l.title, l.description, l.user_id as listing_owner_id,
                 u.username as reporter_name, u.email as reporter_email,
                 owner.username as listing_owner_name
          FROM reports r
          LEFT JOIN listings l ON r.listing_id = l.id
          LEFT JOIN users u ON r.reporter_id = u.id
          LEFT JOIN users owner ON l.user_id = owner.id
          WHERE r.id = :report_id
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':report_id', $_GET['id']);
$stmt->execute();
$report = $stmt->fetch();

if(!$report) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $notes = $_POST['notes'];
    
    if($action === 'delete_listing') {
        $moderator->deleteListing($mod_data['id'], $report['listing_id'], $notes);
        $moderator->resolveReport($mod_data['id'], $report['id'], 'approve', $notes);
        $_SESSION['success'] = 'Listing deleted and report resolved';
    } elseif($action === 'ban_user') {
        $ban_type = $_POST['ban_type'];
        $duration = isset($_POST['duration']) ? $_POST['duration'] : 7;
        $moderator->banUser($mod_data['id'], $report['listing_owner_id'], $notes, $ban_type, $duration);
        $moderator->resolveReport($mod_data['id'], $report['id'], 'approve', $notes);
        $_SESSION['success'] = 'User banned and report resolved';
    } elseif($action === 'dismiss') {
        $moderator->resolveReport($mod_data['id'], $report['id'], 'reject', $notes);
        $_SESSION['success'] = 'Report dismissed';
    }
    
    header('Location: dashboard.php');
    exit();
}

include '../views/header.php';
?>

<div class="container" style="margin: 2rem auto;">
    <a href="dashboard.php" class="btn-secondary">← Back to Dashboard</a>
    
    <div class="report-detail">
        <h2>Report Review #<?php echo $report['id']; ?></h2>
        
        <div class="report-info">
            <div class="info-section">
                <h3>Report Information</h3>
                <p><strong>Status:</strong> <?php echo ucfirst($report['status']); ?></p>
                <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></p>
                <p><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporter_name'] ?? 'Anonymous'); ?></p>
                <p><strong>Reason:</strong></p>
                <p class="reason-text"><?php echo nl2br(htmlspecialchars($report['reason'])); ?></p>
            </div>
            
            <div class="info-section">
                <h3>Reported Listing</h3>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($report['title']); ?></p>
                <p><strong>Owner:</strong> <?php echo htmlspecialchars($report['listing_owner_name']); ?></p>
                <p><strong>Description:</strong></p>
                <p class="listing-description"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                <a href="../listing.php?id=<?php echo $report['listing_id']; ?>" target="_blank" class="btn-secondary">View