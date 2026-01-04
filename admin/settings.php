<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, username, email, is_admin FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$current_user || !$current_user['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Get pending counts
$pending_counts = ['verifications' => 0, 'upgrades' => 0, 'reports' => 0];
$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_verifications WHERE status = 'pending'");
$stmt->execute();
$pending_counts['verifications'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'pending'");
$stmt->execute();
$pending_counts['upgrades'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stmt->execute();
$pending_counts['reports'] = $stmt->fetch()['count'] ?? 0;

// Create table if not exists
try {
    $query = "CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        updated_by INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_key (setting_key)
    )";
    $db->exec($query);
} catch(PDOException $e) {
    error_log("Error creating site_settings table: " . $e->getMessage());
}

// Get current settings
$current_settings = [];
try {
    $query = "SELECT setting_key, setting_value FROM site_settings";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['save_general'])) {
        $settings_to_save = [
            'site_name' => $_POST['site_name'] ?? 'trubl',
            'site_tagline' => $_POST['site_tagline'] ?? '',
            'admin_email' => $_POST['admin_email'] ?? '',
            'posts_per_page' => $_POST['posts_per_page'] ?? '20',
            'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0'
        ];

        try {
            foreach($settings_to_save as $key => $value) {
                $query = "INSERT INTO site_settings (setting_key, setting_value, updated_by) 
                         VALUES (:key, :value, :user_id) 
                         ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :user_id2";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':user_id2', $_SESSION['user_id']);
                $stmt->execute();
            }
            $success = 'General settings saved successfully!';

            // Refresh settings
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            $stmt->execute();
            $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch(PDOException $e) {
            $error = 'Failed to save settings';
        }
    }

    if(isset($_POST['save_maintenance'])) {
        $settings_to_save = [
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'maintenance_title' => $_POST['maintenance_title'] ?? 'Site Maintenance',
            'maintenance_message' => $_POST['maintenance_message'] ?? '',
            'allow_admin_access' => isset($_POST['allow_admin_access']) ? '1' : '0'
        ];

        try {
            foreach($settings_to_save as $key => $value) {
                $query = "INSERT INTO site_settings (setting_key, setting_value, updated_by) 
                         VALUES (:key, :value, :user_id) 
                         ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :user_id2";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':value2', $value);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':user_id2', $_SESSION['user_id']);
                $stmt->execute();
            }
            $success = 'Maintenance settings saved!';

            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            $stmt->execute();
            $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch(PDOException $e) {
            $error = 'Failed to save settings';
        }
    }
}

$active_tab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - trubl Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {'50': '#eff6ff', '100': '#dbeafe', '200': '#bfdbfe', '300': '#93c5fd', '400': '#60a5fa', '500': '#3b82f6', '600': '#2563eb', '700': '#1d4ed8', '800': '#1e40af', '900': '#1e3a8a'}
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900">

    <!-- Sidebar -->
    <aside class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0 bg-gray-800 border-r border-gray-700">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <div class="mb-5 px-3 py-2">
                <a href="../index.php" class="flex items-center">
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">⚡ trubl</span>
                </a>
                <p class="mt-1 text-xs text-gray-400">Admin Control Panel</p>
            </div>

            <div class="mb-4 p-4 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg">
                <div class="flex items-center">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-blue-600 font-bold text-xl">
                            <?php echo strtoupper(substr($current_user['username'], 0, 1)); ?>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-gray-800 rounded-full"></span>
                    </div>
                    <div class="ml-3">
                        <p class="text-white font-semibold"><?php echo htmlspecialchars($current_user['username']); ?></p>
                        <p class="text-xs text-blue-100">Administrator</p>
                    </div>
                </div>
            </div>

            <ul class="space-y-2 font-medium">
                <li><a href="dashboard.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-tachometer-alt w-5 h-5"></i><span class="ml-3">Dashboard</span></a></li>
                <li><a href="users.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-users w-5 h-5"></i><span class="ml-3">Users</span></a></li>
                <li><a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-list w-5 h-5"></i><span class="ml-3">Listings</span></a></li>
                <li><a href="verifications.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-shield-alt w-5 h-5"></i><span class="ml-3">Verifications</span><?php if ($pending_counts['verifications'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse"><?php echo $pending_counts['verifications']; ?></span><?php endif; ?></a></li>
                <li><a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-gem w-5 h-5"></i><span class="ml-3">Upgrades</span><?php if ($pending_counts['upgrades'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo $pending_counts['upgrades']; ?></span><?php endif; ?></a></li>
                <li><a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative"><i class="fas fa-flag w-5 h-5"></i><span class="ml-3">Reports</span><?php if ($pending_counts['reports'] > 0): ?><span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full"><?php echo $pending_counts['reports']; ?></span><?php endif; ?></a></li>
                <li><a href="categories.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-tags w-5 h-5"></i><span class="ml-3">Categories</span></a></li>
                <li><a href="settings.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg"><i class="fas fa-cog w-5 h-5"></i><span class="ml-3">Settings</span></a></li>
                <li><a href="mod.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-shield-alt w-5 h-5"></i><span class="ml-3">Content Moderation</span></a></li>
            </ul>

            <div class="pt-4 mt-4 space-y-2 border-t border-gray-700">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700"><i class="fas fa-home w-5 h-5"></i><span class="ml-3">Back to Site</span></a>
                <a href="../logout.php" class="flex items-center p-3 text-red-400 rounded-lg hover:bg-red-900/20"><i class="fas fa-sign-out-alt w-5 h-5"></i><span class="ml-3">Logout</span></a>
            </div>
        </div>
    </aside>

    <div class="sm:ml-64">
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4 py-3 lg:px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-cog text-gray-400 mr-3"></i>
                            Site Settings
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Configure site-wide settings and preferences</p>
                    </div>
                </div>
            </div>
        </nav>

        <main class="p-4 lg:p-6">
            <?php if ($success): ?>
                <div class="p-4 mb-4 text-sm text-green-400 rounded-lg bg-green-900/20 border border-green-500/50" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-4 mb-4 text-sm text-red-400 rounded-lg bg-red-900/20 border border-red-500/50" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="mb-6 border-b border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                    <li class="mr-2">
                        <a href="?tab=general" class="inline-block p-4 <?php echo $active_tab == 'general' ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-400 hover:text-gray-300'; ?> rounded-t-lg">
                            <i class="fas fa-sliders-h mr-2"></i>General Settings
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="?tab=maintenance" class="inline-block p-4 <?php echo $active_tab == 'maintenance' ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-400 hover:text-gray-300'; ?> rounded-t-lg">
                            <i class="fas fa-tools mr-2"></i>Maintenance Mode
                        </a>
                    </li>
                </ul>
            </div>

            <!-- General Settings Tab -->
            <?php if($active_tab == 'general'): ?>
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-sliders-h text-blue-400 mr-2"></i>
                    General Settings
                </h2>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Site Name</label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars($current_settings['site_name'] ?? 'trubl'); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Site Tagline</label>
                        <input type="text" name="site_tagline" value="<?php echo htmlspecialchars($current_settings['site_tagline'] ?? ''); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Admin Email</label>
                        <input type="email" name="admin_email" value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Posts Per Page</label>
                        <input type="number" name="posts_per_page" value="<?php echo htmlspecialchars($current_settings['posts_per_page'] ?? '20'); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="allow_registration" id="allow_registration" 
                               <?php echo ($current_settings['allow_registration'] ?? '1') == '1' ? 'checked' : ''; ?>
                               class="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500">
                        <label for="allow_registration" class="ml-2 text-sm font-medium text-gray-300">Allow User Registration</label>
                    </div>

                    <button type="submit" name="save_general" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-lg transition-all transform hover:scale-105">
                        <i class="fas fa-save mr-2"></i>Save General Settings
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Maintenance Tab -->
            <?php if($active_tab == 'maintenance'): ?>
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-tools text-orange-400 mr-2"></i>
                    Maintenance Mode
                </h2>

                <form method="POST" class="space-y-6">
                    <div class="p-4 bg-orange-900/20 border border-orange-500/50 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-orange-400 text-2xl mr-3"></i>
                            <div>
                                <p class="text-orange-400 font-semibold">Maintenance Mode Status</p>
                                <p class="text-gray-300 text-sm mt-1">
                                    <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'Currently ACTIVE - Site is in maintenance mode' : 'Currently INACTIVE - Site is operating normally'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" 
                               <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>
                               class="w-4 h-4 text-orange-600 bg-gray-700 border-gray-600 rounded focus:ring-orange-500">
                        <label for="maintenance_mode" class="ml-2 text-sm font-medium text-gray-300">Enable Maintenance Mode</label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Maintenance Title</label>
                        <input type="text" name="maintenance_title" value="<?php echo htmlspecialchars($current_settings['maintenance_title'] ?? 'Site Maintenance'); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-orange-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Maintenance Message</label>
                        <textarea name="maintenance_message" rows="4" 
                                  class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-orange-500"><?php echo htmlspecialchars($current_settings['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back soon.'); ?></textarea>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="allow_admin_access" id="allow_admin_access" 
                               <?php echo ($current_settings['allow_admin_access'] ?? '1') == '1' ? 'checked' : ''; ?>
                               class="w-4 h-4 text-orange-600 bg-gray-700 border-gray-600 rounded focus:ring-orange-500">
                        <label for="allow_admin_access" class="ml-2 text-sm font-medium text-gray-300">Allow Admin Access During Maintenance</label>
                    </div>

                    <button type="submit" name="save_maintenance" 
                            class="px-6 py-3 bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white font-semibold rounded-lg transition-all transform hover:scale-105">
                        <i class="fas fa-save mr-2"></i>Save Maintenance Settings
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>
    <script>
        setTimeout(() => {
            document.querySelectorAll('[role="alert"]').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
