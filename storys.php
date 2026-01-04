<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// Get category filter
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Get all categories
$categories = [
    'hookup' => 'Hookup Stories',
    'first-time' => 'First Time',
    'encounter' => 'Random Encounter',
    'dating' => 'Dating Experience',
    'threesome' => 'Group Experience',
    'casual' => 'Casual Meet',
    'app' => 'App Hookup',
    'other' => 'Other'
];

// Fetch approved stories
try {
    $where_clause = "WHERE s.status = 'approved'";
    if($category_filter !== 'all') {
        $where_clause .= " AND s.category = :category";
    }

    $query = "SELECT s.*, 
              (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as like_count,
              (SELECT COUNT(*) FROM story_comments WHERE story_id = s.id) as comment_count
              FROM stories s
              $where_clause
              ORDER BY s.created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    if($category_filter !== 'all') {
        $stmt->bindParam(':category', $category_filter);
    }
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stories = $stmt->fetchAll();

    // Get total count
    $count_query = "SELECT COUNT(*) FROM stories s $where_clause";
    $count_stmt = $db->prepare($count_query);
    if($category_filter !== 'all') {
        $count_stmt->bindParam(':category', $category_filter);
    }
    $count_stmt->execute();
    $total_stories = $count_stmt->fetchColumn();
    $total_pages = ceil($total_stories / $per_page);

} catch(PDOException $e) {
    error_log("Error fetching stories: " . $e->getMessage());
    $stories = [];
    $total_pages = 0;
}

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');

/* Modal Styles */
#storyModal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.8);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

#storyModal .modal-container {
    background-color: var(--gh-panel, #1e1e1e);
    margin: 2% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    animation: slideIn 0.3s;
    border: 1px solid var(--gh-border, #333);
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

#storyModal .modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    padding: 10px 20px;
    cursor: pointer;
    transition: color 0.3s;
}

#storyModal .modal-close:hover {
    color: var(--gh-fg, #fff);
}

#storyModal .modal-loading {
    text-align: center;
    padding: 60px 20px;
    font-size: 18px;
    color: var(--gh-muted, #888);
}
</style>

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-4xl px-4">

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
        <i class="bi bi-book-fill text-2xl text-pink-500"></i>
        <h2 class="text-2xl font-bold text-gh-fg">Lusterotic Stories</h2>
      </div>
      <p class="text-gh-muted">Real Hookup Experiences</p>
      <div class="mt-2 flex items-center justify-center gap-1 text-[10px] text-gh-muted font-medium">
        <i class="bi bi-shield-check text-gh-accent"></i>
        Protected By AI Content Moderation 🛡️
      </div>

      <!-- Submit Story Button -->
      <div class="mt-4">
        <a href="story-submit.php" 
           class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:brightness-110">
          <i class="bi bi-pencil-square"></i>
          Share Your Story
        </a>
      </div>
    </div>

    <!-- Category Filter -->
    <div class="mb-6 overflow-x-auto">
      <div class="flex gap-2 pb-2">
        <a href="?category=all" 
           class="<?php echo $category_filter === 'all' ? 'bg-gh-accent text-white' : 'border border-gh-border bg-gh-panel text-gh-muted hover:border-gh-accent'; ?> whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition-all">
          All Stories
        </a>
        <?php foreach($categories as $key => $label): ?>
          <a href="?category=<?php echo $key; ?>" 
             class="<?php echo $category_filter === $key ? 'bg-gh-accent text-white' : 'border border-gh-border bg-gh-panel text-gh-muted hover:border-gh-accent'; ?> whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition-all">
            <?php echo htmlspecialchars($label); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Stats Banner -->
    <div class="mb-6 rounded-xl border border-gh-border bg-gh-panel p-4">
      <div class="flex items-center justify-between text-center">
        <div class="flex-1">
          <div class="text-2xl font-bold text-gh-accent"><?php echo number_format($total_stories); ?></div>
          <div class="text-xs text-gh-muted">Total Stories</div>
        </div>
        <div class="h-10 w-px bg-gh-border"></div>
        <div class="flex-1">
          <div class="text-2xl font-bold text-pink-500">
            <i class="bi bi-fire-fill"></i>
          </div>
          <div class="text-xs text-gh-muted">Hot Stories</div>
        </div>
        <div class="h-10 w-px bg-gh-border"></div>
        <div class="flex-1">
          <div class="text-2xl font-bold text-purple-500">
            <i class="bi bi-star-fill"></i>
          </div>
          <div class="text-xs text-gh-muted">Top Rated</div>
        </div>
      </div>
    </div>

    <!-- Stories List -->
    <?php if(count($stories) > 0): ?>
      <div class="space-y-4">
        <?php foreach($stories as $story): ?>
          <article class="group rounded-xl border <?php echo $story['is_locked'] ? 'border-red-500/50' : 'border-gh-border'; ?> bg-gh-panel p-5 transition-all hover:border-gh-accent hover:shadow-lg <?php echo $story['is_locked'] ? 'relative' : ''; ?>">
            <?php if($story['is_locked']): ?>
              <div class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-black/60 backdrop-blur-sm">
                <div class="text-center">
                  <i class="bi bi-lock-fill text-5xl text-red-500 mb-3"></i>
                  <p class="text-white font-bold text-lg">Story Locked by Admin</p>
                  <p class="text-gh-muted text-sm mt-1">This content has been restricted</p>
                </div>
              </div>
            <?php endif; ?>
            <div class="<?php echo $story['is_locked'] ? 'filter blur-md pointer-events-none' : ''; ?>">
            <div class="mb-3 flex items-start justify-between gap-3">
              <div class="flex-1">
                <a href="#" onclick="openStoryModal(<?php echo $story['id']; ?>); return false;">
                  <h3 class="mb-2 text-xl font-bold text-gh-fg group-hover:text-gh-accent">
                    <?php echo htmlspecialchars($story['title']); ?>
                  </h3>
                </a>

                <div class="flex flex-wrap items-center gap-3 text-sm text-gh-muted">
                  <span class="inline-flex items-center gap-1">
                    <i class="bi bi-tag-fill text-pink-500"></i>
                    <?php echo htmlspecialchars($categories[$story['category']] ?? 'Other'); ?>
                  </span>

                  <?php if($story['location']): ?>
                    <span class="inline-flex items-center gap-1">
                      <i class="bi bi-geo-alt-fill"></i>
                      <?php echo htmlspecialchars($story['location']); ?>
                    </span>
                  <?php endif; ?>

                  <span class="inline-flex items-center gap-1">
                    <i class="bi bi-clock-fill"></i>
                    <?php echo date('M d, Y', strtotime($story['created_at'])); ?>
                  </span>

                  <span class="inline-flex items-center gap-1">
                    <i class="bi bi-eye-fill"></i>
                    <?php echo number_format($story['views']); ?> views
                  </span>
                </div>
              </div>

              <?php if($story['is_featured']): ?>
                <span class="shrink-0 rounded-full bg-gradient-to-r from-pink-600 to-purple-600 px-3 py-1 text-xs font-bold text-white">
                  FEATURED
                </span>
              <?php endif; ?>
            </div>

            <p class="mb-4 line-clamp-3 text-gh-muted">
              <?php echo htmlspecialchars(substr(strip_tags($story['content']), 0, 200)) . '...'; ?>
            </p>

            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4 text-sm">
                <button onclick="likeStory(<?php echo $story['id']; ?>)" 
                        class="flex items-center gap-1 text-gh-muted transition-colors hover:text-pink-500">
                  <i class="bi bi-heart-fill"></i>
                  <span><?php echo number_format($story['like_count']); ?></span>
                </button>

                <a href="story-view.php?id=<?php echo $story['id']; ?>#comments" 
                   class="flex items-center gap-1 text-gh-muted transition-colors hover:text-gh-accent">
                  <i class="bi bi-chat-fill"></i>
                  <span><?php echo number_format($story['comment_count']); ?></span>
                </a>

                <button onclick="shareStory(<?php echo $story['id']; ?>)" 
                        class="flex items-center gap-1 text-gh-muted transition-colors hover:text-gh-success">
                  <i class="bi bi-share-fill"></i>
                  <span>Share</span>
                </button>
              </div>

              <a href="#" onclick="openStoryModal(<?php echo $story['id']; ?>); return false;" 
                 class="inline-flex items-center gap-1 font-semibold text-gh-accent transition-all hover:gap-2">
                Read More
                <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if($total_pages > 1): ?>
        <div class="mt-8 flex justify-center gap-2">
          <?php if($page > 1): ?>
            <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $page - 1; ?>" 
               class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 font-semibold text-gh-fg transition-all hover:border-gh-accent">
              <i class="bi bi-chevron-left"></i> Previous
            </a>
          <?php endif; ?>

          <div class="flex items-center gap-2">
            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
              <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $i; ?>" 
                 class="<?php echo $i === $page ? 'bg-gh-accent text-white' : 'border border-gh-border bg-gh-panel text-gh-fg hover:border-gh-accent'; ?> rounded-lg px-4 py-2 font-semibold transition-all">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
          </div>

          <?php if($page < $total_pages): ?>
            <a href="?category=<?php echo $category_filter; ?>&page=<?php echo $page + 1; ?>" 
               class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 font-semibold text-gh-fg transition-all hover:border-gh-accent">
              Next <i class="bi bi-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
        <i class="bi bi-inbox text-6xl text-gh-muted opacity-20"></i>
        <h3 class="mt-4 text-xl font-bold text-gh-fg">No Stories Yet</h3>
        <p class="mt-2 text-gh-muted">Be the first to share your experience!</p>
        <a href="story-submit.php" 
           class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-3 font-semibold text-white transition-all hover:brightness-110">
          Share Your Story
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Story Modal -->
<div id="storyModal">
  <div class="modal-container">
    <span class="modal-close" onclick="closeStoryModal()">&times;</span>
    <div id="modalContent">
      <div class="modal-loading">
        <i class="bi bi-hourglass-split"></i> Loading story...
      </div>
    </div>
  </div>
</div>

<script>
const modal = document.getElementById('storyModal');

function openStoryModal(storyId) {
  modal.style.display = 'block';
  document.getElementById('modalContent').innerHTML = '<div class="modal-loading"><i class="bi bi-hourglass-split"></i> Loading story...</div>';
  
  fetch(`story-fetch.php?id=${storyId}`)
    .then(response => response.json())
    .then(data => {
      if(data.success) {
        displayStoryInModal(data.story);
      } else {
        document.getElementById('modalContent').innerHTML = '<div class="modal-loading">Error loading story.</div>';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('modalContent').innerHTML = '<div class="modal-loading">Error loading story.</div>';
    });
}

function closeStoryModal() {
  modal.style.display = 'none';
}

window.onclick = function(event) {
  if (event.target == modal) {
    closeStoryModal();
  }
}

document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape' && modal.style.display === 'block') {
    closeStoryModal();
  }
});

