<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();
$location = new Location($db);

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Get featured cities
$featured_cities = [];
try {
    $query = "
        SELECT c.*, s.abbreviation as state_abbr,
               (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active') as listing_count
        FROM cities c
        LEFT JOIN states s ON c.state_id = s.id
        ORDER BY listing_count DESC
        LIMIT 8
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $featured_cities = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
}

// Get recent listings
$recent_listings = [];
try {
    $query = "
        SELECT l.*, c.name as city_name, s.abbreviation as state_abbr, cat.name as category_name
        FROM listings l
        LEFT JOIN cities c ON l.city_id = c.id
        LEFT JOIN states s ON c.state_id = s.id
        LEFT JOIN categories cat ON l.category_id = cat.id
        WHERE l.status = 'active'
        ORDER BY l.created_at DESC
        LIMIT 6
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_listings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
}

// Get recent stories
$recent_stories = [];
try {
    $query = "
        SELECT s.*, u.username as author_name
        FROM stories s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.status = 'approved'
        ORDER BY s.created_at DESC
        LIMIT 6
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_stories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
}

// Get featured creators
$featured_creators = [];
try {
    $query = "
        SELECT u.*, COUNT(DISTINCT ml.id) as listing_count
        FROM users u
        LEFT JOIN marketplace_listings ml ON u.id = ml.creator_id AND ml.status = 'active'
        WHERE u.is_creator = 1
        GROUP BY u.id
        ORDER BY listing_count DESC
        LIMIT 6
    ";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $featured_creators = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
}

// Get stats
try {
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM listings WHERE status = 'active') as total_listings,
            (SELECT COUNT(DISTINCT city_id) FROM listings WHERE status = 'active') as active_cities,
            (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as new_users_today
    ";
    $stats_stmt = $db->query($stats_query);
    $stats = $stats_stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total_listings' => 0, 'active_cities' => 0, 'new_users_today' => 0];
}

include 'views/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- ANIMATED BACKGROUND STYLES - Now with Hearts! -->
<!-- ═══════════════════════════════════════════════════════════════════ -->
<style>
/* Animated starfield background */
.stars-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #0f0f0f;
    z-index: -1;
    overflow: hidden;
}

/* Regular stars */
.star {
    position: absolute;
    background: white;
    border-radius: 50%;
    animation: twinkle 3s infinite;
}

@keyframes twinkle {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.2); }
}

@keyframes heartFloat {
    0%, 100% { 
        opacity: 0.4; 
        transform: translateY(0) scale(1) rotate(0deg); 
    }
    50% { 
        opacity: 0.8; 
        transform: translateY(-20px) scale(1.1) rotate(5deg); 
    }
}

/* Create multiple stars with different positions and delays */
<?php for($i = 1; $i <= 40; $i++): ?>
.star:nth-child(<?php echo $i; ?>) {
    top: <?php echo rand(0, 100); ?>%;
    left: <?php echo rand(0, 100); ?>%;
    width: <?php echo rand(1, 3); ?>px;
    height: <?php echo rand(1, 3); ?>px;
    animation-delay: <?php echo rand(0, 30) / 10; ?>s;
    animation-duration: <?php echo rand(20, 40) / 10; ?>s;
}
<?php endfor; ?>

/* Heart emoji positions and animations */
<?php 
$heart_colors = ['💗', '💖', '💕', '💓', '💝', '❤️', '💘'];
for($i = 1; $i <= 15; $i++): 
?>
.heart:nth-child(<?php echo $i + 40; ?>) {
    top: <?php echo rand(0, 100); ?>%;
    left: <?php echo rand(0, 100); ?>%;
    animation-delay: <?php echo rand(0, 40) / 10; ?>s;
    animation-duration: <?php echo rand(30, 60) / 10; ?>s;
}
.heart:nth-child(<?php echo $i + 40; ?>)::before {
    content: "<?php echo $heart_colors[array_rand($heart_colors)]; ?>";
}
<?php endfor; ?>

.floating-gradient {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
    animation: float 20s infinite;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(50px, -50px) scale(1.1); }
    66% { transform: translate(-50px, 50px) scale(0.9); }
}

