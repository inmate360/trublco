<?php
/**
 * Award Notification Popup
 * Shows newly earned awards as a notification
 * Include in header.php or after header
 */

if(isset($_SESSION['new_awards']) && count($_SESSION['new_awards']) > 0):
?>

<div id="award-notifications" class="fixed top-20 right-4 z-50 max-w-sm space-y-3">
  <?php foreach($_SESSION['new_awards'] as $index => $award): ?>
  <div class="award-notification bg-gradient-to-r from-gh-panel to-gh-panel2 border-2 border-gh-accent rounded-xl p-4 shadow-2xl animate-slide-in"
       style="animation-delay: <?php echo $index * 0.2; ?>s;">
    <div class="flex items-start gap-3">
      <!-- Icon -->
      <div class="shrink-0 w-14 h-14 rounded-full flex items-center justify-center animate-pulse"
           style="background: <?php echo $award['color']; ?>20; border: 2px solid <?php echo $award['color']; ?>;">
        <i class="<?php echo $award['icon']; ?> text-2xl" 
           style="color: <?php echo $award['color']; ?>;"></i>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <div class="font-bold text-gh-fg mb-1 flex items-center gap-2">
          🎉 New Award Unlocked!
        </div>
        <div class="font-semibold text-gh-accent text-sm mb-1">
          <?php echo htmlspecialchars($award['name']); ?>
        </div>
        <div class="text-xs text-gh-muted mb-2">
          <?php echo htmlspecialchars($award['description']); ?>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1 text-xs font-bold text-yellow-500 bg-yellow-500/10 px-2 py-1 rounded">
            <i class="bi bi-star-fill"></i>
            +<?php echo $award['points']; ?> points
          </span>
          <a href="awards.php" class="text-xs text-gh-accent hover:underline">
            View all →
          </a>
        </div>
      </div>

      <!-- Close Button -->
      <button onclick="this.closest('.award-notification').remove();"
              class="shrink-0 text-gh-muted hover:text-gh-fg transition-colors">
        <i class="bi bi-x-lg text-lg"></i>
      </button>
    </div>

    <!-- Progress bar (auto-hide) -->
    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gh-accent/20 rounded-b-xl overflow-hidden">
      <div class="h-full bg-gh-accent animate-progress"></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<style>
@keyframes slide-in {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes progress {
  from {
    width: 100%;
  }
  to {
    width: 0%;
  }
}

.animate-slide-in {
  animation: slide-in 0.4s ease-out forwards;
}

.animate-progress {
  animation: progress 5s linear forwards;
}

.award-notification {
  position: relative;
  overflow: hidden;
}

.award-notification:hover .animate-progress {
  animation-play-state: paused;
}
</style>

<script>
// Auto-remove notifications after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
  const notifications = document.querySelectorAll('.award-notification');
  notifications.forEach((notification, index) => {
    setTimeout(() => {
      notification.style.transition = 'opacity 0.3s, transform 0.3s';
      notification.style.opacity = '0';
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => notification.remove(), 300);
    }, 5000 + (index * 200)); // Stagger removal
  });

  // Remove container when all notifications are gone
  setTimeout(() => {
    const container = document.getElementById('award-notifications');
    if(container && container.children.length === 0) {
      container.remove();
    }
  }, 6000);
});
</script>

<?php
// Clear the session variable
unset($_SESSION['new_awards']);
endif;
?>
