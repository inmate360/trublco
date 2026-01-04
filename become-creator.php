<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if already a creator
$query = "SELECT creator FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$isCreator = $stmt->fetchColumn();

if($isCreator) {
    header('Location: creator-dashboard.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $content_types = $_POST['content_types'] ?? [];
    $social_links = [
        'instagram' => trim($_POST['instagram'] ?? ''),
        'twitter' => trim($_POST['twitter'] ?? ''),
        'website' => trim($_POST['website'] ?? '')
    ];
    $agree_terms = isset($_POST['agree_terms']);
    
    if(empty($display_name) || empty($bio) || empty($content_types) || !$agree_terms) {
        $error = 'Please fill in all required fields and agree to the terms';
    } else {
        // Create creator application
        $query = "INSERT INTO creator_applications 
                  (user_id, display_name, bio, content_types, social_links, status, applied_at)
                  VALUES (:user_id, :display_name, :bio, :content_types, :social_links, 'pending', NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':display_name', $display_name);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindValue(':content_types', json_encode($content_types));
        $stmt->bindValue(':social_links', json_encode($social_links));
        
        if($stmt->execute()) {
            // Auto-approve for now (you can add manual approval later)
            $updateQuery = "UPDATE users SET creator = 1 WHERE id = :user_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':user_id', $_SESSION['user_id']);
            $updateStmt->execute();
            
            header('Location: creator-dashboard.php?welcome=1');
            exit();
        } else {
            $error = 'Failed to submit application. Please try again.';
        }
    }
}

include 'views/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root {
    --color-canvas-default: #0d1117;
    --color-canvas-subtle: #161b22;
    --color-canvas-overlay: #1c2128;
    --color-border-default: #30363d;
    --color-border-muted: #21262d;
    --color-text-primary: #e6edf3;
    --color-text-secondary: #7d8590;
    --color-text-tertiary: #636e7b;
    --color-accent-emphasis: #1f6feb;
    --color-accent-muted: rgba(31, 111, 235, 0.15);
    --color-success-emphasis: #238636;
    --color-attention-emphasis: #9e6a03;
    --color-danger-emphasis: #da3633;
    --color-done-emphasis: #8957e5;
    --color-shadow: rgba(1, 4, 9, 0.85);
}

body {
    background: var(--color-canvas-default);
    color: var(--color-text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
}

.creator-page {
    min-height: 100vh;
    padding: 2rem 0 4rem 0;
}

.creator-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.hero-section {
    background: linear-gradient(135deg, var(--color-done-emphasis) 0%, var(--color-accent-emphasis) 100%);
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px var(--color-shadow);
}

.hero-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.hero-section h1 {
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.hero-section p {
    color: rgba(255,255,255,0.9);
    font-size: 1.15rem;
    max-width: 600px;
    margin: 0 auto;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.benefit-card {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s;
}

.benefit-card:hover {
    border-color: var(--color-accent-emphasis);
    transform: translateY(-4px);
    box-shadow: 0 12px 32px var(--color-shadow);
}

.benefit-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: block;
}

.benefit-card h3 {
    color: var(--color-text-primary);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.benefit-card p {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
    margin: 0;
}

.application-form {
    background: var(--color-canvas-subtle);
    border: 1px solid var(--color-border-default);
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 8px 24px var(--color-shadow);
}

.form-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    color: var(--color-text-secondary);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-label.required::after {
    content: '*';
    color: var(--color-danger-emphasis);
    margin-left: 0.25rem;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--color-canvas-default);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    color: var(--color-text-primary);
    font-size: 0.875rem;
    transition: all 0.2s;
    font-family: inherit;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--color-accent-emphasis);
    box-shadow: 0 0 0 3px var(--color-accent-muted);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-item label {
    color: var(--color-text-secondary);
    cursor: pointer;
    font-size: 0.875rem;
}

.terms-box {
    background: var(--color-canvas-overlay);
    border: 1px solid var(--color-border-default);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.terms-checkbox {
    display: flex;
    align-items: start;
    gap: 0.75rem;
}

.terms-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 0.25rem;
    cursor: pointer;
}

.terms-checkbox label {
    color: var(--color-text-secondary);
    font-size: 0.875rem;
    cursor: pointer;
}

.terms-checkbox a {
    color: var(--color-accent-emphasis);
    text-decoration: none;
}

.terms-checkbox a:hover {
    text-decoration: underline;
}

.btn-submit {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--color-done-emphasis), var(--color-accent-emphasis));
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(137, 87, 229, 0.4);
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: rgba(35, 134, 54, 0.15);
    border: 1px solid var(--color-success-emphasis);
    color: var(--color-success-emphasis);
}

