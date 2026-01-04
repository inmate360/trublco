<?php
/**
 * Profile Badges Component
 * Include this on user profile pages to display their awards
 * Usage: include 'includes/profile-badges.php';
 * Make sure $user_id is set before including
 */

if(!isset($user_id)) {
    return;
}

require_once 'AwardsManager.php';
$awardsManager = new AwardsManager($db);

// Get user's displayed awards (limit to top 6 for profile display)
$user_awards = $awardsManager->getUserAwards($user_id, true);
$display_awards = array_slice($user_awards, 0, 6);

// Get user's total points and award count
$stmt = $db->prepare("SELECT total_points, award_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch();
?>

<?php if(count($display_awards) > 0): ?>
<div class="bg-gh-panel border border-gh-border rounded-xl p-5">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-bold text-gh-fg flex items-center gap-2">
      <i class="bi bi-trophy-fill text-yellow-500"></i>
      Awards & Achievements
    </h3>
    <?php if($user_stats && $user_stats['total_points'] > 0): ?>
    <div class="text-sm">
      <span class="text-yellow-500 font-bold"><?php echo $user_stats['total_points']; ?></span>
      <span class="text-gh-muted">pts</span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Award Badges Grid -->
  <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-3">
    <?php foreach($display_awards as $award): ?>
    <div class="group relative">
      <div class="aspect-square rounded-lg flex items-center justify-center border-2 border-gh-border bg-gh-bg hover:border-gh-accent transition-all cursor-pointer" 
           style="background: linear-gradient(135deg, <?php echo $award['color']; ?>15, <?php echo $award['color']; ?>05);"
           title="<?php echo htmlspecialchars($award['name']); ?>">
        <i class="<?php echo $award['icon']; ?> text-2xl sm:text-3xl" style="color: <?php echo $award['color']; ?>;"></i>
      </div>

      <!-- Tooltip -->
      <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
        <div class="font-semibold"><?php echo htmlspecialchars($award['name']); ?></div>
        <div class="text-gray-300"><?php echo $award['points']; ?> points</div>
        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- View All Link -->
  <?php if($user_stats && $user_stats['award_count'] > 6): ?>
  <a href="awards.php" class="block text-center text-sm text-gh-accent hover:text-gh-fg transition-colors">
    View all <?php echo $user_stats['award_count']; ?> awards →
  </a>
  <?php elseif(count($display_awards) > 0): ?>
  <a href="awards.php" class="block text-center text-sm text-gh-accent hover:text-gh-fg transition-colors">
    View all awards →
  </a>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- No Awards Yet -->
<div class="bg-gh-panel border border-gh-border rounded-xl p-5 text-center">
  <i class="bi bi-trophy text-4xl text-gh-muted mb-2"></i>
  <p class="text-gh-muted text-sm">No awards earned yet</p>
  <a href="awards.php" class="inline-block mt-2 text-sm text-gh-accent hover:text-gh-fg transition-colors">
    See available awards →
  </a>
</div>
<?php endif; ?>
