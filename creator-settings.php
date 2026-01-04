<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if user is a creator
$query = "SELECT * FROM creator_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$creator_settings = $stmt->fetch();

if(!$creator_settings || !$creator_settings['is_creator']) {
    header('Location: become-creator.php');
    exit();
}

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $creator_name = trim($_POST['creator_name']);
    $subscription_price = floatval($_POST['subscription_price']);
    $allow_tips = isset($_POST['allow_tips']);
    $allow_custom_requests = isset($_POST['allow_custom_requests']);
    $custom_request_price = floatval($_POST['custom_request_price']);
    $welcome_message = trim($_POST['welcome_message']);
    
    try {
        $query = "UPDATE creator_settings SET
            creator_name = :creator_name,
            subscription_price = :subscription_price,
            allow_tips = :allow_tips,
            allow_custom_requests = :allow_custom_requests,
            custom_request_price = :custom_request_price,
            welcome_message = :welcome_message
            WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':creator_name', $creator_name);
        $stmt->bindParam(':subscription_price', $subscription_price);
        $stmt->bindParam(':allow_tips', $allow_tips, PDO::PARAM_BOOL);
        $stmt->bindParam(':allow_custom_requests', $allow_custom_requests, PDO::PARAM_BOOL);
        $stmt->bindParam(':custom_request_price', $custom_request_price);
        $stmt->bindParam(':welcome_message', $welcome_message);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $success = 'Settings updated successfully!';
        $creator_settings = array_merge($creator_settings, $_POST);
        
    } catch(Exception $e) {
        $error = 'Failed to update settings';
    }
}

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.settings-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 20px;
}
</style>

<div class="page-content">
    <div class="settings-container">
        
        <div class="card" style="background: linear-gradient(135deg, #4267F5, #1D9BF0); color: white; margin-bottom: 2rem;">
            <div style="padding: 2rem;">
                <h1 style="margin: 0 0 0.5rem;">⚙️ Creator Settings</h1>
                <p style="opacity: 0.9; margin: 0;">Manage your creator profile and pricing</p>
            </div>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;">
            ✅ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST">
                
                <div class="form-group">
                    <label>Creator Name *</label>
                    <input type="text" 
                           name="creator_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($creator_settings['creator_name']); ?>"
                           required>
                    <small style="color: var(--text-gray);">How you'll be displayed to subscribers</small>
                </div>
                
                <div class="form-group">
                    <label>Monthly Subscription Price *</label>
                    <input type="number" 
                           name="subscription_price" 
                           class="form-control" 
                           min="4.99"
                           step="0.01"
                           value="<?php echo $creator_settings['subscription_price']; ?>"
                           required>
                    <small style="color: var(--text-gray);">Price in USD per month</small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                        <input type="checkbox" 
                               name="allow_tips" 
                               <?php echo $creator_settings['allow_tips'] ? 'checked' : ''; ?>>
                        <span>Allow subscribers to send tips</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                        <input type="checkbox" 
                               name="allow_custom_requests" 
                               id="allowRequests"
                               <?php echo $creator_settings['allow_custom_requests'] ? 'checked' : ''; ?>>
                        <span>Allow custom content requests</span>
                    </label>
                </div>
                
                <div class="form-group" id="requestPriceGroup">
                    <label>Custom Request Price</label>
                    <input type="number" 
                           name="custom_request_price" 
                           class="form-control" 
                           min="0"
                           step="0.01"
                           value="<?php echo $creator_settings['custom_request_price']; ?>">
                    <small style="color: var(--text-gray);">Base price for custom requests</small>
                </div>
                
                <div class="form-group">
                    <label>Welcome Message</label>
                    <textarea name="welcome_message" 
                              class="form-control" 
                              rows="4"
                              placeholder="Welcome message shown to new subscribers..."><?php echo htmlspecialchars($creator_settings['welcome_message'] ?? ''); ?></textarea>
                </div>
                
                <div style="background: rgba(66, 103, 245, 0.1); padding: 1.5rem; border-radius: 15px; margin: 2rem 0;">
                    <h4 style="margin-bottom: 1rem;">💰 Revenue Information</h4>
                    <p style="color: var(--text-gray); line-height: 1.8; margin: 0;">
                        Platform fee: <strong>20%</strong><br>
                        You receive: <strong>80%</strong> of all earnings<br>
                        Withdraw anytime via Bitcoin
                    </p>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <a href="/creator-dashboard.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">💾 Save Settings</button>
                </div>
                
            </form>
        </div>
        
    </div>
</div>

<script>
const requestCheckbox = document.getElementById('allowRequests');
const requestPriceGroup = document.getElementById('requestPriceGroup');

function toggleRequestPrice() {
    requestPriceGroup.style.display = requestCheckbox.checked ? 'block' : 'none';
}

requestCheckbox.addEventListener('change', toggleRequestPrice);
toggleRequestPrice();
</script>

<?php include 'views/footer.php'; ?>