.gradient-1 {
    top: 10%;
    left: 20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255, 0, 110, 0.3) 0%, transparent 70%);
    animation-delay: 0s;
}

.gradient-2 {
    top: 60%;
    right: 20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(139, 69, 255, 0.3) 0%, transparent 70%);
    animation-delay: 7s;
}

.gradient-3 {
    bottom: 10%;
    left: 10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(0, 255, 255, 0.2) 0%, transparent 70%);
    animation-delay: 14s;
}
</style>

<!-- Animated Background with Stars AND Hearts! -->
<div class="stars-bg">
    <?php 
    // 40 stars
    for($i = 0; $i < 40; $i++): 
    ?>
    <div class="star"></div>
    <?php endfor; ?>

    <?php 
    // 15 heart emojis
    for($i = 0; $i < 15; $i++): 
    ?>
    <div class="🔥"></div>
    <?php endfor; ?>

    <div class="floating-gradient gradient-1"></div>
    <div class="floating-gradient gradient-2"></div>
    <div class="floating-gradient gradient-3"></div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- HERO SECTION - Compact Size with BIGGER Logo -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<div class="relative min-h-[80vh] flex items-center">

  <div class="container mx-auto px-4 py-16">

    <div class="text-center max-w-3xl mx-auto">


      <!-- Night Mode Badge -->
      <div class="inline-flex items-center gap-2 bg-gh-panel border border-gh-border rounded-full px-4 py-2 mb-6">
        <i class="bi bi-moon-stars text-purple-400"></i>
        <span class="text-sm font-medium text-gh-muted uppercase tracking-wide">
          Night Mode · Adults Only
        </span>
      </div>

      <!-- Main Headline - SMALLER SIZE -->
      <h1 class="text-3xl md:text-4xl font-bold text-gh-fg mb-4 leading-tight">
        Tonight's Hookup is Already Here
      </h1>

      <!-- Subheadline - SMALLER SIZE -->
      <p class="text-base md:text-lg text-gh-muted mb-8 max-w-2xl mx-auto leading-relaxed">
        Real people. Real meetups. No catfish, no games, no waiting. 
        Browse verified personals in your city and connect with someone 
        who's ready right now.
      </p>

      <!-- CTA Buttons - NORMAL SIZE -->
      <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
        <a href="/post-ad.php" 
           class="bg-gh-accent text-white font-semibold px-6 py-3 rounded-lg hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
          <i class="bi bi-megaphone-fill"></i>
          Post a Free Ad
        </a>
        <a href="/browse.php" 
           class="bg-transparent border-2 border-gh-accent text-gh-accent font-semibold px-6 py-3 rounded-lg hover:bg-gh-accent hover:text-white transition-all flex items-center justify-center gap-2">
          <i class="bi bi-people-fill"></i>
          Browse Personals
        </a>
      </div>

      <!-- Trust Indicators - SMALLER SIZE -->
      <div class="flex flex-wrap justify-center gap-4 text-sm text-gh-muted">
        <div class="flex items-center gap-2">
          <i class="bi bi-shield-check text-green-500"></i>
          <span>100% Anonymous</span>
        </div>
        <div class="flex items-center gap-2">
          <i class="bi bi-patch-check-fill text-blue-500"></i>
          <span>Verified Members</span>
        </div>
        <div class="flex items-center gap-2">
          <i class="bi bi-clock-fill text-purple-500"></i>
          <span>Active 24/7</span>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- TONIGHT'S HEAT MAP -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<div class="relative py-12 bg-gh-bg/50 backdrop-blur-sm">
  <div class="container mx-auto px-4">
    <div class="max-w-4xl mx-auto">
      <div class="bg-gh-panel/80 backdrop-blur-md border border-gh-border rounded-xl p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-bold text-gh-fg uppercase tracking-wide">Tonight's Heat Map</h2>
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-sm text-green-500 font-medium">Live now</span>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div class="bg-gh-panel2/80 backdrop-blur-sm rounded-lg p-5 text-center border border-gh-border/50">
            <div class="text-xs text-gh-muted uppercase mb-2 font-medium">Active</div>
            <div class="text-3xl font-bold text-gh-fg mb-1"><?php echo e($stats['total_listings']); ?></div>
            <div class="text-sm text-gh-muted">Listings</div>
          </div>
          <div class="bg-gh-panel2/80 backdrop-blur-sm rounded-lg p-5 text-center border border-gh-border/50">
            <div class="text-xs text-gh-muted uppercase mb-2 font-medium">Cities</div>
            <div class="text-3xl font-bold text-gh-fg mb-1"><?php echo e($stats['active_cities']); ?></div>
            <div class="text-sm text-gh-muted">Live</div>
          </div>
          <div class="bg-gh-panel2/80 backdrop-blur-sm rounded-lg p-5 text-center border border-gh-border/50">
            <div class="text-xs text-gh-muted uppercase mb-2 font-medium">New</div>
            <div class="text-3xl font-bold text-gh-fg mb-1"><?php echo e($stats['new_users_today']); ?></div>
            <div class="text-sm text-gh-muted">Users/24h</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- FEATURES SECTION -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<div class="relative bg-gh-panel/50 backdrop-blur-sm py-12">
  <div class="container mx-auto px-4">

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

      <!-- Feature 1 -->
      <div class="bg-gh-panel2/80 backdrop-blur-md border border-gh-border rounded-xl p-6 hover:border-gh-accent transition-colors">
        <div class="w-12 h-12 bg-purple-500/10 rounded-lg flex items-center justify-center mb-4">
          <i class="bi bi-fire text-2xl text-purple-500"></i>
        </div>
        <h3 class="text-base font-bold text-gh-fg mb-2">Club Scene Blowing Up</h3>
        <p class="text-gh-muted text-sm">New "after party" ads tonight.</p>
      </div>

      <!-- Feature 2 -->
      <div class="bg-gh-panel2/80 backdrop-blur-md border border-gh-border rounded-xl p-6 hover:border-gh-accent transition-colors">
        <div class="w-12 h-12 bg-pink-500/10 rounded-lg flex items-center justify-center mb-4">
          <i class="bi bi-broadcast text-2xl text-pink-500"></i>
        </div>
        <h3 class="text-base font-bold text-gh-fg mb-2">Creators Going Live</h3>
        <p class="text-gh-muted text-sm">Exclusive content drops.</p>
      </div>

      <!-- Feature 3 -->
      <div class="bg-gh-panel2/80 backdrop-blur-md border border-gh-border rounded-xl p-6 hover:border-gh-accent transition-colors">
        <div class="w-12 h-12 bg-green-500/10 rounded-lg flex items-center justify-center mb-4">
          <i class="bi bi-shield-check text-2xl text-green-500"></i>
        </div>
        <h3 class="text-base font-bold text-gh-fg mb-2">Stay Safe & Discreet</h3>
        <p class="text-gh-muted text-sm">Built-in privacy tools.</p>
      </div>

      <!-- Feature 4 -->
      <div class="bg-gh-panel2/80 backdrop-blur-md border border-gh-border rounded-xl p-6 hover:border-gh-accent transition-colors">
        <div class="w-12 h-12 bg-blue-500/10 rounded-lg flex items-center justify-center mb-4">
          <i class="bi bi-geo-alt text-2xl text-blue-500"></i>
        </div>
        <h3 class="text-base font-bold text-gh-fg mb-2">Find Connections Near You</h3>
        <p class="text-gh-muted text-sm">Freshly posted personals.</p>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- RECENT LISTINGS -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<?php if(count($recent_listings) > 0): ?>
