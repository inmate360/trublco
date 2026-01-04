<?php
session_start();
require_once 'config/database.php';
require_once 'classes/SmartNotifications.php';
require_once 'classes/IncognitoMode.php';
require_once 'classes/SocialIntegration.php';

if(!isset($_SESSION['user_id'])) {
  header('Location: login.php');
    exit();

}

$database = new Database();
$db = $database->getConnection();
$notifClass = new SmartNotifications($db);
$incognito = new IncognitoMode($db);
$social = new SocialIntegration($db);
$userSettings = new UserSettings($db);  
$settings = $userSettings->getSettings($_SESSION['user_id']);

$error = '';
$success = '';

// Handle settings update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'bio' => trim($_POST['bio']),
            'phone' => trim($_POST['phone'])
        ];
        
        $result = $userSettings->updateProfile($_SESSION['user_id'], $data);
        if($result['success']) {
            $success = 'Profile updated successfully!';
        } else {
            $error = $result['error'];
        }
    }
    
    if(isset($_POST['update_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if($new !== $confirm) {
            $error = 'New passwords do not match';
        } else {
            $result = $userSettings->changePassword($_SESSION['user_id'], $current, $new);
            if($result['success']) {
                $success = 'Password changed successfully!';
            } else {
                $error = $result['error'];
            }
        }
    }
    
    if(isset($_POST['update_notifications'])) {
        $data = [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'message_notifications' => isset($_POST['message_notifications']) ? 1 : 0,
            'listing_updates' => isset($_POST['listing_updates']) ? 1 : 0
        ];
        
        $result = $userSettings->updateNotifications($_SESSION['user_id'], $data);
        if($result['success']) {
            $success = 'Notification preferences updated!';
        }
    }
    
    if(isset($_POST['update_privacy'])) {
        $data = [
            'profile_visibility' => $_POST['profile_visibility'],
            'show_online_status' => isset($_POST['show_online_status']) ? 1 : 0,
            'allow_messages_from' => $_POST['allow_messages_from']
        ];
        
        $result = $userSettings->updatePrivacy($_SESSION['user_id'], $data);
        if($result['success']) {
            $success = 'Privacy settings updated!';
        }
    }
    
    if(isset($_POST['delete_account'])) {
        $result = $userSettings->deleteAccount($_SESSION['user_id'], $_POST['confirm_delete']);
        if($result['success']) {
            session_destroy();
            header('Location: index.php?deleted=1');
            exit();
        } else {
            $error = $result['error'];
        }
    }
}

include 'views/header.php';
?>

