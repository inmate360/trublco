<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : null;
    $reporter_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $reason = isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '';

    // Validate inputs
    if (!$listing_id) {
        $error = 'Invalid listing ID.';
    } elseif (!$reason) {
        $error = 'Please select a reason for reporting.';
    } else {
        // Check if listing exists
        try {
            $check_stmt = $db->prepare("SELECT id FROM listings WHERE id = ?");
            $check_stmt->execute([$listing_id]);
            $listing_exists = $check_stmt->fetch();

            if (!$listing_exists) {
                $error = 'This listing no longer exists or has been removed.';
            } else {
                // Insert the report
                $query = "INSERT INTO reports (listing_id, reporter_id, reason, status, created_at) 
                          VALUES (:listing_id, :reporter_id, :reason, 'pending', NOW())";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':listing_id', $listing_id, PDO::PARAM_INT);
                $stmt->bindParam(':reporter_id', $reporter_id, PDO::PARAM_INT);
                $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $success = 'Thank you for your report. Our moderation team will review it within 24 hours.';
                } else {
                    $error = 'Failed to submit report. Please try again.';
                }
            }
        } catch (PDOException $e) {
            error_log("Report submission error: " . $e->getMessage());
            $error = 'An error occurred while submitting your report. Please try again later.';
        }
    }
}

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gh-fg mb-2">Report Abuse</h1>
            <p class="text-gh-muted">Help us keep trubl safe by reporting violations of our community guidelines</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="mb-6 bg-green-500/10 border border-green-500 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="bi bi-check-circle-fill text-green-500 text-xl"></i>
                    <div class="flex-1">
                        <p class="text-green-500 font-medium"><?php echo $success; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="mb-6 bg-red-500/10 border border-red-500 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl"></i>
                    <div class="flex-1">
                        <p class="text-red-500 font-medium"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Report Form -->
        <div class="bg-gh-panel border border-gh-border rounded-lg p-6">
            <form method="POST" action="report-abuse.php">

                <!-- Hidden listing ID -->
                <input type="hidden" name="listing_id" value="<?php echo isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : ''; ?>">

                <!-- Reason Selection -->
                <div class="mb-6">
                    <label for="reason" class="block text-sm font-medium text-gh-fg mb-2">
                        Reason for Report <span class="text-red-500">*</span>
                    </label>
                    <select name="reason" id="reason" required 
                            class="w-full bg-gh-panel2 border border-gh-border rounded-lg px-4 py-2.5 text-gh-fg focus:border-gh-accent focus:outline-none">
                        <option value="">Select a reason...</option>
                        <option value="spam">Spam or Fake Listing</option>
                        <option value="inappropriate">Inappropriate Content</option>
                        <option value="harassment">Harassment or Bullying</option>
                        <option value="scam">Scam or Fraud</option>
                        <option value="illegal">Illegal Activity</option>
                        <option value="underage">Underage User</option>
                        <option value="violence">Violence or Threats</option>
                        <option value="hate_speech">Hate Speech</option>
                        <option value="impersonation">Impersonation</option>
                        <option value="other">Other Violation</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center gap-3">
                    <button type="submit" 
                            class="px-6 py-2.5 bg-gh-accent text-white rounded-lg hover:opacity-90 transition font-medium">
                        <i class="bi bi-flag-fill"></i>
                        Submit Report
                    </button>
                    <a href="javascript:history.back()" 
                       class="px-6 py-2.5 bg-gh-panel2 text-gh-fg rounded-lg hover:bg-gh-border transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Info Cards -->
        <div class="mt-8 grid gap-4">
            <div class="bg-gh-panel border border-gh-border rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="bi bi-shield-check text-gh-accent text-xl"></i>
                    <div>
                        <h3 class="text-sm font-semibold text-gh-fg mb-1">What Happens Next?</h3>
                        <p class="text-sm text-gh-muted">Our moderation team will review your report within 24 hours</p>
                    </div>
                </div>
            </div>

            <div class="bg-gh-panel border border-gh-border rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="bi bi-trash text-gh-accent text-xl"></i>
                    <div>
                        <h3 class="text-sm font-semibold text-gh-fg mb-1">Content Removal</h3>
                        <p class="text-sm text-gh-muted">If the content violates our Terms of Service, it will be removed promptly</p>
                    </div>
                </div>
            </div>

            <div class="bg-gh-panel border border-gh-border rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="bi bi-person-x text-gh-accent text-xl"></i>
                    <div>
                        <h3 class="text-sm font-semibold text-gh-fg mb-1">Account Actions</h3>
                        <p class="text-sm text-gh-muted">Repeat offenders may have their accounts suspended or permanently banned</p>
                    </div>
                </div>
            </div>

            <div class="bg-gh-panel border border-gh-border rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="bi bi-envelope text-gh-accent text-xl"></i>
                    <div>
                        <h3 class="text-sm font-semibold text-gh-fg mb-1">Follow Up</h3>
                        <p class="text-sm text-gh-muted">We may contact you if additional information is needed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Anonymous Reporting Notice -->
        <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="mt-6 bg-blue-500/10 border border-blue-500 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i class="bi bi-info-circle-fill text-blue-500 text-xl"></i>
                <div>
                    <h3 class="text-sm font-semibold text-blue-500 mb-1">Anonymous Reporting</h3>
                    <p class="text-sm text-blue-400">You are submitting this report anonymously. Consider <a href="login.php" class="underline">logging in</a> for faster processing.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'views/footer.php'; ?>
