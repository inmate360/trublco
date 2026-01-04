<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';
require_once 'classes/MessageLimits.php';

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$profile_user_id = isset($_GET['id']) ? $_GET['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

if (!$profile_user_id) {
    header('Location: login.php');
    exit();
}

$profile_data = $userProfile->getProfile($profile_user_id);
if(!$profile_data) {
    header('Location: index.php');
    exit();
}

$is_own_profile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile_user_id;

// Check message limits
$can_message = false;
$message_limit_info = null;
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $messageLimits = new MessageLimits($db);
    $message_limit_info = $messageLimits->canSendMessage($_SESSION['user_id']);
    $can_message = $message_limit_info['can_send'];
}

// Get user's listings
$query = "SELECT l.*, c.name as category_name, ct.name as city_name, s.abbreviation as state_abbr 
          FROM listings l
          LEFT JOIN categories c ON l.category_id = c.id
          LEFT JOIN cities ct ON l.city_id = ct.id
          LEFT JOIN states s ON ct.state_id = s.id
          WHERE l.user_id = :user_id AND l.status = 'active'
          ORDER BY l.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $profile_user_id);
$stmt->execute();
$user_listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user photos
$user_gallery_images = $userProfile->getUserPhotos($profile_user_id);

// Get primary and banner photos
$primary_photo = null;
$banner_photo = null;
foreach($user_gallery_images as $photo) {
    if($photo['is_primary']) {
        $primary_photo = $photo;
    }
    if(isset($photo['is_banner']) && $photo['is_banner']) {
        $banner_photo = $photo;
    }
}

$avatar_url = ($profile_data['avatar'] && $profile_data['avatar'] !== '/assets/images/default-avatar.png') ? $profile_data['avatar'] : ($primary_photo ? $primary_photo['file_path'] : '/assets/images/default-avatar.png');
$banner_url = $banner_photo ? $banner_photo['file_path'] : '';

// Record profile view
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $userProfile->recordView($_SESSION['user_id'], $profile_user_id);
}

// Check if favorited
$is_favorited = false;
if(isset($_SESSION['user_id']) && !$is_own_profile) {
    $is_favorited = $userProfile->isFavorited($_SESSION['user_id'], $profile_user_id);
}

include 'views/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Pacifico&display=swap');

.profile-banner {
  position: relative;
  height: 200px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  overflow: hidden;
}

@media (min-width: 768px) {
  .profile-banner {
    height: 300px;
  }
}

.profile-banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.online-indicator {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 16px;
  height: 16px;
  background: #22c55e;
  border: 3px solid var(--gh-bg);
  border-radius: 50%;
  z-index: 10;
  animation: pulse 2s infinite;
}