<div class="relative bg-gh-bg/50 backdrop-blur-sm py-12">
  <div class="container mx-auto px-4">

    <div class="flex items-center justify-between mb-8">
      <div>
        <h2 class="text-2xl font-bold text-gh-fg mb-2">Recent Personals</h2>
        <p class="text-gh-muted">Freshly posted in the last 24 hours</p>
      </div>
      <a href="/browse.php" class="text-gh-accent hover:underline flex items-center gap-2">
        View All <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach($recent_listings as $listing): ?>
      <a href="/listing.php?id=<?php echo e($listing['id']); ?>" 
         class="block bg-gh-panel/80 backdrop-blur-md border border-gh-border rounded-xl p-5 hover:border-gh-accent transition-colors">
        <div class="flex items-start justify-between mb-3">
          <span class="text-xs bg-gh-accent/10 text-gh-accent px-2 py-1 rounded">
            <?php echo e($listing['category_name']); ?>
          </span>
          <span class="text-xs text-gh-muted">
            <?php echo e($listing['city_name']); ?>, <?php echo e($listing['state_abbr']); ?>
          </span>
        </div>
        <h3 class="text-base font-semibold text-gh-fg mb-2 line-clamp-1">
          <?php echo e($listing['title']); ?>
        </h3>
        <p class="text-gh-muted text-sm line-clamp-2">
          <?php echo e($listing['description']); ?>
        </p>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- RECENT STORIES -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<?php if(count($recent_stories) > 0): ?>
