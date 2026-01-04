<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = str_replace('setting_', '', $key);
            $stmt = $db->prepare("UPDATE moderation_settings SET setting_value = :value WHERE setting_key = :key");
            $stmt->execute(['value' => $value, 'key' => $settingKey]);
        }
    }

    // Handle checkboxes (they don't send POST data if unchecked)
    $checkboxes = ['moderate_listings', 'moderate_marketplace', 'moderate_forum', 'moderate_profiles', 'enable_scam_detection'];
    foreach ($checkboxes as $checkbox) {
        if (!isset($_POST['setting_' . $checkbox])) {
            $stmt = $db->prepare("UPDATE moderation_settings SET setting_value = 'false' WHERE setting_key = :key");
            $stmt->execute(['key' => $checkbox]);
        }
    }

    header('Location: mod.php?success=1');
    exit;
}

// Get current settings
$query = "SELECT * FROM moderation_settings";
$stmt = $db->query($query);
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include '../views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-6">
    <div class="mx-auto max-w-4xl px-4">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">
                    <i class="bi bi-shield-lock-fill mr-2 text-gh-accent"></i>
                    Content Moderation Control
                </h1>
                <p class="mt-1 text-sm text-gh-muted">Configure AI content moderation behavior</p>
            </div>
            <a href="moderation.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 font-semibold text-gh-fg transition-all hover:border-gh-accent">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="mb-6 rounded-lg border border-green-500/30 bg-green-500/10 p-4">
            <p class="text-sm text-green-500">
                <i class="bi bi-check-circle-fill mr-2"></i>
                Settings saved successfully!
            </p>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">

            <!-- General Settings -->
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-white">
                    <i class="bi bi-toggles text-gh-accent"></i>
                    General Settings
                </h2>

                <div class="space-y-4">
                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <label class="font-semibold text-gh-fg">Enable Auto-Moderation</label>
                                <p class="mt-1 text-sm text-gh-muted">Automatically scan all user-generated content using AI</p>
                            </div>
                            <select name="setting_auto_moderate_enabled" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
                                <option value="true" <?php echo ($settings['auto_moderate_enabled'] ?? 'true') === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="false" <?php echo ($settings['auto_moderate_enabled'] ?? 'true') === 'false' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <label class="font-semibold text-gh-fg">Auto-Flag High Risk Content</label>
                                <p class="mt-1 text-sm text-gh-muted">Automatically flag and hide high-risk content for admin review</p>
                            </div>
                            <select name="setting_auto_flag_high_risk" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
                                <option value="true" <?php echo ($settings['auto_flag_high_risk'] ?? 'true') === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="false" <?php echo ($settings['auto_flag_high_risk'] ?? 'true') === 'false' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <label class="font-semibold text-gh-fg">Auto-Reject Threshold</label>
                                <p class="mt-1 text-sm text-gh-muted">Confidence level (0.0-1.0) at which content is automatically rejected without review</p>
                            </div>
                            <input type="number" name="setting_auto_reject_threshold" value="<?php echo htmlspecialchars($settings['auto_reject_threshold'] ?? '0.9'); ?>" step="0.05" min="0" max="1" class="w-24 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
                        </div>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <label class="font-semibold text-gh-fg">AI Rate Limit (per hour)</label>
                                <p class="mt-1 text-sm text-gh-muted">Maximum number of AI moderation scans allowed per user per hour</p>
                            </div>
                            <input type="number" name="setting_ai_rate_limit_per_hour" value="<?php echo htmlspecialchars($settings['ai_rate_limit_per_hour'] ?? '10'); ?>" min="1" max="100" class="w-24 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Type Settings -->
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-white">
                    <i class="bi bi-files text-purple-500"></i>
                    Content Types to Moderate
                </h2>
                <p class="mb-4 text-sm text-gh-muted">Select which types of content should be analyzed by the AI moderation system</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <label class="flex cursor-pointer items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-pink-500/20 text-pink-500">
                                    <i class="bi bi-heart-fill"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gh-fg">Personals Listings</div>
                                    <div class="text-xs text-gh-muted">Profile ads & descriptions</div>
                                </div>
                            </div>
                            <input type="checkbox" name="setting_moderate_listings" value="true" <?php echo ($settings['moderate_listings'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
                        </label>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <label class="flex cursor-pointer items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/20 text-purple-500">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gh-fg">Creator Marketplace</div>
                                    <div class="text-xs text-gh-muted">Product listings & content</div>
                                </div>
                            </div>
                            <input type="checkbox" name="setting_moderate_marketplace" value="true" <?php echo ($settings['moderate_marketplace'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
                        </label>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <label class="flex cursor-pointer items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/20 text-blue-500">
                                    <i class="bi bi-chat-square-dots-fill"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gh-fg">Forum Posts</div>
                                    <div class="text-xs text-gh-muted">Threads, replies & discussions</div>
                                </div>
                            </div>
                            <input type="checkbox" name="setting_moderate_forum" value="true" <?php echo ($settings['moderate_forum'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
                        </label>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <label class="flex cursor-pointer items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/20 text-green-500">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gh-fg">User Profiles</div>
                                    <div class="text-xs text-gh-muted">Bios, interests & about text</div>
                                </div>
                            </div>
                            <input type="checkbox" name="setting_moderate_profiles" value="true" <?php echo ($settings['moderate_profiles'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
                        </label>
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <label class="flex cursor-pointer items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-500/20 text-red-500">
                                    <i class="bi bi-shield-exclamation"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gh-fg">Romance Scam Detection</div>
                                    <div class="text-xs text-gh-muted">Analyze messages for scam patterns</div>
                                </div>
                            </div>
                            <input type="checkbox" name="setting_enable_scam_detection" value="true" <?php echo ($settings['enable_scam_detection'] ?? 'true') === 'true' ? 'checked' : ''; ?> class="h-5 w-5 rounded border-gh-border bg-gh-bg text-gh-accent focus:ring-2 focus:ring-gh-accent">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="rounded-lg border border-gh-border bg-gh-panel p-6">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-white">
                    <i class="bi bi-bell-fill text-yellow-500"></i>
                    Notifications
                </h2>
                <p class="mb-4 text-sm text-gh-muted">Configure email alerts and notifications for moderation events</p>

                <div class="space-y-4">
                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <label class="mb-2 block font-semibold text-gh-fg">
                            <i class="bi bi-envelope-fill mr-2"></i>Admin Email Address
                        </label>
                        <p class="mb-3 text-sm text-gh-muted">Email address to receive moderation alerts</p>
                        <input type="email" name="setting_admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? 'admin@trubl.co'); ?>" class="w-full rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none" placeholder="admin@trubl.co">
                    </div>

                    <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <label class="font-semibold text-gh-fg">Email Alerts on High Risk Flags</label>
                                <p class="mt-1 text-sm text-gh-muted">Send immediate email notification when high-risk content is flagged</p>
                            </div>
                            <select name="setting_notify_admin_on_flag" class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
                                <option value="true" <?php echo ($settings['notify_admin_on_flag'] ?? 'true') === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="false" <?php echo ($settings['notify_admin_on_flag'] ?? 'true') === 'false' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Information -->
            <div class="rounded-lg border border-gh-accent/30 bg-gh-accent/10 p-6">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-white">
                    <i class="bi bi-info-circle-fill text-gh-accent"></i>
                    API Information
                </h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                        <div>
                            <strong class="text-gh-fg">Provider:</strong>
                            <span class="text-gh-muted">Perplexity AI</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                        <div>
                            <strong class="text-gh-fg">Model:</strong>
                            <span class="text-gh-muted">llama-3.1-sonar-small-128k-online</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
                        <div>
                            <strong class="text-gh-fg">Status:</strong>
                            <span class="text-green-500">Active & Configured</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <i class="bi bi-info-circle-fill mt-0.5 text-blue-500"></i>
                        <div>
                            <strong class="text-gh-fg">Note:</strong>
                            <span class="text-gh-muted">To change API key, edit classes/ContentModerator.php</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex gap-4">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110 hover:shadow-xl">
                    <i class="bi bi-save"></i>
                    Save Settings
                </button>
                <a href="moderation.php" class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-6 py-3 font-semibold text-gh-fg transition-all hover:border-gh-accent">
                    <i class="bi bi-x-circle"></i>
                    Cancel
                </a>
                <button type="button" onclick="resetToDefaults()" class="ml-auto inline-flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-6 py-3 font-semibold text-red-500 transition-all hover:bg-red-500/20">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Reset to Defaults
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function resetToDefaults() {
    if (confirm('Reset all settings to default values?')) {
        // Set form values to defaults
        document.querySelector('[name="setting_auto_moderate_enabled"]').value = 'true';
        document.querySelector('[name="setting_auto_flag_high_risk"]').value = 'true';
        document.querySelector('[name="setting_auto_reject_threshold"]').value = '0.9';
        document.querySelector('[name="setting_moderate_listings"]').checked = true;
        document.querySelector('[name="setting_moderate_marketplace"]').checked = true;
        document.querySelector('[name="setting_moderate_forum"]').checked = true;
        document.querySelector('[name="setting_moderate_profiles"]').checked = true;
        document.querySelector('[name="setting_notify_admin_on_flag"]').value = 'true';
        document.querySelector('[name="setting_admin_email"]').value = 'admin@trubl.co';

        alert('Form reset to defaults. Click "Save Settings" to apply.');
    }
}
</script>

<?php include '../views/footer.php'; ?>
