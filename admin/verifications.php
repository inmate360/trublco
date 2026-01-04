<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check admin status
$query = "SELECT is_admin, username FROM users WHERE id = :user_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$current_user = $stmt->fetch();

if(!$current_user || !$current_user['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Handle verification actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve_verification'])) {
        $verification_id = $_POST['verification_id'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        try {
            $db->beginTransaction();

            $getQuery = "SELECT user_id, verification_type FROM user_verifications WHERE id = :vid";
            $getStmt = $db->prepare($getQuery);
            $getStmt->bindParam(':vid', $verification_id);
            $getStmt->execute();
            $verification = $getStmt->fetch(PDO::FETCH_ASSOC);

            if ($verification) {
                $updateQuery = "UPDATE user_verifications 
                               SET status = 'approved', 
                                   reviewed_at = NOW(), 
                                   reviewed_by = :admin_id,
                                   admin_notes = :notes
                               WHERE id = :vid";
                $stmt = $db->prepare($updateQuery);
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':notes', $admin_notes);
                $stmt->bindParam(':vid', $verification_id);
                $stmt->execute();

                $updateUserQuery = "UPDATE users 
                                   SET id_verified = 1, 
                                       age_verified = 1,
                                       id_verified_at = NOW(),
                                       age_verified_at = NOW(),
                                       verification_level = 'id_verified'
                                   WHERE id = :uid";
                $updateUserStmt = $db->prepare($updateUserQuery);
                $updateUserStmt->bindParam(':uid', $verification['user_id']);
                $updateUserStmt->execute();

                $db->commit();
                $success = 'Verification approved successfully!';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to approve verification: ' . $e->getMessage();
        }
    }

    if(isset($_POST['reject_verification'])) {
        $verification_id = $_POST['verification_id'];
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        if (empty($rejection_reason)) {
            $error = 'Please provide a rejection reason';
        } else {
            try {
                $getUserQuery = "SELECT user_id FROM user_verifications WHERE id = :vid";
                $getUserStmt = $db->prepare($getUserQuery);
                $getUserStmt->bindParam(':vid', $verification_id);
                $getUserStmt->execute();
                $userId = $getUserStmt->fetchColumn();

                $updateQuery = "UPDATE user_verifications 
                               SET status = 'rejected', 
                                   reviewed_at = NOW(), 
                                   reviewed_by = :admin_id,
                                   rejection_reason = :reason,
                                   admin_notes = :notes
                               WHERE id = :vid";
                $stmt = $db->prepare($updateQuery);
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':reason', $rejection_reason);
                $stmt->bindParam(':notes', $admin_notes);
                $stmt->bindParam(':vid', $verification_id);
                $stmt->execute();

                $success = 'Verification rejected';
            } catch (Exception $e) {
                $error = 'Failed to reject verification: ' . $e->getMessage();
            }
        }
    }
}

