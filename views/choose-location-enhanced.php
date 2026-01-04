<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Location.php';

$database = new Database();
$db = $database->getConnection();
$location = new Location($db);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$state_filter = isset($_GET['state']) ? (int)$_GET['state'] : 0;

// Get all states
$states = $location->getAllStates();

// Get popular cities (top 20 by listing count)
$popular_cities = [];
try {
    $query = "SELECT c.*, s.abbreviation as state_abbr, s.name as state_name,
              (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active') as listing_count
              FROM cities c
              LEFT JOIN states s ON c.state_id = s.id
              WHERE c.is_active = 1
              HAVING listing_count > 0
              ORDER BY listing_count DESC
              LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $popular_cities = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error: " . $e->getMessage());
}

// Search results
$search_results = [];
if($search) {
    try {
        $query = "SELECT c.*, s.abbreviation as state_abbr, s.name as state_name,
                  (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active') as listing_count
                  FROM cities c
                  LEFT JOIN states s ON c.state_id = s.id
                  WHERE c.is_active = 1 
                  AND (c.name LIKE :search OR s.name LIKE :search)
                  ORDER BY listing_count DESC
                  LIMIT 50";
        $stmt = $db->prepare($query);
        $search_param = '%' . $search . '%';
        $stmt->bindParam(':search', $search_param);
        $stmt->execute();
        $search_results = $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error: " . $e->getMessage());
    }
}

// Get cities by state
$state_cities = [];
if($state_filter) {
    try {
        $query = "SELECT c.*,
                  (SELECT COUNT(*) FROM listings WHERE city_id = c.id AND status = 'active') as listing_count
                  FROM cities c
                  WHERE c.state_id = :state_id AND c.is_active = 1
                  ORDER BY listing_count DESC, c.name ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':state_id', $state_filter);
        $stmt->execute();
        $state_cities = $stmt->fetchAll();

        // Get state name
        $state_query = "SELECT name FROM states WHERE id = :id";
        $state_stmt = $db->prepare($state_query);
        $state_stmt->bindParam(':id', $state_filter);
        $state_stmt->execute();
        $state_name = $state_stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log("Error: " . $e->getMessage());
    }
}

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');
</style>

<div class="min-h-screen bg-gh-bg">

  <!-- Hero Section -->
  <div class="relative overflow-hidden bg-gradient-to-br from-purple-900 via-pink-900 to-red-900 py-16">
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTM2IDM0aDd2N2MwIDEuMTA1LS44OTUgMi0yIDJoLTNjLTEuMTA1IDAtMi0uODk1LTItMnYtN3oiLz48L2c+PC9nPjwvc3ZnPg==')] opacity-10"></div>

    <div class="relative mx-auto max-w-5xl px-4">
      <a href="index.php" class="mb-6 inline-flex items-center gap-2 text-sm text-white/80 transition-colors hover:text-white">
        <i class="bi bi-arrow-left"></i>
        Back to Home
      </a>

      <div class="text-center">
        <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-2xl bg-white/10 text-5xl backdrop-blur-sm">
          <i class="bi bi-geo-alt-fill text-pink-300"></i>
        </div>

        <h1 class="mb-4 text-5xl font-bold text-white md:text-6xl">Choose Your Location</h1>
        <p class="text-xl text-pink-200">Find personals in your city</p>
      </div>

      <!-- Search Bar -->
      <form method="GET" class="mx-auto mt-8 max-w-2xl">
        <div class="relative">
          <i class="bi bi-search pointer-events-none absolute left-6 top-1/2 -translate-y-1/2 text-2xl text-gh-muted"></i>
          <input type="text" 
                 name="search" 
                 value="<?php echo htmlspecialchars($search); ?>"
                 placeholder="Search for a city or state..."
                 class="w-full rounded-2xl border-2 border-white/20 bg-white/10 py-5 pl-16 pr-6 text-lg text-white placeholder-white/60 backdrop-blur-sm transition-all focus:border-white/40 focus:bg-white/20 focus:outline-none"
                 autofocus>

          <?php if($search): ?>
            <a href="choose-location.php" 
               class="absolute right-6 top-1/2 -translate-y-1/2 text-white/60 transition-colors hover:text-white">
              <i class="bi bi-x-circle-fill text-2xl"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Main Content -->
  <div class="mx-auto max-w-7xl px-4 py-12">

    <?php if($search && count($search_results) > 0): ?>
      <!-- Search Results -->
      <div class="mb-12">
        <h2 class="mb-6 text-3xl font-bold text-white">
          <i class="bi bi-search mr-2 text-gh-accent"></i>
          Search Results for "<?php echo htmlspecialchars($search); ?>"
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <?php foreach($search_results as $city): ?>
            <a href="city.php?location=<?php echo htmlspecialchars($city['slug']); ?>"
               class="group relative overflow-hidden rounded-xl border border-gh-border bg-gh-panel p-6 transition-all hover:border-gh-accent hover:shadow-xl hover:shadow-gh-accent/20">

              <!-- City Icon -->
              <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-gh-accent to-gh-success text-2xl text-white shadow-lg">
                <i class="bi bi-building"></i>
              </div>

              <!-- City Info -->
              <h3 class="mb-1 text-xl font-bold text-white transition-colors group-hover:text-gh-accent">
                <?php echo htmlspecialchars($city['name']); ?>
              </h3>
              <p class="mb-3 text-sm text-gh-muted"><?php echo htmlspecialchars($city['state_name']); ?></p>

              <!-- Stats -->
              <div class="flex items-center justify-between rounded-lg bg-gh-panel2 px-3 py-2">
                <span class="text-xs text-gh-muted">Active Ads</span>
                <span class="font-bold text-gh-accent"><?php echo number_format($city['listing_count']); ?></span>
              </div>

              <!-- Arrow -->
              <div class="absolute right-4 top-4 opacity-0 transition-opacity group-hover:opacity-100">
                <i class="bi bi-arrow-right-circle-fill text-2xl text-gh-accent"></i>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

    <?php elseif($search): ?>
      <!-- No Results -->
      <div class="mb-12 rounded-2xl border border-gh-border bg-gh-panel p-12 text-center">
        <i class="bi bi-search text-6xl text-gh-muted opacity-20"></i>
        <h3 class="mt-4 text-2xl font-bold text-white">No Cities Found</h3>
        <p class="mt-2 text-gh-muted">No results for "<?php echo htmlspecialchars($search); ?>"</p>
        <a href="choose-location.php" 
           class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-3 font-semibold text-white transition-all hover:brightness-110">
          <i class="bi bi-x-circle"></i>
          Clear Search
        </a>
      </div>

    <?php endif; ?>

    <?php if($state_filter && count($state_cities) > 0): ?>
      <!-- State Cities -->
      <div class="mb-12">
        <div class="mb-6 flex items-center justify-between">
          <h2 class="text-3xl font-bold text-white">
            <i class="bi bi-pin-map-fill mr-2 text-gh-accent"></i>
            Cities in <?php echo htmlspecialchars($state_name); ?>
          </h2>
          <a href="choose-location.php" class="text-gh-accent transition-colors hover:text-gh-success">
            <i class="bi bi-x-circle mr-1"></i>
            Clear Filter
          </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <?php foreach($state_cities as $city): ?>
            <a href="city.php?location=<?php echo htmlspecialchars($city['slug']); ?>"
               class="group rounded-xl border border-gh-border bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg">
              <div class="mb-3 flex items-center justify-between">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gh-accent/20 text-xl text-gh-accent">
                  <i class="bi bi-geo-alt-fill"></i>
                </div>
                <i class="bi bi-arrow-right text-xl text-gh-muted transition-all group-hover:translate-x-1 group-hover:text-gh-accent"></i>
              </div>
              <h3 class="mb-2 text-lg font-bold text-white group-hover:text-gh-accent">
                <?php echo htmlspecialchars($city['name']); ?>
              </h3>
              <div class="text-sm text-gh-muted">
                <?php echo number_format($city['listing_count']); ?> ads
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

    <?php endif; ?>

    <?php if(!$search && !$state_filter): ?>

      <!-- Popular Cities -->
      <div class="mb-12">
        <div class="mb-6 text-center">
          <h2 class="text-3xl font-bold text-white">
            <i class="bi bi-fire-fill mr-2 text-orange-500"></i>
            Most Popular Cities
          </h2>
          <p class="mt-2 text-gh-muted">Cities with the most active personals</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <?php foreach(array_slice($popular_cities, 0, 8) as $index => $city): ?>
            <a href="city.php?location=<?php echo htmlspecialchars($city['slug']); ?>"
               class="group relative overflow-hidden rounded-2xl border border-gh-border bg-gh-panel transition-all hover:border-gh-accent hover:shadow-2xl hover:shadow-gh-accent/30">

              <!-- Rank Badge -->
              <div class="absolute left-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full <?php echo $index < 3 ? 'bg-gradient-to-br from-yellow-500 to-orange-500' : 'bg-gh-panel2'; ?> font-bold <?php echo $index < 3 ? 'text-white' : 'text-gh-muted'; ?> shadow-lg">
                <?php echo $index + 1; ?>
              </div>

              <div class="p-6 pt-16">
                <!-- City Icon -->
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-gh-accent to-gh-success text-3xl text-white shadow-lg">
                  <i class="bi bi-building"></i>
                </div>

                <!-- City Info -->
                <h3 class="mb-1 text-center text-xl font-bold text-white transition-colors group-hover:text-gh-accent">
                  <?php echo htmlspecialchars($city['name']); ?>
                </h3>
                <p class="mb-4 text-center text-sm text-gh-muted">
                  <?php echo htmlspecialchars($city['state_abbr']); ?>
                </p>

                <!-- Stats -->
                <div class="rounded-xl bg-gh-panel2 p-3 text-center">
                  <div class="text-2xl font-bold text-gh-accent">
                    <?php echo number_format($city['listing_count']); ?>
                  </div>
                  <div class="text-xs text-gh-muted">Active Ads</div>
                </div>
              </div>

              <!-- Hover Overlay -->
              <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gh-accent/90 to-gh-success/90 opacity-0 transition-opacity group-hover:opacity-100">
                <div class="text-center">
                  <i class="bi bi-arrow-right-circle-fill text-5xl text-white"></i>
                  <div class="mt-2 font-bold text-white">View Listings</div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Browse by State -->
      <div class="mb-12">
        <div class="mb-6 text-center">
          <h2 class="text-3xl font-bold text-white">
            <i class="bi bi-map-fill mr-2 text-blue-500"></i>
            Browse by State
          </h2>
          <p class="mt-2 text-gh-muted">Select a state to view all cities</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          <?php foreach($states as $state): ?>
            <a href="?state=<?php echo $state['id']; ?>" 
               class="group flex items-center justify-between rounded-xl border border-gh-border bg-gh-panel px-5 py-4 transition-all hover:border-gh-accent hover:shadow-lg">
              <span class="font-semibold text-white transition-colors group-hover:text-gh-accent">
                <?php echo htmlspecialchars($state['abbreviation']); ?>
              </span>
              <i class="bi bi-chevron-right text-gh-muted transition-all group-hover:translate-x-1 group-hover:text-gh-accent"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- All Cities Grid -->
      <div class="mb-12">
        <div class="mb-6 text-center">
          <h2 class="text-3xl font-bold text-white">
            <i class="bi bi-globe mr-2 text-green-500"></i>
            All Cities
          </h2>
          <p class="mt-2 text-gh-muted">Showing remaining popular locations</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <?php foreach(array_slice($popular_cities, 8) as $city): ?>
            <a href="city.php?location=<?php echo htmlspecialchars($city['slug']); ?>"
               class="group flex items-center gap-3 rounded-xl border border-gh-border bg-gh-panel p-4 transition-all hover:border-gh-accent hover:shadow-lg">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gh-accent/20 text-gh-accent">
                <i class="bi bi-geo-alt-fill"></i>
              </div>
              <div class="min-w-0 flex-1">
                <div class="truncate font-semibold text-white group-hover:text-gh-accent">
                  <?php echo htmlspecialchars($city['name']); ?>
                </div>
                <div class="text-xs text-gh-muted">
                  <?php echo number_format($city['listing_count']); ?> ads • <?php echo htmlspecialchars($city['state_abbr']); ?>
                </div>
              </div>
              <i class="bi bi-arrow-right shrink-0 text-gh-muted transition-all group-hover:translate-x-1 group-hover:text-gh-accent"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- CTA Section -->
      <div class="rounded-2xl border-2 border-gh-accent/30 bg-gradient-to-br from-gh-accent/10 to-gh-success/10 p-8 text-center">
        <i class="bi bi-pin-map-fill text-5xl text-gh-accent"></i>
        <h3 class="mt-4 text-2xl font-bold text-white">Don't See Your City?</h3>
        <p class="mt-2 text-gh-muted">Be the first to post in your location!</p>
        <a href="post-ad.php" 
           class="mt-6 inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-gh-accent to-gh-success px-8 py-4 font-bold text-white shadow-lg transition-all hover:scale-105">
          <i class="bi bi-plus-circle-fill"></i>
          Post First Ad
        </a>
      </div>

    <?php endif; ?>

  </div>
</div>

<script>
// Auto-submit search on input with debounce
let searchTimeout;
const searchInput = document.querySelector('input[name="search"]');

searchInput?.addEventListener('input', function() {
  clearTimeout(searchTimeout);
  if(this.value.length >= 2 || this.value.length === 0) {
    searchTimeout = setTimeout(() => {
      this.form.submit();
    }, 500);
  }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
  if(e.key === 'Escape' && searchInput?.value) {
    window.location.href = 'choose-location.php';
  }
});
</script>

<?php include 'views/footer.php'; ?>
