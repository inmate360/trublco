<?php
session_start();
require_once 'config/database.php';
require_once 'AwardsManager.php';

$database = new Database();
$db = $database->getConnection();
$awardsManager = new AwardsManager($db);

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;

// Get all awards grouped by category
$query = "SELECT * FROM awards WHERE is_active = 1 ORDER BY category, requirement_value ASC";
$stmt = $db->query($query);
$all_awards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group awards by category
$awards_by_category = [];
foreach($all_awards as $award) {
    $awards_by_category[$award['category']][] = $award;
}

// Get user's awards if logged in
$user_awards = [];
$user_progress = [];
if($is_logged_in) {
    $user_awards_data = $awardsManager->getUserAwards($user_id);
    foreach($user_awards_data as $award) {
        $user_awards[$award['id']] = $award;
    }
    $user_progress = $awardsManager->getUserProgress($user_id);
}

// Get leaderboard
$leaderboard = $awardsManager->getLeaderboard(10);

include 'views/header.php';
?>

<style>
.award-card {
    transition: all 0.3s ease;
}
.award-card:hover {
    transform: translateY(-2px);
}
.award-locked {
    opacity: 0.5;
    filter: grayscale(80%);
}
.award-unlocked {
    animation: pulse 0.5s ease-in-out;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
</style>

<div class="min-h-screen bg-gradient-to-b from-gh-bg to-gh-panel py-8">
  <div class="container mx-auto px-4 max-w-6xl">

    <!-- Header -->
    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gh-fg mb-4">
        <i class="bi bi-trophy-fill text-yellow-500"></i>
        Awards & Achievements
      </h1>
      <p class="text-gh-muted text-lg">
        Earn badges by being active! Post stories, create listings, engage in forums, and get likes.
      </p>
    </div>

    <?php if($is_logged_in): ?>
    <!-- User Stats -->
    <div class="bg-gh-panel rounded-xl border border-gh-border p-6 mb-8">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <?php
        $stmt = $db->prepare("SELECT total_points, award_count FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_stats = $stmt->fetch();
        ?>
        <div>
          <div class="text-3xl font-bold text-yellow-500"><?php echo $user_stats['total_points'] ?? 0; ?></div>
          <div class="text-sm text-gh-muted">Total Points</div>
        </div>
        <div>
          <div class="text-3xl font-bold text-gh-accent"><?php echo $user_stats['award_count'] ?? 0; ?></div>
          <div class="text-sm text-gh-muted">Awards Earned</div>
        </div>
        <div>
          <div class="text-3xl font-bold text-purple-500"><?php echo count($user_progress); ?></div>
          <div class="text-sm text-gh-muted">In Progress</div>
        </div>
        <div>
          <div class="text-3xl font-bold text-green-500"><?php echo count($all_awards); ?></div>
          <div class="text-sm text-gh-muted">Total Awards</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Awards by Category -->
    <?php 
    $category_names = [
        'stories' => 'Story Awards',
        'listings' => 'Listing Awards',
        'forum' => 'Forum Awards',
        'likes' => 'Like Awards',
        'special' => 'Special Awards'
    ];

    $category_icons = [
        'stories' => 'bi-book',
        'listings' => 'bi-megaphone',
        'forum' => 'bi-chat',
        'likes' => 'bi-heart',
        'special' => 'bi-star'
    ];

    foreach($awards_by_category as $category => $awards): 
    ?>
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-gh-fg mb-4 flex items-center gap-2">
        <i class="<?php echo $category_icons[$category] ?? 'bi-trophy'; ?>"></i>
        <?php echo $category_names[$category] ?? ucfirst($category); ?>
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach($awards as $award): 
          $is_earned = isset($user_awards[$award['id']]);
          $earned_date = $is_earned ? $user_awards[$award['id']]['earned_at'] : null;
        ?>
        <div class="award-card bg-gh-panel border-2 <?php echo $is_earned ? 'border-gh-accent' : 'border-gh-border'; ?> rounded-xl p-5 <?php echo $is_earned ? 'award-unlocked' : 'award-locked'; ?>">
          <div class="flex items-start gap-3">
            <!-- Icon -->
            <div class="shrink-0 w-14 h-14 rounded-full flex items-center justify-center" style="background: <?php echo $award['color']; ?>15;">
              <i class="<?php echo $award['icon']; ?> text-2xl" style="color: <?php echo $award['color']; ?>;"></i>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-gh-fg mb-1 flex items-center gap-2">
                <?php echo htmlspecialchars($award['name']); ?>
                <?php if($is_earned): ?>
                <i class="bi bi-check-circle-fill text-green-500 text-sm"></i>
                <?php endif; ?>
              </h3>
              <p class="text-sm text-gh-muted mb-2"><?php echo htmlspecialchars($award['description']); ?></p>

              <div class="flex items-center justify-between text-xs">
                <span class="text-yellow-500 font-semibold">
                  <i class="bi bi-star-fill"></i> <?php echo $award['points']; ?> pts
                </span>
                <?php if($is_earned): ?>
                <span class="text-green-500">
                  <i class="bi bi-calendar-check"></i> 
                  <?php echo date('M j, Y', strtotime($earned_date)); ?>
                </span>
                <?php else: ?>
                <span class="text-gh-muted">
                  <?php echo $award['requirement_value']; ?> 
                  <?php echo str_replace('_', ' ', $award['requirement_type']); ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Leaderboard -->
    <div class="bg-gh-panel rounded-xl border border-gh-border p-6 mt-8">
      <h2 class="text-2xl font-bold text-gh-fg mb-6 flex items-center gap-2">
        <i class="bi bi-trophy"></i>
        Top Members
      </h2>

      <div class="space-y-3">
        <?php foreach($leaderboard as $index => $member): ?>
        <div class="flex items-center gap-4 p-4 bg-gh-bg rounded-lg hover:bg-gh-panel2 transition-colors">
          <div class="text-2xl font-bold <?php 
            if($index == 0) echo 'text-yellow-500';
            elseif($index == 1) echo 'text-gray-400';
            elseif($index == 2) echo 'text-orange-600';
            else echo 'text-gh-muted';
          ?>">
            #<?php echo $index + 1; ?>
          </div>

          <div class="flex-1">
            <div class="font-semibold text-gh-fg"><?php echo htmlspecialchars($member['username']); ?></div>
            <div class="text-sm text-gh-muted">
              <?php echo $member['story_count']; ?> stories • 
              <?php echo $member['listing_count']; ?> listings
            </div>
          </div>

          <div class="text-right">
            <div class="text-xl font-bold text-yellow-500"><?php echo $member['total_points']; ?></div>
            <div class="text-xs text-gh-muted"><?php echo $member['award_count']; ?> awards</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if(!$is_logged_in): ?>
    <!-- Call to Action -->
    <div class="text-center mt-12 p-8 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-xl border border-purple-500/20">
      <h3 class="text-2xl font-bold text-gh-fg mb-3">Start Earning Awards!</h3>
      <p class="text-gh-muted mb-6">Join our community to unlock achievements and earn points.</p>
      <a href="login.php" class="inline-block px-8 py-3 bg-gh-accent text-white rounded-lg font-semibold hover:bg-gh-accent/90 transition-colors">
        Sign Up / Login
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php include 'views/footer.php'; ?>