// Get pending counts for badges
$pending_counts = [];
$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_subscriptions WHERE status = 'pending'");
$stmt->execute();
$pending_counts['upgrades'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stmt->execute();
$pending_counts['reports'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM user_verifications WHERE status = 'pending'");
$stmt->execute();
$pending_counts['verifications'] = $stmt->fetch()['count'] ?? 0;

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_clause = '';
$params = [];
if ($status_filter != 'all') {
    $where_clause = "WHERE v.status = :status";
    $params[':status'] = $status_filter;
}

// Get total count
$countQuery = "SELECT COUNT(*) as count FROM user_verifications v $where_clause";
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total_verifications = $countStmt->fetch()['count'];
$total_pages = ceil($total_verifications / $per_page);

// Get verifications
$query = "SELECT v.*, u.username, u.email 
          FROM user_verifications v 
          LEFT JOIN users u ON v.user_id = u.id 
          $where_clause
          ORDER BY v.submitted_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$verifications = $stmt->fetchAll();

// Get status counts
$statsQuery = "SELECT status, COUNT(*) as count FROM user_verifications GROUP BY status";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$status_counts = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verifications - trubl Admin</title>

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

    <!-- Sidebar (same as dashboard) -->
    <aside class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0 bg-gray-800 border-r border-gray-700">
        <div class="h-full px-3 py-4 overflow-y-auto">
            <div class="mb-5 px-3 py-2">
                <a href="../index.php" class="flex items-center">
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        ⚡ trubl
                    </span>
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
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt w-5 h-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-users w-5 h-5"></i>
                        <span class="ml-3">Users</span>
                    </a>
                </li>
                <li>
                    <a href="listings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-list w-5 h-5"></i>
                        <span class="ml-3">Listings</span>
                    </a>
                </li>
                <li>
                    <a href="verifications.php" class="flex items-center p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg relative">
                        <i class="fas fa-shield-alt w-5 h-5"></i>
                        <span class="ml-3">Verifications</span>
                        <?php if ($pending_counts['verifications'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
                                <?php echo $pending_counts['verifications']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="upgrades.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                        <i class="fas fa-gem w-5 h-5"></i>
                        <span class="ml-3">Upgrades</span>
                        <?php if ($pending_counts['upgrades'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                                <?php echo $pending_counts['upgrades']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700 relative">
                        <i class="fas fa-flag w-5 h-5"></i>
                        <span class="ml-3">Reports</span>
                        <?php if ($pending_counts['reports'] > 0): ?>
                            <span class="ml-auto inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                                <?php echo $pending_counts['reports']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="categories.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-tags w-5 h-5"></i>
                        <span class="ml-3">Categories</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-cog w-5 h-5"></i>
                        <span class="ml-3">Settings</span>
                    </a>
                </li>
            </ul>

            <div class="pt-4 mt-4 space-y-2 border-t border-gray-700">
                <a href="../index.php" class="flex items-center p-3 text-gray-300 rounded-lg hover:bg-gray-700">
                    <i class="fas fa-home w-5 h-5"></i>
                    <span class="ml-3">Back to Site</span>
                </a>
                <a href="../logout.php" class="flex items-center p-3 text-red-400 rounded-lg hover:bg-red-900/20">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i>
                    <span class="ml-3">Logout</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="sm:ml-64">
        <!-- Top Bar -->
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4 py-3 lg:px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-shield-alt text-blue-400 mr-3"></i>
                            ID Verifications
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Review and approve user identity verifications</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <?php if ($pending_counts['verifications'] > 0): ?>
                            <span class="px-4 py-2 bg-red-500/20 text-red-400 rounded-lg text-sm font-semibold animate-pulse">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <?php echo $pending_counts['verifications']; ?> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="p-4 lg:p-6">
            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="p-4 mb-4 text-sm text-green-400 rounded-lg bg-green-900/20 border border-green-500/50" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="p-4 mb-4 text-sm text-red-400 rounded-lg bg-red-900/20 border border-red-500/50" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                    <a href="?status=all" class="px-4 py-2 <?php echo $status_filter == 'all' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-list mr-2"></i>
                        All (<?php echo array_sum($status_counts); ?>)
                    </a>
                    <a href="?status=pending" class="px-4 py-2 <?php echo $status_filter == 'pending' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-clock mr-2"></i>
                        Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                    </a>
                    <a href="?status=approved" class="px-4 py-2 <?php echo $status_filter == 'approved' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        Approved (<?php echo $status_counts['approved'] ?? 0; ?>)
                    </a>
                    <a href="?status=rejected" class="px-4 py-2 <?php echo $status_filter == 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?> rounded-lg transition-all font-medium">
                        <i class="fas fa-times-circle mr-2"></i>
                        Rejected (<?php echo $status_counts['rejected'] ?? 0; ?>)
                    </a>
                </div>
            </div>

            <!-- Verifications List -->
            <div class="space-y-6">
                <?php if (count($verifications) > 0): ?>
                    <?php foreach ($verifications as $verification): ?>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl shadow-lg overflow-hidden hover:border-blue-500/50 transition-all">
                        <!-- Header -->
                        <div class="p-6 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-800/50">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold text-lg mr-4">
                                        <?php echo strtoupper(substr($verification['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-white">
                                            <?php echo htmlspecialchars($verification['full_name']); ?>
                                        </h3>
                                        <div class="flex items-center gap-3 text-sm text-gray-400 mt-1">
                                            <span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($verification['username']); ?></span>
                                            <span><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($verification['email']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $status_colors = [
                                    'pending' => 'bg-orange-500/20 text-orange-400 border-orange-500/50',
                                    'approved' => 'bg-green-500/20 text-green-400 border-green-500/50',
                                    'rejected' => 'bg-red-500/20 text-red-400 border-red-500/50'
                                ];
                                $color = $status_colors[$verification['status']] ?? 'bg-gray-500/20 text-gray-400';
                                ?>
                                <span class="px-4 py-2 rounded-full text-sm font-bold border <?php echo $color; ?>">
                                    <?php 
                                    if ($verification['status'] == 'pending') echo '⏳ ';
                                    if ($verification['status'] == 'approved') echo '✓ ';
                                    if ($verification['status'] == 'rejected') echo '✗ ';
                                    echo ucfirst($verification['status']); 
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="p-6 bg-gray-800/50">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="p-4 bg-gray-800 rounded-lg">
                                    <p class="text-xs text-gray-400 uppercase mb-1">Submitted</p>
                                    <p class="text-white font-semibold"><?php echo date('M j, Y g:i A', strtotime($verification['submitted_at'])); ?></p>
                                </div>
                                <div class="p-4 bg-gray-800 rounded-lg">
                                    <p class="text-xs text-gray-400 uppercase mb-1">Date of Birth</p>
                                    <p class="text-white font-semibold">
                                        <?php 
                                        $dob = new DateTime($verification['date_of_birth']);
                                        $now = new DateTime();
                                        $age = $now->diff($dob)->y;
                                        echo date('M j, Y', strtotime($verification['date_of_birth'])) . " (Age: $age)";
                                        ?>
                                    </p>
                                </div>
                                <div class="p-4 bg-gray-800 rounded-lg">
                                    <p class="text-xs text-gray-400 uppercase mb-1">Country</p>
                                    <p class="text-white font-semibold"><?php echo htmlspecialchars($verification['country']); ?></p>
                                </div>
                                <div class="p-4 bg-gray-800 rounded-lg">
                                    <p class="text-xs text-gray-400 uppercase mb-1">Document Type</p>
                                    <p class="text-white font-semibold"><?php echo ucwords(str_replace('_', ' ', $verification['document_type'])); ?></p>
                                </div>
                            </div>

                            <!-- Images -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div class="group relative cursor-pointer" onclick="openModal('<?php echo htmlspecialchars($verification['document_front_path']); ?>')">
                                    <img src="../<?php echo htmlspecialchars($verification['document_front_path']); ?>" alt="Document Front" class="w-full h-48 object-cover rounded-lg border-2 border-gray-700 group-hover:border-blue-500 transition-all">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                        <i class="fas fa-search-plus text-white text-2xl"></i>
                                    </div>
                                    <p class="text-center text-sm text-gray-400 mt-2"><i class="fas fa-id-card mr-1"></i>Document Front</p>
                                </div>

                                <?php if ($verification['document_back_path']): ?>
                                <div class="group relative cursor-pointer" onclick="openModal('<?php echo htmlspecialchars($verification['document_back_path']); ?>')">
                                    <img src="../<?php echo htmlspecialchars($verification['document_back_path']); ?>" alt="Document Back" class="w-full h-48 object-cover rounded-lg border-2 border-gray-700 group-hover:border-blue-500 transition-all">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                        <i class="fas fa-search-plus text-white text-2xl"></i>
                                    </div>
                                    <p class="text-center text-sm text-gray-400 mt-2"><i class="fas fa-id-card mr-1"></i>Document Back</p>
                                </div>
                                <?php endif; ?>

                                <div class="group relative cursor-pointer" onclick="openModal('<?php echo htmlspecialchars($verification['selfie_path']); ?>')">
                                    <img src="../<?php echo htmlspecialchars($verification['selfie_path']); ?>" alt="Selfie" class="w-full h-48 object-cover rounded-lg border-2 border-gray-700 group-hover:border-blue-500 transition-all">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center">
                                        <i class="fas fa-search-plus text-white text-2xl"></i>
                                    </div>
                                    <p class="text-center text-sm text-gray-400 mt-2"><i class="fas fa-camera mr-1"></i>Selfie with ID</p>
                                </div>
                            </div>

                            <?php if ($verification['status'] == 'pending'): ?>
                            <!-- Action Form -->
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="verification_id" value="<?php echo $verification['id']; ?>">

                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Admin Notes (Optional)</label>
                                    <textarea name="admin_notes" rows="2" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Internal notes about this verification..."></textarea>
                                </div>

                                <div id="reject_reason_<?php echo $verification['id']; ?>" style="display: none;">
                                    <label class="block text-sm font-medium text-red-400 mb-2">Rejection Reason (Required) *</label>
                                    <textarea name="rejection_reason" rows="3" class="w-full px-4 py-2 bg-gray-700 border border-red-500/50 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-red-500" placeholder="Explain why this verification is being rejected..."></textarea>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    <button type="submit" name="approve_verification" class="flex-1 sm:flex-none px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold rounded-lg transition-all transform hover:scale-105">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Approve Verification
                                    </button>
                                    <button type="button" onclick="showRejectReason(<?php echo $verification['id']; ?>)" class="flex-1 sm:flex-none px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-semibold rounded-lg transition-all transform hover:scale-105">
                                        <i class="fas fa-times-circle mr-2"></i>
                                        Reject Verification
                                    </button>
                                    <a href="../profile.php?id=<?php echo $verification['user_id']; ?>" target="_blank" class="flex-1 sm:flex-none px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all text-center">
                                        <i class="fas fa-user mr-2"></i>
                                        View Profile
                                    </a>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl p-12 text-center">
                        <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-2xl font-bold text-white mb-2">No Verifications Found</h3>
                        <p class="text-gray-400">There are no verification requests in this category</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-8 gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" 
                       class="px-4 py-2 <?php echo $i == $page ? 'bg-blue-600' : 'bg-gray-800 hover:bg-gray-700'; ?> text-white rounded-lg">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 z-50 hidden bg-black/90 flex items-center justify-center p-4" onclick="closeModal()">
        <div class="relative max-w-6xl max-h-full">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-white bg-red-500 hover:bg-red-600 rounded-full w-10 h-10 flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" alt="Verification Document" class="max-w-full max-h-[90vh] rounded-lg">
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js"></script>

    <script>
        function showRejectReason(verificationId) {
            const rejectDiv = document.getElementById('reject_reason_' + verificationId);
            rejectDiv.style.display = 'block';

            const form = rejectDiv.closest('form');
            const rejectBtn = form.querySelector('[onclick]');
            rejectBtn.type = 'submit';
            rejectBtn.name = 'reject_verification';
            rejectBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i> Confirm Rejection';
            rejectBtn.onclick = null;
        }

        function openModal(src) {
            const modal = document.getElementById('imageModal');
            document.getElementById('modalImage').src = '../' + src;
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('imageModal').classList.add('hidden');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

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
