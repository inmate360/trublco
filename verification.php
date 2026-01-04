<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Check if user already has a pending or approved verification
$stmt = $pdo->prepare("SELECT * FROM profile_verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$existing_verification = $stmt->fetch();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['verification_video'])) {
    
    // Check if already verified
    if ($existing_verification && $existing_verification['status'] === 'approved') {
        $error = 'Your profile is already verified.';
    } elseif ($existing_verification && $existing_verification['status'] === 'pending') {
        $error = 'You already have a pending verification request.';
    } else {
        $video = $_FILES['verification_video'];
        
        // Validate video
        $allowed_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
        $max_size = 50 * 1024 * 1024; // 50MB
        
        if ($video['error'] !== UPLOAD_ERR_OK) {
            $error = 'Error uploading video. Please try again.';
        } elseif (!in_array($video['type'], $allowed_types)) {
            $error = 'Invalid video format. Please upload MP4, MOV, AVI, or WEBM.';
        } elseif ($video['size'] > $max_size) {
            $error = 'Video file is too large. Maximum size is 50MB.';
        } else {
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/verifications/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($video['name'], PATHINFO_EXTENSION);
            $filename = $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($video['tmp_name'], $filepath)) {
                // Insert verification request
                $stmt = $pdo->prepare("INSERT INTO profile_verifications (user_id, video_path, status, submitted_at) VALUES (?, ?, 'pending', NOW())");
                $stmt->execute([$user_id, $filepath]);
                
                $message = 'Verification video submitted successfully! Our team will review it within 24-48 hours.';
                
                // Refresh verification status
                $stmt = $pdo->prepare("SELECT * FROM profile_verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
                $stmt->execute([$user_id]);
                $existing_verification = $stmt->fetch();
            } else {
                $error = 'Failed to upload video. Please try again.';
            }
        }
    }
}

include 'views/header.php';
?>