function displayStoryInModal(story) {
  const categories = <?php echo json_encode($categories); ?>;
  
  let metaHtml = '';
  if(story.author_name) metaHtml += `<span class="inline-flex items-center gap-1"><i class="bi bi-person-fill"></i> ${escapeHtml(story.author_name)}</span>`;
  if(story.age) metaHtml += `<span class="inline-flex items-center gap-1"><i class="bi bi-calendar-fill"></i> ${story.age}</span>`;
  if(story.gender) metaHtml += `<span class="inline-flex items-center gap-1"><i class="bi bi-gender-ambiguous"></i> ${story.gender.charAt(0).toUpperCase() + story.gender.slice(1)}</span>`;
  if(story.location) metaHtml += `<span class="inline-flex items-center gap-1"><i class="bi bi-geo-alt-fill"></i> ${escapeHtml(story.location)}</span>`;
  
  const html = `
    <div class="rounded-t-xl border-b border-gh-border bg-gradient-to-r from-pink-600 to-purple-600 p-6">
      <h2 class="mb-3 text-2xl font-bold text-white">${escapeHtml(story.title)}</h2>
      <div class="flex flex-wrap items-center gap-3 text-sm text-white opacity-90">
        <span class="inline-flex items-center gap-1">
          <i class="bi bi-tag-fill"></i>
          ${categories[story.category] || 'Other'}
        </span>
        ${metaHtml}
        <span class="inline-flex items-center gap-1">
          <i class="bi bi-clock-fill"></i>
          ${new Date(story.created_at).toLocaleDateString()}
        </span>
      </div>
    </div>
    <div class="p-6">
      <div class="whitespace-pre-wrap text-gh-fg" style="line-height: 1.8;">
        ${escapeHtml(story.content)}
      </div>
    </div>
    <div class="rounded-b-xl border-t border-gh-border bg-gh-panel p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4 text-sm text-gh-muted">
          <span><i class="bi bi-eye-fill"></i> ${formatNumber(story.views)} views</span>
          <span><i class="bi bi-heart-fill text-pink-500"></i> ${formatNumber(story.like_count)} likes</span>
          <span><i class="bi bi-chat-fill"></i> ${formatNumber(story.comment_count)} comments</span>
        </div>
        <a href="story-view.php?id=${story.id}" class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-4 py-2 font-semibold text-white transition-all hover:brightness-110">
          View Full Page <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  `;
  
  document.getElementById('modalContent').innerHTML = html;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatNumber(num) {
  return new Intl.NumberFormat().format(num);
}

function likeStory(storyId) {
  fetch(`story-like.php?id=${storyId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    }
  })
  .then(response => response.json())
  .then(data => {
    if(data.success) {
      location.reload();
    }
  })
  .catch(error => console.error('Error:', error));
}

function shareStory(storyId) {
  const url = `${window.location.origin}/story-view.php?id=${storyId}`;
  if(navigator.share) {
    navigator.share({
      title: 'Check out this story',
      url: url
    });
  } else {
    navigator.clipboard.writeText(url);
    alert('Link copied to clipboard!');
  }
}
</script>

<?php include 'views/footer.php'; ?>