@media (min-width: 768px) {
  .online-indicator {
    bottom: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
  }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.profile-avatar-mobile {
  width: 120px;
  height: 120px;
  margin-top: -60px;
}

@media (min-width: 768px) {
  .profile-avatar-mobile {
    width: 160px;
    height: 160px;
    margin-top: -80px;
  }
}

@media (min-width: 1024px) {
  .profile-avatar-mobile {
    width: 192px;
    height: 192px;
    margin-top: -96px;
  }
}

/* Mobile-optimized action buttons */
.action-buttons-mobile {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
}

@media (min-width: 640px) {
  .action-buttons-mobile {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
  }
}

@media (min-width: 1024px) {
  .action-buttons-mobile {
    justify-content: flex-start;
  }
}

/* Collapsible sections for mobile */
.collapsible-header {
  cursor: pointer;
  user-select: none;
}

.collapsible-content {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease-out;
}

.collapsible-content.active {
  max-height: 2000px;
  transition: max-height 0.5s ease-in;
}

/* Touch-friendly sizing */
.touch-target {
  min-height: 44px;
  min-width: 44px;
}

/* Optimized badge layout for mobile */
.badge-container {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  justify-content: center;
}

@media (min-width: 1024px) {
  .badge-container {
    justify-content: flex-start;
  }
}

.badge-mobile {
  font-size: 0.75rem;
  padding: 0.25rem 0.625rem;
}

@media (min-width: 640px) {
  .badge-mobile {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
  }
}

/* Photo grid optimization */
.photo-grid-mobile {
  grid-template-columns: repeat(2, 1fr);
}

@media (min-width: 640px) {
  .photo-grid-mobile {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 768px) {
  .photo-grid-mobile {
    grid-template-columns: repeat(4, 1fr);
  }
}
</style>

<div class="min-h-screen bg-gh-bg pb-8 sm:pb-12">
  
  <!-- Profile Banner -->
  <div class="profile-banner mb-4 sm:mb-6">
    <?php if($banner_url): ?>
      <img src="<?php echo htmlspecialchars($banner_url); ?>" alt="Banner">
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-t from-gh-bg via-transparent to-transparent"></div>
  </div>

  <div class="mx-auto max-w-7xl px-3 sm:px-4">
    
    <!-- Avatar & Header Info -->
    <div class="relative mb-6 sm:mb-8">
      <div class="flex flex-col items-center gap-4 sm:gap-6">
        
        <!-- Avatar -->
        <div class="relative profile-avatar-mobile">
          <?php if($profile_data['is_online'] ?? false): ?>
            <div class="online-indicator"></div>
          <?php endif; ?>
          
          <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
               alt="<?php echo htmlspecialchars($profile_data['username']); ?>"
               class="h-full w-full cursor-pointer rounded-full border-4 border-gh-panel object-cover shadow-2xl ring-4 ring-gh-bg"
               onclick="openLightbox('<?php echo htmlspecialchars($avatar_url); ?>')">
        </div>

        <!-- User Info -->
        <div class="w-full text-center">
          <!-- Username & Badges -->
          <div class="mb-3 flex flex-col items-center gap-2">
            <h1 class="text-2xl font-bold text-gh-fg sm:text-3xl lg:text-4xl">
              <?php echo htmlspecialchars($profile_data['username']); ?>
            </h1>
            
            <!-- Badges - Optimized for mobile -->
            <div class="badge-container">
              <?php if($profile_data['is_admin'] ?? false): ?>
                <span class="badge-mobile inline-flex items-center gap-1 rounded-full bg-red-500/20 font-bold text-red-400 ring-1 ring-red-500/30" title="Admin">
                  <i class="bi bi-shield-fill-check"></i>
                  <span class="hidden xs:inline">Admin</span>
                </span>
              <?php endif; ?>
              
              <?php if($profile_data['verified'] ?? false): ?>
                <span class="badge-mobile inline-flex items-center gap-1 rounded-full bg-blue-500/20 font-bold text-blue-400 ring-1 ring-blue-500/30" title="Verified">
                  <i class="bi bi-patch-check-fill"></i>
                  <span class="hidden xs:inline">Verified</span>
                </span>
              <?php endif; ?>
              
              <?php if($profile_data['creator'] ?? false): ?>
                <span class="badge-mobile inline-flex items-center gap-1 rounded-full bg-purple-500/20 font-bold text-purple-400 ring-1 ring-purple-500/30" title="Creator">
                  <i class="bi bi-star-fill"></i>
                  <span class="hidden xs:inline">Creator</span>
                </span>
              <?php endif; ?>
              
              <?php if($profile_data['premium'] ?? false): ?>
                <span class="badge-mobile inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-yellow-500/20 to-orange-500/20 font-bold text-yellow-400 ring-1 ring-yellow-500/30" title="Premium">
                  <i class="bi bi-gem"></i>
                  <span class="hidden xs:inline">Premium</span>
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Meta Info - Responsive -->
          <div class="mb-4 flex flex-wrap items-center justify-center gap-3 text-xs text-gh-muted sm:gap-4 sm:text-sm">
            <?php if($profile_data['show_age'] ?? true): ?>
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-calendar-heart"></i>
                <span class="hidden xs:inline"><?php echo $profile_data['age'] ?? 'N/A'; ?> years</span>
                <span class="xs:hidden"><?php echo $profile_data['age'] ?? 'N/A'; ?>y</span>
              </span>
            <?php endif; ?>
            
            <?php if(!empty($profile_data['city_name'])): ?>
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-geo-alt-fill"></i>
                <span class="truncate max-w-[120px] sm:max-w-none"><?php echo htmlspecialchars($profile_data['city_name']); ?></span>
              </span>
            <?php endif; ?>
            
            <?php if($profile_data['show_online_status'] ?? true): ?>
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-clock-fill"></i>
                <?php echo ($profile_data['is_online'] ?? false) ? 'Online' : 'Active'; ?>
              </span>
            <?php endif; ?>
          </div>

          <!-- Action Buttons - Mobile Optimized -->
          <div class="action-buttons-mobile w-full px-2 sm:px-0">
            <?php if($is_own_profile): ?>
              <a href="edit-profile.php" class="touch-target flex items-center justify-center gap-1.5 rounded-lg bg-gh-accent px-3 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110 sm:gap-2 sm:px-4 sm:text-base">
                <i class="bi bi-pencil-fill"></i>
                <span class="hidden xs:inline">Edit</span>
                <span class="xs:hidden">Edit</span>
              </a>
              <a href="upload-photos.php" class="touch-target flex items-center justify-center gap-1.5 rounded-lg border border-gh-border bg-gh-panel px-3 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent sm:gap-2 sm:px-4 sm:text-base">
                <i class="bi bi-images"></i>
                <span class="hidden sm:inline">Photos</span>
              </a>
              <a href="settings.php" class="touch-target flex items-center justify-center gap-1.5 rounded-lg border border-gh-border bg-gh-panel px-3 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent sm:gap-2 sm:px-4 sm:text-base">
                <i class="bi bi-gear-fill"></i>
                <span class="hidden sm:inline">Settings</span>
              </a>
            <?php else: ?>
              <?php if($can_message): ?>
                <a href="messages.php?user=<?php echo $profile_user_id; ?>" 
                   class="touch-target col-span-2 flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110 sm:col-span-1 sm:text-base">
                  <i class="bi bi-chat-dots-fill"></i>
                  Message
                </a>
              <?php endif; ?>
              
              <button class="touch-target flex items-center justify-center gap-1.5 rounded-lg border border-gh-border bg-gh-panel px-3 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-pink-500 hover:text-pink-500 sm:gap-2 sm:px-4 sm:text-base <?php echo $is_favorited ? 'border-pink-500 text-pink-500' : ''; ?>" 
                      onclick="toggleFavorite(<?php echo $profile_user_id; ?>)">
                <i class="bi bi-heart-fill"></i>
                <span id="favoriteText" class="hidden sm:inline"><?php echo $is_favorited ? 'Favorited' : 'Favorite'; ?></span>
              </button>
              
              <button class="touch-target flex items-center justify-center gap-1.5 rounded-lg border border-gh-border bg-gh-panel px-3 py-2.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent sm:gap-2 sm:px-4 sm:text-base" 
                      onclick="sendWink()">
                <i class="bi bi-emoji-smile"></i>
                <span class="hidden sm:inline">Wink</span>
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Grid - Mobile Optimized -->
    <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
      
      <!-- Main Column -->
      <div class="lg:col-span-2">
        
        <!-- About Section -->
        <?php if(!empty($profile_data['bio'])): ?>
        <div class="mb-4 rounded-xl border border-gh-border bg-gh-panel p-4 sm:mb-6 sm:p-6">
          <h2 class="mb-3 flex items-center gap-2 text-lg font-bold text-gh-fg sm:mb-4 sm:text-xl">
            <i class="bi bi-person-lines-fill text-pink-500"></i>
            About Me
          </h2>
          <p class="whitespace-pre-wrap text-sm leading-relaxed text-gh-muted sm:text-base">
            <?php echo nl2br(htmlspecialchars($profile_data['bio'])); ?>
          </p>
        </div>
        <?php endif; ?>

        <!-- Profile Details - Collapsible on Mobile -->
        <div class="mb-4 rounded-xl border border-gh-border bg-gh-panel sm:mb-6">
          <div class="collapsible-header flex items-center justify-between p-4 sm:cursor-default sm:p-6" 
               onclick="toggleSection('details')">
            <h2 class="flex items-center gap-2 text-lg font-bold text-gh-fg sm:text-xl">
              <i class="bi bi-info-circle-fill text-purple-500"></i>
              Profile Details
            </h2>
            <i class="bi bi-chevron-down text-gh-muted transition-transform sm:hidden" id="details-icon"></i>
          </div>
          <div class="collapsible-content active px-4 pb-4 sm:px-6 sm:pb-6" id="details-content">
            <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
              <?php if(!empty($profile_data['height'])): ?>
              <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-2.5 sm:p-3">
                <span class="flex items-center gap-2 text-sm text-gh-muted">
                  <i class="bi bi-rulers"></i>
                  Height
                </span>
                <span class="text-sm font-semibold text-gh-fg"><?php echo htmlspecialchars($profile_data['height']); ?> cm</span>
              </div>
              <?php endif; ?>
              
              <?php if(!empty($profile_data['body_type'])): ?>
              <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-2.5 sm:p-3">
                <span class="flex items-center gap-2 text-sm text-gh-muted">
                  <i class="bi bi-person"></i>
                  Body Type
                </span>
                <span class="text-sm font-semibold text-gh-fg"><?php echo htmlspecialchars($profile_data['body_type']); ?></span>
              </div>
              <?php endif; ?>
              
              <?php if(!empty($profile_data['ethnicity'])): ?>
              <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-2.5 sm:p-3">
                <span class="flex items-center gap-2 text-sm text-gh-muted">
                  <i class="bi bi-globe"></i>
                  Ethnicity
                </span>
                <span class="text-sm font-semibold text-gh-fg"><?php echo htmlspecialchars($profile_data['ethnicity']); ?></span>
              </div>
              <?php endif; ?>
              
              <?php if(!empty($profile_data['relationship_status'])): ?>
              <div class="flex items-center justify-between rounded-lg border border-gh-border bg-gh-bg p-2.5 sm:p-3">
                <span class="flex items-center gap-2 text-sm text-gh-muted">
                  <i class="bi bi-heart"></i>
                  Status
                </span>
                <span class="text-sm font-semibold text-gh-fg"><?php echo htmlspecialchars($profile_data['relationship_status']); ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Photo Gallery -->
        <?php if(count($user_gallery_images) > 0): ?>
        <div class="mb-4 rounded-xl border border-gh-border bg-gh-panel sm:mb-6">
          <div class="collapsible-header flex items-center justify-between p-4 sm:cursor-default sm:p-6" 
               onclick="toggleSection('photos')">
            <h2 class="flex items-center gap-2 text-lg font-bold text-gh-fg sm:text-xl">
              <i class="bi bi-images text-pink-500"></i>
              Photos (<?php echo count($user_gallery_images); ?>)
            </h2>
            <i class="bi bi-chevron-down text-gh-muted transition-transform sm:hidden" id="photos-icon"></i>
          </div>
          <div class="collapsible-content active px-4 pb-4 sm:px-6 sm:pb-6" id="photos-content">
            <div class="photo-grid-mobile grid gap-2 sm:gap-3">
              <?php foreach($user_gallery_images as $photo): ?>
              <div class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg border border-gh-border bg-gh-bg transition-all hover:border-gh-accent hover:shadow-lg"
                   onclick="openLightbox('<?php echo htmlspecialchars($photo['file_path']); ?>')">
                <img src="<?php echo htmlspecialchars($photo['file_path']); ?>" 
                     alt="Gallery photo"
                     class="h-full w-full object-cover transition-transform group-hover:scale-110"
                     loading="lazy">
                <?php if($photo['is_primary']): ?>
                  <span class="absolute right-1.5 top-1.5 rounded-full bg-gh-accent px-2 py-0.5 text-xs font-bold text-white sm:right-2 sm:top-2 sm:px-2 sm:py-1">
                    Primary
                  </span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- User Listings -->
        <?php if(count($user_listings) > 0): ?>
        <div class="rounded-xl border border-gh-border bg-gh-panel">
          <div class="collapsible-header flex items-center justify-between p-4 sm:cursor-default sm:p-6" 
               onclick="toggleSection('listings')">
            <h2 class="flex items-center gap-2 text-lg font-bold text-gh-fg sm:text-xl">
              <i class="bi bi-list-ul text-purple-500"></i>
              Listings (<?php echo count($user_listings); ?>)
            </h2>
            <i class="bi bi-chevron-down text-gh-muted transition-transform sm:hidden" id="listings-icon"></i>
          </div>
          <div class="collapsible-content active px-4 pb-4 sm:px-6 sm:pb-6" id="listings-content">
            <div class="space-y-2 sm:space-y-3">
              <?php foreach($user_listings as $listing): ?>
              <a href="listing.php?id=<?php echo $listing['id']; ?>" 
                 class="group block rounded-lg border border-gh-border bg-gh-bg p-3 transition-all hover:border-gh-accent hover:shadow-lg sm:p-4">
                <div class="flex items-start gap-2 sm:gap-3">
                  <?php if(!empty($listing['image_url'])): ?>
                  <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" 
                       alt="<?php echo htmlspecialchars($listing['title']); ?>"
                       class="h-16 w-16 flex-shrink-0 rounded-lg object-cover sm:h-20 sm:w-20"
                       loading="lazy">
                  <?php endif; ?>
                  <div class="min-w-0 flex-1">
                    <h3 class="mb-1 truncate text-sm font-bold text-gh-fg group-hover:text-gh-accent sm:text-base">
                      <?php echo htmlspecialchars($listing['title']); ?>
                    </h3>
                    <p class="mb-2 line-clamp-2 text-xs text-gh-muted sm:text-sm">
                      <?php echo htmlspecialchars(substr($listing['description'], 0, 100)); ?>...
                    </p>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-gh-muted">
                      <span class="inline-flex items-center gap-1">
                        <i class="bi bi-tag-fill"></i>
                        <span class="truncate max-w-[100px]"><?php echo htmlspecialchars($listing['category_name']); ?></span>
                      </span>
                      <span class="inline-flex items-center gap-1">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span class="truncate max-w-[100px]"><?php echo htmlspecialchars($listing['city_name']); ?></span>
                      </span>
                    </div>
                  </div>
                </div>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Sidebar - Moves to bottom on mobile -->
      <div class="lg:col-span-1">
        <div class="space-y-4 sm:space-y-6 lg:sticky lg:top-6">
          
          <!-- Quick Stats -->
          <div class="rounded-xl border border-gh-border bg-gh-panel p-4 sm:p-6">
            <h3 class="mb-3 text-base font-bold text-gh-fg sm:mb-4 sm:text-lg">Profile Stats</h3>
            <div class="space-y-2.5 sm:space-y-3">
              <div class="flex items-center justify-between">
                <span class="flex items-center gap-1.5 text-xs text-gh-muted sm:gap-2 sm:text-sm">
                  <i class="bi bi-eye-fill"></i>
                  Views
                </span>
                <span class="text-sm font-bold text-gh-accent sm:text-base"><?php echo number_format($profile_data['profile_views'] ?? 0); ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="flex items-center gap-1.5 text-xs text-gh-muted sm:gap-2 sm:text-sm">
                  <i class="bi bi-images"></i>
                  Photos
                </span>
                <span class="text-sm font-bold text-gh-accent sm:text-base"><?php echo count($user_gallery_images); ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="flex items-center gap-1.5 text-xs text-gh-muted sm:gap-2 sm:text-sm">
                  <i class="bi bi-list-ul"></i>
                  Listings
                </span>
                <span class="text-sm font-bold text-gh-accent sm:text-base"><?php echo count($user_listings); ?></span>
              </div>
            </div>
          </div>

          <!-- Interests -->
          <?php if(!empty($profile_data['interests'])): ?>
          <div class="rounded-xl border border-gh-border bg-gh-panel p-4 sm:p-6">
            <h3 class="mb-3 text-base font-bold text-gh-fg sm:mb-4 sm:text-lg">Interests</h3>
            <div class="flex flex-wrap gap-1.5 sm:gap-2">
              <?php 
              $interests = is_array($profile_data['interests']) ? $profile_data['interests'] : json_decode($profile_data['interests'], true);
              if($interests):
                foreach($interests as $interest): 
              ?>
                <span class="rounded-full border border-gh-accent bg-gh-accent/10 px-2.5 py-1 text-xs font-semibold text-gh-accent sm:px-3 sm:text-sm">
                  <?php echo htmlspecialchars($interest); ?>
                </span>
              <?php 
                endforeach;
              endif;
              ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Lightbox Modal - Mobile Optimized -->
<div id="lightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/95 p-2 sm:p-4" onclick="closeLightbox()">
  <button class="absolute right-2 top-2 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-2xl text-white backdrop-blur transition-all hover:bg-white/20 sm:right-4 sm:top-4 sm:h-12 sm:w-12 sm:text-3xl" onclick="closeLightbox()">
    <i class="bi bi-x-lg"></i>
  </button>
  <img id="lightbox-img" src="" alt="Lightbox" class="max-h-full max-w-full rounded-lg">
</div>

<script>
function openLightbox(imageSrc) {
  document.getElementById('lightbox-img').src = imageSrc;
  document.getElementById('lightbox').classList.remove('hidden');
  document.getElementById('lightbox').classList.add('flex');
  document.body.style.overflow = 'hidden'; // Prevent scrolling
}

function closeLightbox() {
  document.getElementById('lightbox').classList.add('hidden');
  document.getElementById('lightbox').classList.remove('flex');
  document.body.style.overflow = ''; // Restore scrolling
}

function toggleFavorite(userId) {
  fetch('toggle-favorite.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId })
  })
  .then(response => response.json())
  .then(data => {
    if(data.success) {
      const text = document.getElementById('favoriteText');
      if(text) {
        text.textContent = data.is_favorited ? 'Favorited' : 'Favorite';
      }
      location.reload();
    }
  });
}

function sendWink() {
  alert('Wink feature coming soon!');
}

// Collapsible sections for mobile
function toggleSection(sectionId) {
  // Only work on mobile
  if (window.innerWidth >= 640) return;
  
  const content = document.getElementById(sectionId + '-content');
  const icon = document.getElementById(sectionId + '-icon');
  
  if (content.classList.contains('active')) {
    content.classList.remove('active');
    icon.style.transform = 'rotate(0deg)';
  } else {
    content.classList.add('active');
    icon.style.transform = 'rotate(180deg)';
  }
}

// Lazy loading for images
if ('loading' in HTMLImageElement.prototype) {
  const images = document.querySelectorAll('img[loading="lazy"]');
  images.forEach(img => {
    img.src = img.src;
  });
}
</script>

<?php include 'views/footer.php'; ?>