<!-- Hero Section -->
<div class="relative bg-gradient-to-br from-purple-600 via-pink-500 to-red-500 text-white py-16">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
            <i class="bi bi-shield-check text-3xl"></i>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Profile Verification</h1>
        <p class="text-lg text-white/90">Verify your identity and build trust with the trubl community</p>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-4xl mx-auto px-4 py-12">
    
    <?php if ($message): ?>
    <div class="bg-gh-success/10 border border-gh-success text-gh-success px-6 py-4 rounded-lg mb-6 flex items-start">
        <i class="bi bi-check-circle-fill text-xl mr-3 mt-0.5"></i>
        <div><?php echo htmlspecialchars($message); ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="bg-red-500/10 border border-red-500 text-red-500 px-6 py-4 rounded-lg mb-6 flex items-start">
        <i class="bi bi-exclamation-triangle-fill text-xl mr-3 mt-0.5"></i>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
    <?php endif; ?>
    
    <!-- Current Status -->
    <?php if ($existing_verification): ?>
    <div class="bg-gh-panel border border-gh-border rounded-lg p-6 mb-8">
        <h2 class="text-xl font-bold text-gh-fg mb-4">Verification Status</h2>
        <div class="flex items-center justify-between">
            <div>
                <div class="text-gh-muted mb-1">Current Status</div>
                <div class="flex items-center">
                    <?php if ($existing_verification['status'] === 'approved'): ?>
                        <span class="inline-flex items-center px-4 py-2 bg-gh-success/10 text-gh-success rounded-full font-medium">
                            <i class="bi bi-check-circle-fill mr-2"></i> Verified
                        </span>
                    <?php elseif ($existing_verification['status'] === 'pending'): ?>
                        <span class="inline-flex items-center px-4 py-2 bg-yellow-500/10 text-yellow-500 rounded-full font-medium">
                            <i class="bi bi-clock-fill mr-2"></i> Pending Review
                        </span>
                    <?php elseif ($existing_verification['status'] === 'denied'): ?>
                        <span class="inline-flex items-center px-4 py-2 bg-red-500/10 text-red-500 rounded-full font-medium">
                            <i class="bi bi-x-circle-fill mr-2"></i> Denied
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-gh-muted text-sm mb-1">Submitted</div>
                <div class="text-gh-fg"><?php echo date('M j, Y', strtotime($existing_verification['submitted_at'])); ?></div>
            </div>
        </div>
        
        <?php if ($existing_verification['status'] === 'denied' && $existing_verification['admin_notes']): ?>
        <div class="mt-4 pt-4 border-t border-gh-border">
            <div class="text-gh-muted text-sm mb-1">Reason for Denial</div>
            <div class="text-gh-fg"><?php echo htmlspecialchars($existing_verification['admin_notes']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- How It Works -->
    <div class="bg-gh-panel border border-gh-border rounded-lg p-6 mb-8">
        <h2 class="text-xl font-bold text-gh-fg mb-4">How Profile Verification Works</h2>
        <div class="space-y-4">
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-gh-accent/10 text-gh-accent rounded-full flex items-center justify-center font-bold mr-4">1</div>
                <div>
                    <h3 class="font-semibold text-gh-fg mb-1">Prepare Your Sign</h3>
                    <p class="text-gh-muted">Write your trubl username (<strong><?php echo htmlspecialchars($username); ?></strong>) and today's date (<strong><?php echo date('m/d/Y'); ?></strong>) on a piece of paper or sign.</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-gh-accent/10 text-gh-accent rounded-full flex items-center justify-center font-bold mr-4">2</div>
                <div>
                    <h3 class="font-semibold text-gh-fg mb-1">Record Your Video</h3>
                    <p class="text-gh-muted">Record a 15-second video holding the sign with your face clearly visible. Speak your username in the video for additional verification.</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-gh-accent/10 text-gh-accent rounded-full flex items-center justify-center font-bold mr-4">3</div>
                <div>
                    <h3 class="font-semibold text-gh-fg mb-1">Submit for Review</h3>
                    <p class="text-gh-muted">Upload your video using the form below. Our team will review within 24-48 hours.</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-gh-accent/10 text-gh-accent rounded-full flex items-center justify-center font-bold mr-4">4</div>
                <div>
                    <h3 class="font-semibold text-gh-fg mb-1">Get Verified</h3>
                    <p class="text-gh-muted">Once approved, you'll receive a verified badge on your profile, building trust with other members.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upload Form -->
    <?php if (!$existing_verification || $existing_verification['status'] === 'denied'): ?>
    <div class="bg-gh-panel border border-gh-border rounded-lg p-6">
        <h2 class="text-xl font-bold text-gh-fg mb-6">Submit Verification Video</h2>
        
        <form method="POST" enctype="multipart/form-data" id="verificationForm">
            <!-- Video Upload -->
            <div class="mb-6">
                <label class="block text-gh-fg font-medium mb-2">Upload Verification Video</label>
                <div class="border-2 border-dashed border-gh-border rounded-lg p-8 text-center hover:border-gh-accent transition-colors">
                    <input type="file" name="verification_video" id="videoInput" accept="video/*" required class="hidden">
                    <label for="videoInput" class="cursor-pointer">
                        <i class="bi bi-camera-video text-5xl text-gh-muted mb-3 block"></i>
                        <div class="text-gh-fg font-medium mb-1">Click to upload video</div>
                        <div class="text-gh-muted text-sm">MP4, MOV, AVI, or WEBM (Max 50MB, 15 seconds)</div>
                        <div id="fileName" class="text-gh-accent mt-2 font-medium hidden"></div>
                    </label>
                </div>
            </div>
            
            <!-- Requirements Checklist -->
            <div class="bg-gh-bg border border-gh-border rounded-lg p-4 mb-6">
                <div class="font-medium text-gh-fg mb-3">Verification Requirements:</div>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center text-gh-muted hover:text-gh-fg cursor-pointer">
                        <input type="checkbox" required class="mr-2 rounded border-gh-border">
                        Your face is clearly visible in the video
                    </label>
                    <label class="flex items-center text-gh-muted hover:text-gh-fg cursor-pointer">
                        <input type="checkbox" required class="mr-2 rounded border-gh-border">
                        Sign shows username: <strong class="text-gh-fg"><?php echo htmlspecialchars($username); ?></strong>
                    </label>
                    <label class="flex items-center text-gh-muted hover:text-gh-fg cursor-pointer">
                        <input type="checkbox" required class="mr-2 rounded border-gh-border">
                        Sign shows today's date: <strong class="text-gh-fg"><?php echo date('m/d/Y'); ?></strong>
                    </label>
                    <label class="flex items-center text-gh-muted hover:text-gh-fg cursor-pointer">
                        <input type="checkbox" required class="mr-2 rounded border-gh-border">
                        Video is approximately 15 seconds long
                    </label>
                </div>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="w-full bg-gradient-to-r from-purple-600 to-pink-500 hover:from-purple-700 hover:to-pink-600 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                <i class="bi bi-upload mr-2"></i> Submit for Verification
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Benefits -->
    <div class="mt-8 bg-gradient-to-br from-purple-600/10 to-pink-500/10 border border-gh-accent/20 rounded-lg p-6">
        <h3 class="font-bold text-gh-fg mb-3 flex items-center">
            <i class="bi bi-star-fill text-gh-accent mr-2"></i>
            Benefits of Verification
        </h3>
        <ul class="space-y-2 text-gh-muted">
            <li class="flex items-start">
                <i class="bi bi-check2 text-gh-success mr-2 mt-1"></i>
                <span>Verified badge displayed on your profile</span>
            </li>
            <li class="flex items-start">
                <i class="bi bi-check2 text-gh-success mr-2 mt-1"></i>
                <span>Increased trust and credibility with other members</span>
            </li>
            <li class="flex items-start">
                <i class="bi bi-check2 text-gh-success mr-2 mt-1"></i>
                <span>Higher visibility in search results</span>
            </li>
            <li class="flex items-start">
                <i class="bi bi-check2 text-gh-success mr-2 mt-1"></i>
                <span>Access to exclusive verified-only features</span>
            </li>
        </ul>
    </div>
</div>

<script>
document.getElementById('videoInput').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileNameDisplay = document.getElementById('fileName');
    if (fileName) {
        fileNameDisplay.textContent = fileName;
        fileNameDisplay.classList.remove('hidden');
    }
});
</script>

<?php include 'views/footer.php'; ?>
