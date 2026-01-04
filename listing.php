<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$listing_id) {
    // Redirect to browse, not back to itself
    header('Location: browse.php');
    exit();
}

// Get listing
try {
    $query = "SELECT l.*, c.name as city_name, c.slug as city_slug, s.name as state_name, s.abbreviation as state_abbr,
              cat.name as category_name, u.username, u.is_verified, u.is_premium, u.profile_image
              FROM listings l
              LEFT JOIN cities c ON l.city_id = c.id
              LEFT JOIN states s ON c.state_id = s.id
              LEFT JOIN categories cat ON l.category_id = cat.id
              LEFT JOIN users u ON l.user_id = u.id
              WHERE l.id = :id AND l.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $listing_id);
    $stmt->execute();
    $listing = $stmt->fetch();

    if(!$listing) {
        header('Location: browse.php');
        exit();
    }

    // Increment view count
    $update_query = "UPDATE listings SET views = views + 1 WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $listing_id);
    $update_stmt->execute();

} catch(PDOException $e) {
    error_log("Error loading listing: " . $e->getMessage());
    header('Location: browse.php');
    exit();
}

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-4xl px-4">

    <!-- Back Navigation -->
    <div class="mb-4 flex items-center justify-between">
      <a href="javascript:history.back()" class="inline-flex items-center gap-1 text-xs text-gh-muted transition-colors hover:text-gh-fg">
        <i class="bi bi-arrow-left"></i>
        Back
      </a>

      <div class="flex items-center gap-2">
        <button onclick="toggleFavorite(<?php echo $listing['id']; ?>)" class="rounded-lg border border-gh-border bg-gh-panel p-2 text-sm text-gh-muted transition-all hover:border-yellow-500 hover:text-yellow-500">
          <i class="bi bi-star"></i>
        </button>
        <button onclick="shareListing()" class="rounded-lg border border-gh-border bg-gh-panel p-2 text-sm text-gh-muted transition-all hover:border-gh-accent hover:text-gh-accent">
          <i class="bi bi-share"></i>
        </button>
      </div>
    </div>

    <!-- Main Card -->
    <article class="rounded-lg border border-gh-border bg-gh-panel p-6 shadow-lg">

      <!-- Header -->
      <div class="mb-4 border-b border-gh-border pb-4">
        <div class="mb-3 flex items-start justify-between gap-3">
          <h1 class="flex-1 text-2xl font-bold text-white md:text-3xl">
            <?php echo htmlspecialchars($listing['title']); ?>
          </h1>

          <?php if($listing['is_premium']): ?>
            <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 px-3 py-1 text-xs font-bold text-white">
              <i class="bi bi-gem"></i>
              PREMIUM
            </span>
          <?php endif; ?>
        </div>

        <!-- Meta Info -->
        <div class="flex flex-wrap gap-3 text-xs text-gh-muted">
          <a href="city.php?location=<?php echo htmlspecialchars($listing['city_slug']); ?>" class="inline-flex items-center gap-1 transition-colors hover:text-gh-accent">
            <i class="bi bi-geo-alt-fill text-gh-accent"></i>
            <span class="font-semibold text-white">
              <?php echo htmlspecialchars($listing['city_name']); ?>, <?php echo htmlspecialchars($listing['state_abbr']); ?>
            </span>
          </a>

          <span class="inline-flex items-center gap-1">
            <i class="bi bi-tag-fill text-gh-accent"></i>
            <?php echo htmlspecialchars($listing['category_name']); ?>
          </span>

          <?php if($listing['age']): ?>
            <span class="inline-flex items-center gap-1">
              <i class="bi bi-calendar"></i>
              <?php echo $listing['age']; ?> years old
            </span>
          <?php endif; ?>

          <span class="inline-flex items-center gap-1">
            <i class="bi bi-clock"></i>
            <?php echo date('M d, Y g:i A', strtotime($listing['created_at'])); ?>
          </span>

          <span class="inline-flex items-center gap-1">
            <i class="bi bi-eye"></i>
            <?php echo number_format($listing['views']); ?> views
          </span>
        </div>

        <!-- Badges -->
        <div class="mt-3 flex items-center gap-2">
          <?php if($listing['is_verified']): ?>
            <span class="inline-flex items-center gap-1 rounded bg-green-500/20 px-3 py-1 text-xs font-semibold text-green-500">
              <i class="bi bi-patch-check-fill"></i>
              Verified
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Description -->
      <div class="mb-6">
        <h2 class="mb-3 text-lg font-bold text-white">About</h2>
        <div class="prose prose-invert max-w-none">
          <p class="whitespace-pre-wrap text-sm leading-relaxed text-gh-fg">
            <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
          </p>
        </div>
      </div>

      <!-- Contact Section -->
      <div class="rounded-lg border border-gh-accent/30 bg-gh-accent/10 p-4">
        <h3 class="mb-3 text-base font-bold text-white">
          <i class="bi bi-chat-dots-fill mr-2 text-gh-accent"></i>
          Contact This Member
        </h3>

        <?php if(isset($_SESSION['user_id'])): ?>
          <?php if($_SESSION['user_id'] == $listing['user_id']): ?>
            <div class="rounded-lg border border-gh-border bg-gh-panel p-3">
              <p class="text-sm text-gh-muted">
                <i class="bi bi-info-circle mr-1"></i>
                This is your listing
              </p>
              <a href="edit-listing.php?id=<?php echo $listing['id']; ?>" 
                 class="mt-2 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                <i class="bi bi-pencil-fill"></i>
                Edit Listing
              </a>
            </div>
          <?php else: ?>
            <a href="message.php?to=<?php echo $listing['user_id']; ?>&listing=<?php echo $listing['id']; ?>" 
               class="group inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-2.5 text-sm font-bold text-white transition-all hover:brightness-110">
              <i class="bi bi-send-fill"></i>
              Send Message
              <i class="bi bi-arrow-right transition-transform group-hover:translate-x-1"></i>
            </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="rounded-lg border border-gh-border bg-gh-panel p-3">
            <p class="mb-2 text-xs text-gh-muted">Login to contact this member</p>
            <div class="flex gap-2">
              <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                 class="rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                Login
              </a>
              <a href="register.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                 class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-sm font-semibold text-gh-fg hover:border-gh-accent">
                Sign Up
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- Report Button -->
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $listing['user_id']): ?>
          <div class="mt-4 pt-4 border-t border-gh-border/50">
            <a href="report.php?listing_id=<?php echo $listing['id']; ?>" 
               class="inline-flex items-center gap-2 text-xs text-gh-muted hover:text-red-500 transition-colors">
              <i class="bi bi-flag"></i>
              Report this listing
            </a>
          </div>
        <?php endif; ?>
      </div>

    </article>

    <!-- Safety Tips -->
    <div class="mt-6 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4">
      <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-yellow-500">
        <i class="bi bi-shield-check"></i>
        Safety Tips
      </h3>
      <ul class="space-y-1 text-xs text-gh-muted">
        <li class="flex items-start gap-1">
          <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
          <span>Meet in public places first</span>
        </li>
        <li class="flex items-start gap-1">
          <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
          <span>Tell someone where you're going</span>
        </li>
        <li class="flex items-start gap-1">
          <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
          <span>Trust your instincts</span>
        </li>
        <li class="flex items-start gap-1">
          <i class="bi bi-check-circle-fill mt-0.5 text-green-500"></i>
          <span>Never share financial info</span>
        </li>
      </ul>
    </div>

    <!-- More from this city -->
    <div class="mt-6">
      <a href="city.php?location=<?php echo htmlspecialchars($listing['city_slug']); ?>" 
         class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
        <i class="bi bi-arrow-left"></i>
        More from <?php echo htmlspecialchars($listing['city_name']); ?>
      </a>
    </div>

  </div>
</div>

<script>
function toggleFavorite(listingId) {
  fetch('favorite.php?id=' + listingId, { method: 'POST' })
    .then(response => response.json())
    .then(data => {
      if(data.success) {
        const btn = event.target.closest('button');
        const icon = btn.querySelector('i');
        icon.classList.toggle('bi-star');
        icon.classList.toggle('bi-star-fill');
      }
    })
    .catch(error => console.error('Error:', error));
}

function shareListing() {
  if (navigator.share) {
    navigator.share({
      title: '<?php echo htmlspecialchars($listing['title']); ?>',
      text: 'Check out this listing on trubl',
      url: window.location.href
    }).catch(err => console.log('Error sharing:', err));
  } else {
    // Fallback - copy to clipboard
    navigator.clipboard.writeText(window.location.href)
      .then(() => alert('Link copied to clipboard!'))
      .catch(err => console.error('Failed to copy:', err));
  }
}
</script>

<?php include 'views/footer.php'; ?>
