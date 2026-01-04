<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Listing.php';
require_once 'classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$listing = new Listing($db);

$success = '';
$error = '';

// Handle delete action
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if(!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $listing_id = (int)$_POST['listing_id'];
        if($listing->delete($listing_id, $_SESSION['user_id'])) {
            $success = 'Listing deleted successfully!';
            CSRF::regenerateToken();
        } else {
            $error = 'Failed to delete listing';
        }
    }
}

// Get user's listings
$my_listings = $listing->getUserListings($_SESSION['user_id']);

// Get stats
$query = "SELECT 
          COUNT(*) as total_listings, 
          SUM(views) as total_views,
          SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_listings
          FROM listings 
          WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$stats = $stmt->fetch();

include 'views/header.php';
?>

<div class="mx-auto max-w-7xl px-4 py-8">
  
  <!-- Page Header -->
  <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight">
        <i class="bi bi-file-text-fill text-gh-accent"></i>
        My listings
      </h1>
      <p class="mt-2 text-sm text-gh-muted">Manage your posts and track performance</p>
    </div>
    <a href="post-ad.php" 
       class="inline-flex items-center justify-center gap-2 rounded-lg bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
      <i class="bi bi-plus-circle-fill"></i> Create new listing
    </a>
  </div>

  <!-- Alerts -->
  <?php if(!empty($success)): ?>
    <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-xl text-gh-success"></i>
        <div class="flex-1">
          <span class="font-semibold text-gh-success">Success!</span>
          <span class="text-sm text-gh-fg"> <?php echo htmlspecialchars($success); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if(!empty($error)): ?>
    <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-xl text-gh-danger"></i>
        <div class="flex-1">
          <span class="font-semibold text-gh-danger">Error:</span>
          <span class="text-sm text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-file-text text-2xl text-gh-accent"></i>
        </div>
        <div>
          <div class="text-2xl font-extrabold"><?php echo number_format($stats['total_listings'] ?? 0); ?></div>
          <div class="text-sm text-gh-muted">Total listings</div>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-eye text-2xl text-gh-success"></i>
        </div>
        <div>
          <div class="text-2xl font-extrabold"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
          <div class="text-sm text-gh-muted">Total views</div>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-gh-border bg-gh-panel p-5">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-gh-border bg-gh-panel2">
          <i class="bi bi-check-circle text-2xl text-gh-warning"></i>
        </div>
        <div>
          <div class="text-2xl font-extrabold"><?php echo number_format($stats['active_listings'] ?? 0); ?></div>
          <div class="text-sm text-gh-muted">Active listings</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Listings -->
  <?php if(empty($my_listings)): ?>
    <!-- Empty State -->
    <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl border border-gh-border bg-gh-panel2">
        <i class="bi bi-inbox text-4xl text-gh-muted opacity-50"></i>
      </div>
      <h3 class="mt-6 text-lg font-bold">No listings yet</h3>
      <p class="mt-2 text-sm text-gh-muted">You haven't created any listings yet. Start by posting your first ad!</p>
      <a href="post-ad.php" 
         class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
        <i class="bi bi-plus-circle-fill"></i> Create first listing
      </a>
    </div>
  <?php else: ?>
    <!-- Listings Grid -->
    <div class="space-y-4">
      <?php foreach($my_listings as $item): ?>
        <div class="group overflow-hidden rounded-xl border border-gh-border bg-gh-panel transition-colors hover:bg-gh-panel2">
          <div class="flex flex-col gap-4 p-5 md:flex-row">
            
            <!-- Thumbnail -->
            <div class="relative h-32 w-full flex-shrink-0 overflow-hidden rounded-lg border border-gh-border bg-gh-panel2 md:h-28 md:w-40">
              <?php if(!empty($item['photo_url'])): ?>
                <img src="<?php echo htmlspecialchars($item['photo_url']); ?>" 
                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                     class="h-full w-full object-cover" />
              <?php else: ?>
                <div class="flex h-full w-full items-center justify-center">
                  <i class="bi bi-image text-4xl text-gh-muted opacity-20"></i>
                </div>
              <?php endif; ?>
              
              <?php if(!empty($item['is_featured'])): ?>
                <span class="absolute left-2 top-2 rounded-full border border-gh-border bg-gh-panel px-2 py-0.5 text-xs font-bold text-gh-warning backdrop-blur">
                  <i class="bi bi-star-fill mr-1"></i>Featured
                </span>
              <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                  <a href="listing.php?id=<?php echo $item['id']; ?>" 
                     class="group-hover:underline">
                    <h3 class="text-lg font-bold group-hover:text-gh-accent">
                      <?php echo htmlspecialchars($item['title']); ?>
                    </h3>
                  </a>
                  
                  <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-gh-muted">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gh-border bg-gh-panel2 px-2.5 py-0.5 text-xs font-semibold">
                      <?php echo htmlspecialchars($item['category_name']); ?>
                    </span>
                    <span class="inline-flex items-center gap-1">
                      <i class="bi bi-geo-alt-fill"></i>
                      <?php echo htmlspecialchars($item['city_name']); ?>
                    </span>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1">
                      <i class="bi bi-eye-fill"></i>
                      <?php echo number_format($item['views'] ?? 0); ?> views
                    </span>
                    <span>•</span>
                    <span class="inline-flex items-center gap-1">
                      <i class="bi bi-clock-fill"></i>
                      <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                    </span>
                  </div>

                  <div class="mt-3 line-clamp-2 text-sm text-gh-muted">
                    <?php 
                      $excerpt = strip_tags($item['description']);
                      echo htmlspecialchars(substr($excerpt, 0, 150)) . (strlen($excerpt) > 150 ? '...' : '');
                    ?>
                  </div>
                </div>

                <!-- Status Badge -->
                <div>
                  <?php if($item['status'] === 'active'): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1 text-xs font-bold text-gh-success">
                      <span class="h-2 w-2 rounded-full bg-gh-success"></span>
                      Active
                    </span>
                  <?php elseif($item['status'] === 'pending'): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1 text-xs font-bold text-gh-warning">
                      <span class="h-2 w-2 rounded-full bg-gh-warning"></span>
                      Pending
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1 text-xs font-bold text-gh-muted">
                      <span class="h-2 w-2 rounded-full bg-gh-muted"></span>
                      Inactive
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Actions -->
              <div class="mt-4 flex flex-wrap gap-2">
                <a href="listing.php?id=<?php echo $item['id']; ?>" 
                   class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel2 px-3 py-1.5 text-sm font-semibold transition-colors hover:bg-white/5">
                  <i class="bi bi-eye-fill"></i> View
                </a>
                <a href="edit-listing.php?id=<?php echo $item['id']; ?>" 
                   class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel2 px-3 py-1.5 text-sm font-semibold transition-colors hover:bg-white/5">
                  <i class="bi bi-pencil-fill"></i> Edit
                </a>
                
                <?php if($item['status'] === 'active'): ?>
                  <button onclick="pauseListing(<?php echo $item['id']; ?>)" 
                          class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel2 px-3 py-1.5 text-sm font-semibold transition-colors hover:bg-white/5">
                    <i class="bi bi-pause-circle-fill"></i> Pause
                  </button>
                <?php endif; ?>
                
                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this listing?');">
                  <?php echo CSRF::getHiddenInput(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="listing_id" value="<?php echo $item['id']; ?>">
                  <button type="submit" 
                          class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel2 px-3 py-1.5 text-sm font-semibold text-gh-danger transition-colors hover:bg-white/5">
                    <i class="bi bi-trash-fill"></i> Delete
                  </button>
                </form>

                <button onclick="shareUrl('<?php echo htmlspecialchars('listing.php?id=' . $item['id']); ?>')" 
                        class="inline-flex items-center gap-2 rounded-md border border-gh-border bg-gh-panel2 px-3 py-1.5 text-sm font-semibold transition-colors hover:bg-white/5">
                  <i class="bi bi-share-fill"></i> Share
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Tips Card -->
  <div class="mt-6 rounded-xl border border-gh-border bg-gh-panel p-6">
    <h3 class="flex items-center gap-2 text-base font-bold">
      <i class="bi bi-lightbulb-fill text-gh-warning"></i>
      Tips to boost your listings
    </h3>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div class="flex items-start gap-3 text-sm">
        <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
        <div>
          <div class="font-semibold">Add clear photos</div>
          <div class="text-gh-muted">Listings with photos get 3x more views</div>
        </div>
      </div>
      <div class="flex items-start gap-3 text-sm">
        <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
        <div>
          <div class="font-semibold">Write detailed descriptions</div>
          <div class="text-gh-muted">Be specific about what you're looking for</div>
        </div>
      </div>
      <div class="flex items-start gap-3 text-sm">
        <i class="bi bi-check-circle-fill mt-0.5 text-gh-success"></i>
        <div>
          <div class="font-semibold">Respond quickly</div>
          <div class="text-gh-muted">Fast responses increase engagement</div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// Pause listing
function pauseListing(listingId) {
  if(!confirm('Pause this listing? You can reactivate it later.')) return;
  
  fetch('api/listings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
      action: 'pause', 
      listing_id: listingId 
    })
  })
  .then(r => r.json())
  .then(data => {
    if(data.success) {
      location.reload();
    } else {
      alert(data.message || 'Failed to pause listing');
    }
  })
  .catch(err => {
    console.error(err);
    alert('An error occurred');
  });
}

// Share URL
function shareUrl(path) {
  const url = window.location.origin + '/' + path;
  
  if(navigator.share) {
    navigator.share({
      title: 'Check out my listing',
      url: url
    });
  } else if(navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => {
      alert('Link copied to clipboard!');
    });
  } else {
    prompt('Copy this link:', url);
  }
}
</script>

<?php include 'views/footer.php'; ?>
