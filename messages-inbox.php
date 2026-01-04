<?php
session_start();
require_once 'config/database.php';
require_once 'classes/PrivateMessaging.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$pm = new PrivateMessaging($db);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$threads = $pm->getInbox($_SESSION['user_id'], $page, 20);
$unread_count = $pm->getUnreadCount($_SESSION['user_id']);

include 'views/header.php';
?>

<div class="min-h-screen bg-gh-bg py-6">
  <div class="mx-auto max-w-5xl px-4">

    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Messages</h1>
        <?php if($unread_count > 0): ?>
          <p class="mt-1 text-sm">
            <span class="inline-flex items-center gap-1 rounded-full bg-gh-accent px-3 py-0.5 text-xs font-bold text-white">
              <i class="bi bi-envelope-fill"></i>
              <?php echo $unread_count; ?> unread
            </span>
          </p>
        <?php else: ?>
          <p class="mt-1 text-sm text-gh-muted">All caught up! 🎉</p>
        <?php endif; ?>
        <div class="mt-2 text-[10px] text-gh-muted font-medium flex items-center gap-1">
          <i class="bi bi-shield-check text-gh-accent"></i>
          Protected By AI Content Moderation 🛡️
        </div>
      </div>

      <a href="messages-compose.php" 
         class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-4 py-2 text-sm font-bold text-white transition-all hover:brightness-110">
        <i class="bi bi-pencil-square"></i>
        New Message
      </a>
    </div>

    <!-- Conversations List -->
    <?php if(count($threads) > 0): ?>
      <div class="space-y-2">
        <?php foreach($threads as $thread): ?>
          <?php
            // Determine other user
            $is_starter = ($thread['starter_id'] == $_SESSION['user_id']);
            $other_user = $is_starter ? [
              'id' => $thread['recipient_id'],
              'name' => $thread['recipient_name'],
              'verified' => $thread['recipient_verified']
            ] : [
              'id' => $thread['starter_id'],
              'name' => $thread['starter_name'],
              'verified' => $thread['starter_verified']
            ];

            $has_unread = $thread['unread_count'] > 0;
          ?>

          <a href="messages-view.php?thread=<?php echo $thread['id']; ?>" 
             class="<?php echo $has_unread ? 'border-gh-accent bg-gh-accent/10' : 'border-gh-border bg-gh-panel hover:border-gh-accent/50'; ?> group block rounded-lg border p-4 transition-all">

            <div class="flex items-start gap-3">
              <!-- Avatar -->
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-pink-600 to-purple-600 text-lg font-bold text-white">
                <?php echo strtoupper(substr($other_user['name'], 0, 1)); ?>
              </div>

              <!-- Content -->
              <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <h3 class="<?php echo $has_unread ? 'font-bold text-white' : 'font-semibold text-gh-fg'; ?> text-base">
                      <?php echo htmlspecialchars($other_user['name']); ?>
                    </h3>

                    <?php if($other_user['verified']): ?>
                      <i class="bi bi-patch-check-fill text-sm text-green-500" title="Verified"></i>
                    <?php endif; ?>

                    <?php if($has_unread): ?>
                      <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gh-accent text-xs font-bold text-white">
                        <?php echo $thread['unread_count']; ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <span class="shrink-0 text-xs text-gh-muted">
                    <?php echo date('M d, g:i A', strtotime($thread['last_activity'])); ?>
                  </span>
                </div>

                <p class="mb-1 text-sm font-semibold text-gh-muted">
                  <?php echo htmlspecialchars($thread['subject']); ?>
                </p>

                <p class="<?php echo $has_unread ? 'font-semibold text-gh-fg' : 'text-gh-muted'; ?> line-clamp-1 text-sm">
                  <?php echo htmlspecialchars(substr($thread['last_message'], 0, 100)); ?>
                </p>
              </div>

              <!-- Arrow -->
              <i class="bi bi-chevron-right shrink-0 text-gh-muted transition-all group-hover:translate-x-1 group-hover:text-gh-accent"></i>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if($page > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
          <a href="?page=<?php echo $page - 1; ?>" 
             class="rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent">
            <i class="bi bi-chevron-left mr-1"></i>
            Previous
          </a>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <!-- Empty State -->
      <div class="rounded-lg border border-gh-border bg-gh-panel p-12 text-center">
        <i class="bi bi-chat-dots text-5xl text-gh-muted opacity-20"></i>
        <h3 class="mt-4 text-lg font-bold text-white">No Messages Yet</h3>
        <p class="mt-2 text-sm text-gh-muted">Start a conversation with someone!</p>
        <a href="messages-compose.php" 
           class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-pink-600 to-purple-600 px-5 py-2.5 text-sm font-bold text-white transition-all hover:brightness-110">
          <i class="bi bi-pencil-square"></i>
          Send Your First Message
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include 'views/footer.php'; ?>