.alert-danger {
    background: rgba(218, 54, 51, 0.15);
    border: 1px solid var(--color-danger-emphasis);
    color: var(--color-danger-emphasis);
}

.form-help {
    font-size: 0.8rem;
    color: var(--color-text-tertiary);
    margin-top: 0.375rem;
}
</style>

<div class="creator-page">
    <div class="creator-container">
        <div class="hero-section">
            <div class="hero-icon">🚀</div>
            <h1>Become a Creator</h1>
            <p>Join our creator community and start monetizing your exclusive content. Earn money doing what you love while building your fanbase.</p>
        </div>

        <div class="benefits-grid">
            <div class="benefit-card">
                <span class="benefit-icon">💰</span>
                <h3>Earn Money</h3>
                <p>Set your own prices and keep 85% of every sale</p>
            </div>
            <div class="benefit-card">
                <span class="benefit-icon">📊</span>
                <h3>Analytics</h3>
                <p>Track your performance with detailed insights</p>
            </div>
            <div class="benefit-card">
                <span class="benefit-icon">🎯</span>
                <h3>Full Control</h3>
                <p>Manage your content and pricing your way</p>
            </div>
            <div class="benefit-card">
                <span class="benefit-icon">🌟</span>
                <h3>Get Verified</h3>
                <p>Stand out with a verified creator badge</p>
            </div>
        </div>

        <div class="application-form">
            <h2 class="form-title">
                <i class="bi bi-pencil-square"></i>
                Creator Application
            </h2>

            <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label required">Creator Display Name</label>
                    <input type="text" name="display_name" class="form-input" 
                           placeholder="Your creator name" required>
                    <div class="form-help">This is how you'll appear to buyers</div>
                </div>

                <div class="form-group">
                    <label class="form-label required">About You</label>
                    <textarea name="bio" class="form-textarea" 
                              placeholder="Tell us about yourself and the content you plan to create..." required></textarea>
                    <div class="form-help">Minimum 100 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label required">Content Types</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="content_types[]" value="photos" id="ct_photos">
                            <label for="ct_photos">Photos</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="content_types[]" value="videos" id="ct_videos">
                            <label for="ct_videos">Videos</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="content_types[]" value="audio" id="ct_audio">
                            <label for="ct_audio">Audio</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="content_types[]" value="documents" id="ct_docs">
                            <label for="ct_documents">Documents</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Instagram</label>
                    <input type="text" name="instagram" class="form-input" 
                           placeholder="@yourusername">
                </div>

                <div class="form-group">
                    <label class="form-label">Twitter/X</label>
                    <input type="text" name="twitter" class="form-input" 
                           placeholder="@yourusername">
                </div>

                <div class="form-group">
                    <label class="form-label">Website</label>
                    <input type="url" name="website" class="form-input" 
                           placeholder="https://yourwebsite.com">
                </div>

                <div class="terms-box">
                    <div class="terms-checkbox">
                        <input type="checkbox" name="agree_terms" id="agree_terms" required>
                        <label for="agree_terms">
                            I agree to the <a href="creator-terms.php" target="_blank">Creator Terms & Conditions</a> 
                            and confirm that I have the rights to sell the content I upload.
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-rocket-takeoff-fill"></i>
                    Submit Application
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
