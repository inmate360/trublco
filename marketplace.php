<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$content_type = $_GET['content_type'] ?? '';
$sort = $_GET['sort'] ?? 'recent';

// At the top after session_start()
if (!isset($_SESSION['age_verified']) || !$_SESSION['age_verified']) {
    // Check if user has verified age
    $verifyQuery = "SELECT age_verified FROM users WHERE id = :uid";
    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->bindParam(':uid', $_SESSION['user_id']);
    $verifyStmt->execute();
    $user = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['age_verified']) {
        header('Location: verify-identity.php');
        exit;
    }
    
    $_SESSION['age_verified'] = true;
}


// Build query
$query = "SELECT 
            cl.*, 
            u.username, 
            u.verified, 
            u.creator,
            (SELECT file_path FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as creator_avatar,
            (SELECT COUNT(*) FROM creator_purchases WHERE listing_id = cl.id) as total_sales,
            (SELECT AVG(rating) FROM creator_reviews WHERE listing_id = cl.id) as avg_rating,
            (SELECT COUNT(*) FROM creator_reviews WHERE listing_id = cl.id) as review_count
          FROM creator_listings cl
          LEFT JOIN users u ON cl.creator_id = u.id
          WHERE cl.status = 'active'";

$params = [];

if (!empty($search)) {
    $query .= " AND (cl.title LIKE :search OR cl.description LIKE :search OR cl.tags LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND cl.category = :category";
    $params[':category'] = $category;
}

if (!empty($content_type)) {
    $query .= " AND cl.content_type = :content_type";
    $params[':content_type'] = $content_type;
}

if (!empty($price_min)) {
    $query .= " AND cl.price >= :price_min";
    $params[':price_min'] = $price_min;
}

if (!empty($price_max)) {
    $query .= " AND cl.price <= :price_max";
    $params[':price_max'] = $price_max;
}

// Sorting
switch($sort) {
    case 'price_low':
        $query .= " ORDER BY cl.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY cl.price DESC";
        break;
    case 'popular':
        $query .= " ORDER BY total_sales DESC";
        break;
    case 'rating':
        $query .= " ORDER BY avg_rating DESC";
        break;
    default:
        $query .= " ORDER BY cl.created_at DESC";
}

$query .= " LIMIT 50";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categoryQuery = "SELECT DISTINCT category FROM creator_listings WHERE status = 'active' AND category IS NOT NULL ORDER BY category";
$categoryStmt = $db->prepare($categoryQuery);
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get stats
$creatorQuery = "SELECT COUNT(DISTINCT creator_id) FROM creator_listings WHERE status = 'active'";
$creatorStmt = $db->prepare($creatorQuery);
$creatorStmt->execute();
$totalCreators = $creatorStmt->fetchColumn();

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');
</style>

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-7xl px-4">

    <!-- Header -->
    <div class="mb-8 text-center">
      <div class="mb-3">
        <a href="index.php" class="inline-block">
          <h1 class="bg-gradient-to-r from-gh-accent via-gh-success to-gh-accent bg-clip-text text-4xl font-bold text-transparent sm:text-5xl" style="font-family: 'Pacifico', cursive;">
            trubl
          </h1>
        </a>
      </div>
      <div class="mb-4 flex items-center justify-center gap-2">
        <i class="bi bi-shop text-2xl text-pink-500"></i>
        <h2 class="text-2xl font-bold text-gh-fg">Creator Marketplace</h2>
      </div>
      <p class="text-gh-muted">Discover exclusive content from verified creators</p>
    </div>

    <!-- Creator Dashboard/Become Creator Banner -->
    <?php if(isset($_SESSION['user_id'])): ?>
        <?php
        $creatorCheck = "SELECT creator FROM users WHERE id = :user_id";
        $stmt = $db->prepare($creatorCheck);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $isCreator = $stmt->fetchColumn();
        ?>
        
        <?php if($isCreator): ?>
        <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="mb-1 flex items-center gap-2 text-lg font-bold text-gh-fg">
                <i class="bi bi-star-fill text-yellow-500"></i>
                Creator Dashboard
              </h3>
              <p class="text-sm text-gh-muted">Manage your listings, view analytics, and track your earnings</p>
            </div>
            <a href="creator-dashboard.php" class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-4 py-2 font-semibold text-white transition-all hover:brightness-110">
              <i class="bi bi-speedometer2"></i>
              Dashboard
            </a>
          </div>
        </div>
        <?php else: ?>
        <div class="mb-6 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-center">
          <h3 class="mb-2 text-2xl font-bold text-white">
            <i class="bi bi-star-fill"></i>
            Become a Creator
          </h3>
          <p class="mb-4 text-white/90">
            Start selling your exclusive content and earn money doing what you love
          </p>
          <a href="become-creator.php" class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 font-semibold text-purple-600 transition-all hover:bg-gray-100">
            <i class="bi bi-rocket-takeoff-fill"></i>
            Apply Now
          </a>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Stats Banner -->
    <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-4">
      <div class="flex items-center justify-between text-center">
        <div class="flex-1">
          <div class="text-2xl font-bold text-gh-accent"><?php echo count($listings); ?></div>
          <div class="text-xs text-gh-muted">Active Listings</div>
        </div>
        <div class="h-10 w-px bg-gh-border"></div>
        <div class="flex-1">
          <div class="text-2xl font-bold text-pink-500"><?php echo $totalCreators; ?></div>
          <div class="text-xs text-gh-muted">Creators</div>
        </div>
        <div class="h-10 w-px bg-gh-border"></div>
        <div class="flex-1">
          <div class="text-2xl font-bold text-purple-500"><?php echo count($categories); ?></div>
          <div class="text-xs text-gh-muted">Categories</div>
        </div>
      </div>
    </div>

    <!-- Search and Filters -->
    <form method="GET" class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-6">
      <!-- Search Bar -->
      <div class="mb-4 flex gap-2">
        <div class="relative flex-1">
          <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-gh-muted"></i>
          <input type="text" 
                 name="search" 
                 placeholder="Search for content, creators, tags..."
                 value="<?php echo htmlspecialchars($search); ?>"
                 class="w-full rounded-lg border border-gh-border bg-gh-bg py-3 pl-12 pr-4 text-gh-fg focus:border-gh-accent focus:outline-none">
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-3 font-semibold text-white transition-all hover:brightness-110">
          <i class="bi bi-search"></i>
          Search
        </button>
      </div>
      <div class="flex justify-end mb-4">
        <div class="text-[10px] text-gh-muted font-medium flex items-center gap-1">
          <i class="bi bi-shield-check text-gh-accent"></i>
          Protected By AI Content Moderation 🛡️
        </div>
      </div>

      <!-- Filters -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <label class="mb-2 block text-sm font-semibold text-gh-fg">
            <i class="bi bi-grid mr-1"></i>Category
          </label>
          <select name="category" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(ucfirst($cat)); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="mb-2 block text-sm font-semibold text-gh-fg">
            <i class="bi bi-file-earmark mr-1"></i>Content Type
          </label>
          <select name="content_type" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="photo" <?php echo $content_type == 'photo' ? 'selected' : ''; ?>>Photos</option>
            <option value="video" <?php echo $content_type == 'video' ? 'selected' : ''; ?>>Videos</option>
            <option value="audio" <?php echo $content_type == 'audio' ? 'selected' : ''; ?>>Audio</option>
            <option value="document" <?php echo $content_type == 'document' ? 'selected' : ''; ?>>Documents</option>
            <option value="set" <?php echo $content_type == 'set' ? 'selected' : ''; ?>>Content Sets</option>
          </select>
        </div>

        <div>
          <label class="mb-2 block text-sm font-semibold text-gh-fg">
            <i class="bi bi-cash mr-1"></i>Min Price
          </label>
          <input type="number" name="price_min" placeholder="$0" value="<?php echo htmlspecialchars($price_min); ?>" step="0.01" min="0"
                 class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
        </div>

        <div>
          <label class="mb-2 block text-sm font-semibold text-gh-fg">
            <i class="bi bi-cash-stack mr-1"></i>Max Price
          </label>
          <input type="number" name="price_max" placeholder="Any" value="<?php echo htmlspecialchars($price_max); ?>" step="0.01" min="0"
                 class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none">
        </div>

        <div>
          <label class="mb-2 block text-sm font-semibold text-gh-fg">
            <i class="bi bi-sort-down mr-1"></i>Sort By
          </label>
          <select name="sort" class="w-full rounded-lg border border-gh-border bg-gh-bg px-4 py-2 text-gh-fg focus:border-gh-accent focus:outline-none" onchange="this.form.submit()">
            <option value="recent" <?php echo $sort == 'recent' ? 'selected' : ''; ?>>Most Recent</option>
            <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
          </select>
        </div>
      </div>

      <!-- Active Filters -->
      <?php if($search || $category || $content_type || $price_min || $price_max): ?>
      <div class="mt-4 flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold text-gh-muted">Active Filters:</span>
        
        <?php if($search): ?>
        <span class="inline-flex items-center gap-2 rounded-full border border-gh-border bg-gh-bg px-3 py-1 text-sm text-gh-fg">
          <i class="bi bi-search"></i>
          "<?php echo htmlspecialchars($search); ?>"
          <a href="?<?php echo http_build_query(array_diff_key($_GET, ['search' => ''])); ?>" class="text-red-500 hover:text-red-400">
            <i class="bi bi-x-lg"></i>
          </a>
        </span>
        <?php endif; ?>

        <?php if($category): ?>
        <span class="inline-flex items-center gap-2 rounded-full border border-gh-border bg-gh-bg px-3 py-1 text-sm text-gh-fg">
          <i class="bi bi-grid"></i>
          <?php echo htmlspecialchars(ucfirst($category)); ?>
          <a href="?<?php echo http_build_query(array_diff_key($_GET, ['category' => ''])); ?>" class="text-red-500 hover:text-red-400">
            <i class="bi bi-x-lg"></i>
          </a>
        </span>
        <?php endif; ?>

        <?php if($content_type): ?>
        <span class="inline-flex items-center gap-2 rounded-full border border-gh-border bg-gh-bg px-3 py-1 text-sm text-gh-fg">
          <i class="bi bi-file-earmark"></i>
          <?php echo htmlspecialchars(ucfirst($content_type)); ?>
          <a href="?<?php echo http_build_query(array_diff_key($_GET, ['content_type' => ''])); ?>" class="text-red-500 hover:text-red-400">
            <i class="bi bi-x-lg"></i>
          </a>
        </span>
        <?php endif; ?>

        <a href="marketplace.php" class="text-sm font-semibold text-gh-accent hover:underline">Clear All</a>
      </div>
      <?php endif; ?>
    </form>

    <!-- Listings Grid -->
    <?php if(count($listings) > 0): ?>
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php foreach($listings as $listing): ?>
          <article class="group rounded-xl border border-gh-border bg-gh-panel transition-all hover:border-gh-accent hover:shadow-lg">
            <!-- Thumbnail -->
            <div class="card-thumbnail">
              <img src="<?php echo htmlspecialchars($listing['thumbnail'] ?? 'assets/images/default-content.jpg'); ?>" 
                   alt="<?php echo htmlspecialchars($listing['title']); ?>">
              <div class="thumbnail-overlay"></div>
              
              <!-- Content Type Badge -->
              <span class="absolute right-2 top-2 rounded-full bg-black/70 px-2 py-1 text-xs font-semibold text-white">
                <i class="bi bi-<?php echo $listing['content_type'] == 'photo' ? 'image' : ($listing['content_type'] == 'video' ? 'play-circle' : 'file-earmark'); ?>"></i>
                <?php echo ucfirst($listing['content_type']); ?>
              </span>
            </div>

            <!-- Content -->
            <div class="p-4">
              <!-- Creator Info -->
              <div class="mb-2 flex items-center gap-2">
                <?php if($listing['creator_avatar']): ?>
                  <img src="<?php echo htmlspecialchars($listing['creator_avatar']); ?>" 
                       alt="<?php echo htmlspecialchars($listing['username']); ?>"
                       class="h-6 w-6 rounded-full object-cover">
                <?php else: ?>
                  <div class="flex h-6 w-6 items-center justify-center rounded-full bg-gh-accent/20">
                    <i class="bi bi-person-fill text-xs text-gh-accent"></i>
                  </div>
                <?php endif; ?>
                <span class="text-sm font-semibold text-gh-fg"><?php echo htmlspecialchars($listing['username']); ?></span>
                <?php if($listing['verified']): ?>
                  <i class="bi bi-patch-check-fill text-sm text-blue-500"></i>
                <?php endif; ?>
              </div>

              <!-- Title -->
              <a href="marketlisting.php?id=<?php echo $listing['id']; ?>">
                <h3 class="mb-2 line-clamp-2 font-bold text-gh-fg group-hover:text-gh-accent">
                  <?php echo htmlspecialchars($listing['title']); ?>
                </h3>
              </a>

              <!-- Description -->
              <p class="mb-3 line-clamp-2 text-sm text-gh-muted">
                <?php echo htmlspecialchars(substr($listing['description'], 0, 100)); ?>
              </p>

              <!-- Stats -->
              <div class="mb-3 flex items-center gap-3 text-xs text-gh-muted">
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-eye-fill"></i>
                  <?php echo number_format($listing['views'] ?? 0); ?>
                </span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-cart-fill"></i>
                  <?php echo number_format($listing['total_sales']); ?>
                </span>
                <?php if($listing['avg_rating']): ?>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-star-fill text-yellow-500"></i>
                  <?php echo number_format($listing['avg_rating'], 1); ?>
                </span>
                <?php endif; ?>
              </div>

              <!-- Price and Action -->
              <div class="flex items-center justify-between">
                <div class="text-2xl font-bold text-gh-accent">
                  $<?php echo number_format($listing['price'], 2); ?>
                </div>
                <a href="marketlisting.php?id=<?php echo $listing['id']; ?>" 
                   class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white transition-all hover:brightness-110">
                  View Details
                  <i class="bi bi-arrow-right"></i>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
        <i class="bi bi-inbox text-6xl text-gh-muted opacity-20"></i>
        <h3 class="mt-4 text-xl font-bold text-gh-fg">No Listings Found</h3>
        <p class="mt-2 text-gh-muted">Try adjusting your filters or search terms</p>
        <a href="marketplace.php" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-3 font-semibold text-white transition-all hover:brightness-110">
          Clear Filters
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<style>
.card-thumbnail {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--gh-bg, #0d1117);
  border-radius: 0.75rem 0.75rem 0 0;
}

.card-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.group:hover .card-thumbnail img {
  transform: scale(1.05);
}

.thumbnail-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 50%);
  opacity: 0;
  transition: opacity 0.3s;
}

.group:hover .thumbnail-overlay {
  opacity: 1;
}
</style>

<?php include 'views/footer.php'; ?>