<div class="relative bg-gh-panel/50 backdrop-blur-sm py-12">
  <div class="container mx-auto px-4">

    <div class="flex items-center justify-between mb-8">
      <div>
        <h2 class="text-2xl font-bold text-gh-fg mb-2">Authentic Hookup Experiences</h2>
        <p class="text-gh-muted">Real stories from the community</p>
      </div>
      <a href="/story.php" class="text-gh-accent hover:underline flex items-center gap-2">
        Read More <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach($recent_stories as $story): ?>
      <a href="/story-view.php?id=<?php echo e($story['id']); ?>" 
         class="block bg-gh-panel2/80 backdrop-blur-md border border-gh-border rounded-xl p-5 hover:border-gh-accent transition-colors">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 bg-gh-accent/10 rounded-full flex items-center justify-center">
            <i class="bi bi-person text-gh-accent"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gh-fg">
              <?php echo e($story['author_name'] ?? 'Anonymous'); ?>
            </div>
            <div class="text-xs text-gh-muted">
              <?php echo e($story['category']); ?>
            </div>
          </div>
        </div>
        <h3 class="text-base font-semibold text-gh-fg mb-2 line-clamp-2">
          <?php echo e($story['title']); ?>
        </h3>
        <p class="text-gh-muted text-sm line-clamp-3">
          <?php echo e(substr($story['content'], 0, 150)); ?>...
        </p>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- FEATURED CITIES -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<?php if(count($featured_cities) > 0): ?>
<div class="relative bg-gh-bg/50 backdrop-blur-sm py-12">
  <div class="container mx-auto px-4">

    <div class="text-center mb-8">
      <h2 class="text-2xl font-bold text-gh-fg mb-2">Browse by City</h2>
      <p class="text-gh-muted">Find personals in your area</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
      <?php foreach($featured_cities as $city): ?>
      <a href="/city.php?slug=<?php echo e($city['slug']); ?>" 
         class="bg-gh-panel/80 backdrop-blur-md border border-gh-border rounded-lg p-4 hover:border-gh-accent transition-colors text-center">
        <div class="text-2xl mb-2">📍</div>
        <div class="text-sm font-medium text-gh-fg mb-1"><?php echo e($city['name']); ?></div>
        <div class="text-xs text-gh-muted"><?php echo e($city['listing_count']); ?> ads</div>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<!-- CTA SECTION -->
<!-- ═══════════════════════════════════════════════════════════════════ -->

<div class="relative bg-gradient-to-r from-purple-900/20 to-pink-900/20 backdrop-blur-sm py-16">
  <div class="container mx-auto px-4 text-center">
    <h2 class="text-3xl font-bold text-gh-fg mb-4">Ready to Connect?</h2>
    <p class="text-lg text-gh-muted mb-8 max-w-2xl mx-auto">
      Join thousands of verified members. Post your ad or browse personals—it's free, fast, and discreet.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <a href="/register.php" 
         class="bg-gh-accent text-white font-semibold px-6 py-3 rounded-lg hover:opacity-90 transition-opacity">
        Sign Up Free
      </a>
      <a href="/browse.php" 
         class="bg-transparent border-2 border-white text-white font-semibold px-6 py-3 rounded-lg hover:bg-white hover:text-gh-bg transition-all">
        Start Browsing
      </a>
    </div>
  </div>
</div>

<?php include 'views/footer.php'; ?>
