<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$listing_id = $_GET['id'] ?? 0;

// Get listing with creator info
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
          WHERE cl.id = :listing_id AND cl.status = 'active'";

$stmt = $db->prepare($query);
$stmt->bindParam(':listing_id', $listing_id);
$stmt->execute();
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$listing) {
    header('Location: marketplace.php');
    exit();
}

// Increment view count
$updateViews = "UPDATE creator_listings SET views = views + 1 WHERE id = :id";
$stmt = $db->prepare($updateViews);
$stmt->bindParam(':id', $listing_id);
$stmt->execute();

// Check if user has purchased
$hasPurchased = false;
if(isset($_SESSION['user_id'])) {
    $purchaseCheck = "SELECT id FROM creator_purchases WHERE buyer_id = :buyer_id AND listing_id = :listing_id";
    $stmt = $db->prepare($purchaseCheck);
    $stmt->bindParam(':buyer_id', $_SESSION['user_id']);
    $stmt->bindParam(':listing_id', $listing_id);
    $stmt->execute();
    $hasPurchased = $stmt->rowCount() > 0;
}

// Get reviews
$reviewsQuery = "SELECT cr.*, u.username, u.avatar 
                 FROM creator_reviews cr
                 LEFT JOIN users u ON cr.user_id = u.id
                 WHERE cr.listing_id = :listing_id
                 ORDER BY cr.created_at DESC
                 LIMIT 10";
$stmt = $db->prepare($reviewsQuery);
$stmt->bindParam(':listing_id', $listing_id);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get more from this creator
$moreQuery = "SELECT * FROM creator_listings 
              WHERE creator_id = :creator_id AND id != :listing_id AND status = 'active'
              ORDER BY created_at DESC LIMIT 4";