<div class="mx-auto max-w-5xl px-4 py-8">
  
  <!-- Header -->
  <div class="mb-8">
    <h1 class="mb-2 flex items-center gap-3 text-3xl font-extrabold tracking-tight">
      <i class="bi bi-gear-fill text-gh-accent"></i>
      Settings
    </h1>
    <p class="text-sm text-gh-muted">Manage your account preferences and privacy</p>
  </div>

  <!-- Messages -->
  <?php if(!empty($success)): ?>
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-lg text-gh-success"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-success">Success!</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($success); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if(!empty($error)): ?>
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-lg text-gh-danger"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-danger">Error:</span>
          <span class="text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid gap-6 lg:grid-cols-4">
    
    <!-- Sidebar Navigation -->
    <div class="lg:col-span-1">
      <nav class="sticky top-20 space-y-1 rounded-xl border border-gh-border bg-gh-panel p-4">
        <a href="#profile" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-person-fill text-gh-accent"></i>
          <span>Profile</span>
        </a>
        <a href="#password" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-shield-lock-fill text-gh-accent"></i>
          <span>Password</span>
        </a>
        <a href="#notifications" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-bell-fill text-gh-accent"></i>
          <span>Notifications</span>
        </a>
        <a href="#privacy" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-lock-fill text-gh-accent"></i>
          <span>Privacy</span>
        </a>
        <a href="#incognito" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-incognito text-gh-accent"></i>
          <span>Incognito Mode</span>
        </a>
        <a href="#social" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
          <i class="bi bi-share-fill text-gh-accent"></i>
          <span>Social Media</span>
        </a>
        <a href="#danger" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-gh-danger transition-colors hover:bg-gh-danger/10">
          <i class="bi bi-trash-fill"></i>
          <span>Delete Account</span>
        </a>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="lg:col-span-3 space-y-6">
      
      <!-- Profile Settings -->
      <section id="profile" class="rounded-xl border border-gh-border bg-gh-panel">
        <div class="border-b border-gh-border p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold">
            <i class="bi bi-person-fill text-gh-accent"></i>
            Profile Information
          </h2>
        </div>
        <form method="POST" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Username</label>
              <input type="text" 
                     name="username" 
                     value="<?php echo htmlspecialchars($settings['username'] ?? ''); ?>"
                     required
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Email</label>
              <input type="email" 
                     name="email" 
                     value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>"
                     required
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Phone</label>
              <input type="tel" 
                     name="phone" 
                     value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>"
                     placeholder="Optional"
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Bio</label>
              <textarea name="bio" 
                        rows="4"
                        placeholder="Tell us about yourself..."
                        class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50"><?php echo htmlspecialchars($settings['bio'] ?? ''); ?></textarea>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <button type="submit" 
                    name="update_profile"
                    class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
              <i class="bi bi-check-circle-fill"></i>
              Save Changes
            </button>
          </div>
        </form>
      </section>

      <!-- Password Settings -->
      <section id="password" class="rounded-xl border border-gh-border bg-gh-panel">
        <div class="border-b border-gh-border p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold">
            <i class="bi bi-shield-lock-fill text-gh-accent"></i>
            Change Password
          </h2>
        </div>
        <form method="POST" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Current Password</label>
              <input type="password" 
                     name="current_password" 
                     required
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">New Password</label>
              <input type="password" 
                     name="new_password" 
                     required
                     minlength="8"
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
              <p class="mt-1.5 text-xs text-gh-muted">At least 8 characters</p>
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Confirm New Password</label>
              <input type="password" 
                     name="confirm_password" 
                     required
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <button type="submit" 
                    name="update_password"
                    class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
              <i class="bi bi-key-fill"></i>
              Update Password
            </button>
          </div>
        </form>
      </section>

      <!-- Notification Settings -->
      <section id="notifications" class="rounded-xl border border-gh-border bg-gh-panel">
        <div class="border-b border-gh-border p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold">
            <i class="bi bi-bell-fill text-gh-accent"></i>
            Notification Preferences
          </h2>
        </div>
        <form method="POST" class="p-6">
          <div class="space-y-4">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
              <input type="checkbox" 
                     name="email_notifications" 
                     <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>
                     class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
              <div class="flex-1">
                <div class="font-semibold">Email Notifications</div>
                <div class="text-sm text-gh-muted">Receive email updates about your account</div>
              </div>
            </label>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
              <input type="checkbox" 
                     name="message_notifications" 
                     <?php echo ($settings['message_notifications'] ?? 1) ? 'checked' : ''; ?>
                     class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
              <div class="flex-1">
                <div class="font-semibold">Message Notifications</div>
                <div class="text-sm text-gh-muted">Get notified when you receive new messages</div>
              </div>
            </label>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
              <input type="checkbox" 
                     name="listing_updates" 
                     <?php echo ($settings['listing_updates'] ?? 1) ? 'checked' : ''; ?>
                     class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
              <div class="flex-1">
                <div class="font-semibold">Listing Updates</div>
                <div class="text-sm text-gh-muted">Updates about your active listings</div>
              </div>
            </label>
          </div>

          <div class="mt-6 flex justify-end">
            <button type="submit" 
                    name="update_notifications"
                    class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
              <i class="bi bi-check-circle-fill"></i>
              Save Preferences
            </button>
          </div>
        </form>
      </section>

      <!-- Privacy Settings -->
      <section id="privacy" class="rounded-xl border border-gh-border bg-gh-panel">
        <div class="border-b border-gh-border p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold">
            <i class="bi bi-lock-fill text-gh-accent"></i>
            Privacy Settings
          </h2>
        </div>
        <form method="POST" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Profile Visibility</label>
              <select name="profile_visibility" 
                      class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50">
                <option value="public" <?php echo ($settings['profile_visibility'] ?? 'public') == 'public' ? 'selected' : ''; ?>>Public</option>
                <option value="members" <?php echo ($settings['profile_visibility'] ?? '') == 'members' ? 'selected' : ''; ?>>Members Only</option>
                <option value="private" <?php echo ($settings['profile_visibility'] ?? '') == 'private' ? 'selected' : ''; ?>>Private</option>
              </select>
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">Allow Messages From</label>
              <select name="allow_messages_from" 
                      class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50">
                <option value="everyone" <?php echo ($settings['allow_messages_from'] ?? 'everyone') == 'everyone' ? 'selected' : ''; ?>>Everyone</option>
                <option value="verified" <?php echo ($settings['allow_messages_from'] ?? '') == 'verified' ? 'selected' : ''; ?>>Verified Only</option>
                <option value="none" <?php echo ($settings['allow_messages_from'] ?? '') == 'none' ? 'selected' : ''; ?>>No One</option>
              </select>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-4 transition-colors hover:bg-white/5">
              <input type="checkbox" 
                     name="show_online_status" 
                     <?php echo ($settings['show_online_status'] ?? 1) ? 'checked' : ''; ?>
                     class="mt-0.5 h-5 w-5 rounded border-gh-border bg-gh-panel text-gh-accent focus:ring-2 focus:ring-gh-accent/50" />
              <div class="flex-1">
                <div class="font-semibold">Show Online Status</div>
                <div class="text-sm text-gh-muted">Let others see when you're online</div>
              </div>
            </label>
          </div>

          <div class="mt-6 flex justify-end">
            <button type="submit" 
                    name="update_privacy"
                    class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
              <i class="bi bi-check-circle-fill"></i>
              Save Settings
            </button>
          </div>
        </form>
      </section>

      <!-- Incognito Mode -->
      <section id="incognito" class="rounded-xl border border-gh-border bg-gh-panel">
        <div class="border-b border-gh-border p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold">
            <i class="bi bi-incognito text-gh-accent"></i>
            Incognito Mode
          </h2>
        </div>
        <div class="p-6">
          <div class="mb-4 rounded-lg border border-gh-warning bg-gh-warning/10 p-4">
            <div class="mb-2 flex items-center gap-2 text-sm font-bold text-gh-warning">
              <i class="bi bi-stars"></i>
              Premium Feature
            </div>
            <p class="text-sm text-gh-muted">Browse ads anonymously without leaving a trace. Your profile views won't be recorded.</p>
          </div>

          <?php if($settings['incognito_active'] ?? false): ?>
            <div class="rounded-lg border border-gh-success bg-gh-success/10 p-4">
              <div class="mb-2 flex items-center gap-2 font-bold text-gh-success">
                <i class="bi bi-check-circle-fill"></i>
                Incognito Mode Active
              </div>
              <p class="text-sm text-gh-muted">
                Expires: <?php echo date('M j, Y', strtotime($settings['incognito_expires'])); ?>
              </p>
            </div>
          <?php else: ?>
            <a href="membership.php" 
               class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
              <i class="bi bi-star-fill"></i>
              Upgrade to Premium
            </a>
          <?php endif; ?>
        </div>
      </section>

      <!-- Social Media Connections -->
      <section id="social" class="rounded-xl border border-gh-border bg-gh-panel">
        <div class="border-b border-gh-border p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold">
            <i class="bi bi-share-fill text-gh-accent"></i>
            Social Media Connections
          </h2>
          <p class="mt-2 text-sm text-gh-muted">Connect your social media accounts for quick login</p>
        </div>
        <div class="p-6 space-y-3">
          <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#1877F2] text-white">
                <i class="bi bi-facebook"></i>
              </div>
              <div>
                <div class="font-semibold">Facebook</div>
                <?php if($settings['facebook_connected'] ?? false): ?>
                  <div class="text-xs text-gh-success">✓ Connected</div>
                <?php else: ?>
                  <div class="text-xs text-gh-muted">Not connected</div>
                <?php endif; ?>
              </div>
            </div>
            <button class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
              <?php echo ($settings['facebook_connected'] ?? false) ? 'Disconnect' : 'Connect'; ?>
            </button>
          </div>

          <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-black text-white">
                <i class="bi bi-twitter-x"></i>
              </div>
              <div>
                <div class="font-semibold">X (Twitter)</div>
                <?php if($settings['twitter_connected'] ?? false): ?>
                  <div class="text-xs text-gh-success">✓ Connected</div>
                <?php else: ?>
                  <div class="text-xs text-gh-muted">Not connected</div>
                <?php endif; ?>
              </div>
            </div>
            <button class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
              <?php echo ($settings['twitter_connected'] ?? false) ? 'Disconnect' : 'Connect'; ?>
            </button>
          </div>

          <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-panel2 p-4">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-[#833AB4] via-[#FD1D1D] to-[#FCAF45] text-white">
                <i class="bi bi-instagram"></i>
              </div>
              <div>
                <div class="font-semibold">Instagram</div>
                <?php if($settings['instagram_connected'] ?? false): ?>
                  <div class="text-xs text-gh-success">✓ Connected</div>
                <?php else: ?>
                  <div class="text-xs text-gh-muted">Not connected</div>
                <?php endif; ?>
              </div>
            </div>
            <button class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
              <?php echo ($settings['instagram_connected'] ?? false) ? 'Disconnect' : 'Connect'; ?>
            </button>
          </div>
        </div>
      </section>

      <!-- Danger Zone -->
      <section id="danger" class="rounded-xl border border-gh-danger bg-gh-danger/5">
        <div class="border-b border-gh-danger p-6">
          <h2 class="flex items-center gap-2 text-xl font-bold text-gh-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Danger Zone
          </h2>
        </div>
        <div class="p-6">
          <p class="mb-4 text-sm text-gh-muted">
            Once you delete your account, there is no going back. All your data, listings, and messages will be permanently deleted. Please be certain.
          </p>
          <button onclick="document.getElementById('deleteModal').classList.remove('hidden')"
                  class="inline-flex items-center gap-2 rounded-lg border border-gh-danger bg-gh-danger/10 px-6 py-2.5 text-sm font-semibold text-gh-danger transition-colors hover:bg-gh-danger hover:text-white">
            <i class="bi bi-trash-fill"></i>
            Delete Account
          </button>
        </div>
      </section>

    </div>
  </div>
</div>

<!-- Delete Account Modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
  <div class="w-full max-w-md rounded-xl border border-gh-danger bg-gh-panel">
    <div class="border-b border-gh-danger p-6">
      <h3 class="text-xl font-bold text-gh-danger">⚠️ Confirm Account Deletion</h3>
    </div>
    <form method="POST" class="p-6">
      <p class="mb-4 text-sm text-gh-muted">
        This action cannot be undone. Type <strong class="text-gh-fg">DELETE</strong> to confirm.
      </p>
      <input type="text" 
             name="confirm_delete" 
             placeholder="Type DELETE to confirm"
             required
             class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-danger focus:outline-none focus:ring-2 focus:ring-gh-danger/50" />
      
      <div class="mt-6 flex gap-3">
        <button type="button" 
                onclick="document.getElementById('deleteModal').classList.add('hidden')"
                class="flex-1 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5">
          Cancel
        </button>
        <button type="submit" 
                name="delete_account"
                class="flex-1 rounded-lg bg-gh-danger px-4 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
          Delete Forever
        </button>
      </div>
    </form>
  </div>
</div>

<?php include 'views/footer.php'; ?>