$stmt = $db->prepare($moreQuery);
$stmt->bindParam(':creator_id', $listing['creator_id']);
$stmt->bindParam(':listing_id', $listing_id);
$stmt->execute();
$moreListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get creator listing count
$creatorListings = "SELECT COUNT(*) FROM creator_listings WHERE creator_id = :creator_id AND status = 'active'";
$stmt = $db->prepare($creatorListings);
$stmt->bindParam(':creator_id', $listing['creator_id']);
$stmt->execute();
$listingCount = $stmt->fetchColumn();

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');
</style>

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-7xl px-4">

    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center gap-2 text-sm text-gh-muted">
      <a href="marketplace.php" class="text-gh-accent transition-colors hover:underline">Marketplace</a>
      <i class="bi bi-chevron-right"></i>
      <a href="marketplace.php?category=<?php echo urlencode($listing['category']); ?>" 
         class="text-gh-accent transition-colors hover:underline">
        <?php echo htmlspecialchars(ucfirst($listing['category'])); ?>
      </a>
      <i class="bi bi-chevron-right"></i>
      <span class="text-gh-fg"><?php echo htmlspecialchars($listing['title']); ?></span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      
      <!-- Main Content (Left 2 columns) -->
      <div class="lg:col-span-2">
        
        <!-- Media Preview -->
        <div class="mb-6 overflow-hidden rounded-xl border border-gh-border bg-gh-panel">
          <div class="relative aspect-video bg-gh-bg">
            <?php if($listing['is_premium'] ?? false): ?>
            <span class="absolute right-4 top-4 z-10 inline-flex items-center gap-2 rounded-full bg-purple-600 px-4 py-2 text-sm font-bold text-white">
              <i class="bi bi-gem"></i>
              Premium
            </span>
            <?php endif; ?>
            
            <img src="<?php echo htmlspecialchars($listing['thumbnail'] ?? 'assets/images/default-content.jpg'); ?>" 
                 alt="<?php echo htmlspecialchars($listing['title']); ?>"
                 class="h-full w-full object-contain">
          </div>
        </div>

        <!-- Details Section -->
        <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-6">
          <h1 class="mb-4 text-3xl font-bold text-gh-fg"><?php echo htmlspecialchars($listing['title']); ?></h1>
          
          <!-- Meta Info -->
          <div class="mb-6 flex flex-wrap items-center gap-4 border-b border-gh-border pb-6 text-sm text-gh-muted">
            <?php if($listing['avg_rating']): ?>
            <div class="flex items-center gap-2">
              <div class="flex items-center gap-1">
                <?php for($i = 0; $i < 5; $i++): ?>
                  <i class="bi bi-star-fill text-yellow-500"></i>
                <?php endfor; ?>
              </div>
              <span class="font-semibold text-gh-fg"><?php echo number_format($listing['avg_rating'], 1); ?></span>
              <span>(<?php echo $listing['review_count']; ?> reviews)</span>
            </div>
            <?php endif; ?>
            
            <span class="inline-flex items-center gap-1">
              <i class="bi bi-eye-fill"></i>
              <?php echo number_format($listing['views']); ?> views
            </span>
            
            <span class="inline-flex items-center gap-1">
              <i class="bi bi-cart-fill"></i>
              <?php echo number_format($listing['total_sales']); ?> sales
            </span>
            
            <span class="inline-flex items-center gap-1">
              <i class="bi bi-clock-fill"></i>
              <?php echo date('M j, Y', strtotime($listing['created_at'])); ?>
            </span>
          </div>
          
          <!-- Description -->
          <div class="mb-6">
            <h3 class="mb-3 text-lg font-bold text-gh-fg">Description</h3>
            <p class="whitespace-pre-wrap leading-relaxed text-gh-muted">
              <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
            </p>
          </div>
          
          <!-- Tags -->
          <?php if(!empty($listing['tags'])): ?>
          <div>
            <h3 class="mb-3 text-lg font-bold text-gh-fg">Tags</h3>
            <div class="flex flex-wrap gap-2">
              <?php 
              $tags = explode(',', $listing['tags']);
              foreach($tags as $tag): 
                $tag = trim($tag);
                if(!empty($tag)):
              ?>
                <span class="inline-flex items-center gap-1 rounded-full border border-gh-accent bg-gh-accent/10 px-3 py-1 text-sm font-semibold text-gh-accent">
                  <i class="bi bi-tag-fill"></i>
                  <?php echo htmlspecialchars($tag); ?>
                </span>
              <?php 
                endif;
              endforeach; 
              ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Creator Section -->
        <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-6">
          <h3 class="mb-4 text-lg font-bold text-gh-fg">About the Creator</h3>
          
          <div class="flex items-start gap-4">
            <img src="<?php echo htmlspecialchars($listing['creator_avatar'] ?? 'assets/images/default-avatar.png'); ?>" 
                 alt="<?php echo htmlspecialchars($listing['username']); ?>"
                 class="h-16 w-16 rounded-full object-cover ring-2 ring-gh-border">
            
            <div class="flex-1">
              <div class="mb-2 flex items-center gap-2">
                <h4 class="text-xl font-bold text-gh-fg"><?php echo htmlspecialchars($listing['username']); ?></h4>
                <?php if($listing['verified']): ?>
                  <i class="bi bi-patch-check-fill text-lg text-blue-500"></i>
                <?php endif; ?>
              </div>
              <p class="mb-3 text-sm text-gh-muted"><?php echo $listingCount; ?> active listings</p>
              
              <a href="profile.php?id=<?php echo $listing['creator_id']; ?>" 
                 class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-bg px-4 py-2 font-semibold text-gh-fg transition-all hover:border-gh-accent">
                <i class="bi bi-person-circle"></i>
                View Profile
              </a>
            </div>
          </div>
        </div>

        <!-- Reviews Section -->
        <?php if(count($reviews) > 0): ?>
        <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-6">
          <h3 class="mb-4 text-lg font-bold text-gh-fg">
            Reviews (<?php echo count($reviews); ?>)
          </h3>
          
          <div class="space-y-4">
            <?php foreach($reviews as $review): ?>
            <div class="rounded-lg border border-gh-border bg-gh-bg p-4">
              <div class="mb-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="font-semibold text-gh-fg"><?php echo htmlspecialchars($review['username'] ?? 'Anonymous'); ?></span>
                  <div class="flex items-center gap-1">
                    <?php for($i = 0; $i < $review['rating']; $i++): ?>
                      <i class="bi bi-star-fill text-xs text-yellow-500"></i>
                    <?php endfor; ?>
                  </div>
                </div>
                <span class="text-xs text-gh-muted"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
              </div>
              <p class="text-sm text-gh-muted"><?php echo htmlspecialchars($review['review']); ?></p>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- More from Creator -->
        <?php if(count($moreListings) > 0): ?>
        <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
          <h3 class="mb-4 text-lg font-bold text-gh-fg">More from this Creator</h3>
          
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
            <?php foreach($moreListings as $item): ?>
            <a href="marketlisting.php?id=<?php echo $item['id']; ?>" 
               class="group rounded-lg border border-gh-border bg-gh-bg transition-all hover:border-gh-accent hover:shadow-lg">
              <div class="aspect-square overflow-hidden rounded-t-lg bg-gh-panel">
                <img src="<?php echo htmlspecialchars($item['thumbnail'] ?? 'assets/images/default-content.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                     class="h-full w-full object-cover transition-transform group-hover:scale-105">
              </div>
              <div class="p-3">
                <h4 class="mb-2 truncate font-semibold text-gh-fg group-hover:text-gh-accent">
                  <?php echo htmlspecialchars($item['title']); ?>
                </h4>
                <div class="text-xl font-bold text-gh-accent">
                  $<?php echo number_format($item['price'], 2); ?>
                </div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Sidebar (Right column) -->
      <div class="lg:col-span-1">
        <div class="sticky top-6 rounded-xl border border-gh-border bg-gh-panel p-6">
          
          <?php if($hasPurchased): ?>
          <!-- Already Purchased -->
          <div class="mb-4 rounded-lg border border-green-500 bg-green-500/10 p-4 text-center">
            <i class="bi bi-check-circle-fill mb-2 text-3xl text-green-500"></i>
            <p class="font-bold text-green-500">You own this content</p>
          </div>
          
          <a href="my-purchases.php" 
             class="mb-4 block w-full rounded-lg bg-gh-accent px-6 py-3 text-center font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-download"></i>
            Download
          </a>
          
          <?php else: ?>
          <!-- Purchase Section -->
          <div class="mb-4 text-center">
            <div class="mb-2 text-sm text-gh-muted">Price</div>
            <div class="text-4xl font-bold text-gh-accent">
              $<?php echo number_format($listing['price'], 2); ?>
            </div>
          </div>
          
          <?php if(isset($_SESSION['user_id'])): ?>
          <button onclick="purchaseContent(<?php echo $listing_id; ?>)" 
                  class="mb-4 flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-4 text-lg font-bold text-white shadow-lg transition-all hover:brightness-110 hover:shadow-xl">
            <i class="bi bi-cart-plus-fill"></i>
            Purchase Now
          </button>
          <?php else: ?>
          <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
             class="mb-4 block w-full rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-4 text-center text-lg font-bold text-white shadow-lg transition-all hover:brightness-110">
            <i class="bi bi-box-arrow-in-right"></i>
            Login to Purchase
          </a>
          <?php endif; ?>
          <?php endif; ?>
          
          <!-- Info Items -->
          <div class="space-y-3 border-t border-gh-border pt-4 text-sm text-gh-muted">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <i class="bi bi-file-earmark"></i>
                Content Type
              </span>
              <span class="font-semibold text-gh-fg"><?php echo ucfirst($listing['content_type']); ?></span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <i class="bi bi-grid"></i>
                Category
              </span>
              <span class="font-semibold text-gh-fg"><?php echo ucfirst($listing['category']); ?></span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <i class="bi bi-shield-check"></i>
                Instant Access
              </span>
              <span class="font-semibold text-green-500">Yes</span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2">
                <i class="bi bi-arrow-repeat"></i>
                Lifetime Access
              </span>
              <span class="font-semibold text-green-500">Yes</span>
            </div>
          </div>

          <!-- Share Buttons -->
          <div class="mt-4 border-t border-gh-border pt-4">
            <p class="mb-3 text-sm font-semibold text-gh-fg">Share this listing</p>
            <div class="flex gap-2">
              <button onclick="shareContent('twitter')" 
                      class="flex-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-2 text-gh-fg transition-all hover:border-gh-accent">
                <i class="bi bi-twitter"></i>
              </button>
              <button onclick="shareContent('facebook')" 
                      class="flex-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-2 text-gh-fg transition-all hover:border-gh-accent">
                <i class="bi bi-facebook"></i>
              </button>
              <button onclick="shareContent('copy')" 
                      class="flex-1 rounded-lg border border-gh-border bg-gh-bg px-3 py-2 text-gh-fg transition-all hover:border-gh-accent">
                <i class="bi bi-link-45deg"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function purchaseContent(listingId) {
  if(confirm('Confirm purchase for $<?php echo number_format($listing['price'], 2); ?>?')) {
    fetch('process-purchase.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        listing_id: listingId
      })
    })
    .then(response => response.json())
    .then(data => {
      if(data.success) {
        alert('Purchase successful!');
        location.reload();
      } else {
        alert(data.message || 'Purchase failed. Please try again.');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred. Please try again.');
    });
  }
}

function shareContent(platform) {
  const url = window.location.href;
  const title = <?php echo json_encode($listing['title']); ?>;
  
  if(platform === 'twitter') {
    window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`, '_blank');
  } else if(platform === 'facebook') {
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
  } else if(platform === 'copy') {
    navigator.clipboard.writeText(url);
    alert('Link copied to clipboard!');
  }
}
</script>

<?php include 'views/footer.php'; ?>
