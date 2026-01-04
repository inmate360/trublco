<?php
require_once 'config/database.php';
require_once 'classes/Location.php';

$database = new Database();
$db = $database->getConnection();
$location = new Location($db);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Featured cities
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
    error_log('Featured cities error: ' . $e->getMessage());
}

// Recent listings
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
    error_log('Recent listings error: ' . $e->getMessage());
}

// Recent stories
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
    error_log('Recent stories error: ' . $e->getMessage());
}

// Basic stats
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

<style>
@keyframes fadeUpSoft {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-up { animation: fadeUpSoft 0.6s ease-out both; }
.delay-100 { animation-delay: 100ms; }
.delay-200 { animation-delay: 200ms; }
.delay-300 { animation-delay: 300ms; }

@keyframes pulse-glow {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}
.pulse-dot { animation: pulse-glow 2s ease-in-out infinite; }
</style>

<div class="min-h-screen bg-gh-bg text-gh-fg">

    <!-- Hero: Verified Personals + AI -->
    <section class="relative overflow-hidden border-b border-gh-border bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-32 right-0 h-96 w-96 rounded-full bg-pink-500/20 blur-3xl"></div>
            <div class="absolute top-20 -left-24 h-80 w-80 rounded-full bg-purple-500/20 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Left column -->
                <div class="animate-fade-up">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-300 mb-5">
                        <i class="bi bi-shield-check"></i>
                        Verified personals · Discreet hookups · AI‑protected
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white mb-5 leading-tight">
                        Real people. Discreet hookups.<br>
                        <span class="bg-gradient-to-r from-pink-500 via-purple-500 to-pink-500 bg-clip-text text-transparent">
                            No catfish. No games.
                        </span>
                    </h1>
                    <p class="text-base sm:text-lg text-gh-muted mb-8 leading-relaxed">
                        Browse verified personals, stories, and local listings in one private hub. 
                        Every post and message is protected by Perplexity AI so you can focus on chemistry, not safety.
                    </p>

                    <!-- Stats pills -->
                    <div class="flex flex-wrap gap-3 mb-8">
                        <div class="flex items-center gap-3 rounded-xl border border-gh-border/80 bg-gh-panel/60 backdrop-blur-sm px-4 py-3 transition-all hover:border-pink-500/50 hover:shadow-lg hover:shadow-pink-500/10">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-pink-500/20 to-purple-500/20">
                                <i class="bi bi-people-fill text-pink-400 text-lg"></i>
                            </div>
                            <div class="text-sm">
                                <p class="font-bold text-white">
                                    <?php echo number_format($stats['total_listings']); ?>+
                                </p>
                                <p class="text-gh-muted text-xs">Active personals</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-gh-border/80 bg-gh-panel/60 backdrop-blur-sm px-4 py-3 transition-all hover:border-sky-500/50 hover:shadow-lg hover:shadow-sky-500/10">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-sky-500/20 to-cyan-500/20">
                                <i class="bi bi-geo-alt-fill text-sky-400 text-lg"></i>
                            </div>
                            <div class="text-sm">
                                <p class="font-bold text-white">
                                    <?php echo number_format($stats['active_cities']); ?>
                                </p>
                                <p class="text-gh-muted text-xs">Cities covered</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-gh-border/80 bg-gh-panel/60 backdrop-blur-sm px-4 py-3 transition-all hover:border-emerald-500/50 hover:shadow-lg hover:shadow-emerald-500/10">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500/20 to-green-500/20">
                                <i class="bi bi-lightning-charge-fill text-emerald-400 text-lg"></i>
                            </div>
                            <div class="text-sm">
                                <p class="font-bold text-white">
                                    <?php echo number_format($stats['new_users_today']); ?>
                                </p>
                                <p class="text-gh-muted text-xs">Joined today</p>
                            </div>
                        </div>
                    </div>

                    <!-- Primary CTA -->
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="post-ad.php"
                           class="group inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-pink-600 to-purple-600 px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-pink-500/30 transition-all hover:shadow-xl hover:shadow-pink-500/40 hover:scale-105">
                            <i class="bi bi-plus-circle me-2"></i>
                            Post a verified personal
                        </a>
                        <a href="marketplace.php"
                           class="inline-flex items-center justify-center rounded-xl border border-gh-border bg-gh-panel px-6 py-3.5 text-sm font-semibold text-gh-fg transition-all hover:border-gh-accent hover:bg-gh-panel2">
                            <i class="bi bi-search me-2"></i>
                            Browse local ads
                        </a>
                    </div>
                    <button type="button"
                            onclick="document.getElementById('safety-modal').classList.remove('hidden'); document.getElementById('safety-modal').classList.add('flex');"
                            class="mt-4 inline-flex items-center text-xs font-semibold text-gh-muted hover:text-emerald-400 transition-colors">
                        <i class="bi bi-shield-lock me-1 text-emerald-400"></i>
                        How we keep things discreet & safe
                    </button>
                </div>

                <!-- Right column: feature cards -->
                <div class="relative animate-fade-up delay-200">
                    <div class="rounded-2xl border border-gh-border/80 bg-gh-panel/80 backdrop-blur-sm p-5 shadow-2xl">
                        <!-- Verified personals card -->
                        <div class="mb-4 group rounded-xl border border-emerald-500/40 bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 p-5 transition-all hover:border-emerald-500/60 hover:shadow-lg hover:shadow-emerald-500/20">
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-500/20 group-hover:scale-110 transition-transform">
                                    <i class="bi bi-patch-check-fill text-emerald-300 text-xl"></i>
                                </div>
                                <div class="text-sm">
                                    <p class="font-bold text-white mb-1">Verified personals only</p>
                                    <p class="text-gh-muted text-xs leading-relaxed">
                                        Manual + AI checks on photos, text, and patterns to reduce fake profiles and catfish attempts.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- AI moderation card -->
                        <div class="mb-4 group rounded-xl border border-purple-500/40 bg-gradient-to-br from-purple-500/10 to-purple-500/5 p-5 transition-all hover:border-purple-500/60 hover:shadow-lg hover:shadow-purple-500/20">
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-purple-500/20 group-hover:scale-110 transition-transform">
                                    <i class="bi bi-robot text-purple-300 text-xl"></i>
                                </div>
                                <div class="text-sm">
                                    <p class="font-bold text-white mb-1">AI‑protected messages</p>
                                    <p class="text-gh-muted text-xs leading-relaxed mb-2">
                                        Perplexity Sonar scans for romance scams, money requests, and pushy platform switching in real‑time.
                                    </p>
                                    <a href="transparency.php" class="inline-flex items-center text-xs font-bold text-purple-300 hover:text-purple-100 transition-colors">
                                        View AI transparency
                                        <i class="bi bi-arrow-right-short text-base"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Discreet hookups features -->
                        <div class="rounded-xl border border-gh-border/80 bg-gh-panel2 p-5">
                            <p class="text-xs font-bold text-white mb-3 flex items-center gap-2">
                                <i class="bi bi-incognito text-gh-muted"></i>
                                Built for discretion
                            </p>
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-pink-400 pulse-dot"></span>
                                    <p class="text-gh-muted">Nicknames & blurred photo previews</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-sky-400 pulse-dot"></span>
                                    <p class="text-gh-muted">Location‑based browsing only</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                                    <p class="text-gh-muted">No public follower counts</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-purple-400 pulse-dot"></span>
                                    <p class="text-gh-muted">Subtle, private notifications</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why choose us -->
    <section class="border-b border-gh-border bg-gh-panel py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-12">
                <div>
                    <h2 class="text-3xl font-extrabold text-white mb-3">
                        Why choose us for discreet hookups?
                    </h2>
                    <p class="text-sm text-gh-muted max-w-2xl leading-relaxed">
                        Built from day one for real‑life meetups: fewer bots, more signal, and privacy tools that keep your fun low‑key.
                    </p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-4 py-2.5 text-xs font-semibold text-emerald-200">
                    <i class="bi bi-lock-fill"></i>
                    TLS encryption · No public profiles · Optional verification
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1: Verified personals -->
                <article class="animate-fade-up group relative overflow-hidden rounded-2xl border border-gh-border/80 bg-gradient-to-b from-gh-panel via-gh-panel to-gh-panel2 p-6 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-pink-500/70 hover:shadow-2xl hover:shadow-pink-500/20">
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none bg-gradient-to-br from-pink-500/10 via-purple-500/5 to-transparent"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-pink-500/20 to-purple-500/20 text-pink-300 mb-5 group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-person-hearts text-2xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-white mb-2">
                            Verified personals, front and center
                        </h3>
                        <p class="text-xs text-gh-muted leading-relaxed mb-4">
                            Profiles can be verified and boosted, while risky or spammy ads are quietly pushed down or blocked by AI moderation.
                        </p>
                        <div class="flex items-center gap-2 text-[11px] text-gh-muted group-hover:text-gh-fg transition-colors">
                            <i class="bi bi-patch-check-fill text-emerald-400"></i>
                            <span>Manual + AI checks reduce catfish & fakes</span>
                        </div>
                    </div>
                </article>

                <!-- Card 2: AI moderation -->
                <article class="animate-fade-up delay-100 group relative overflow-hidden rounded-2xl border border-gh-border/80 bg-gradient-to-b from-gh-panel via-gh-panel to-gh-panel2 p-6 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-purple-500/70 hover:shadow-2xl hover:shadow-purple-500/20">
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none bg-gradient-to-br from-purple-500/10 via-sky-500/5 to-transparent"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500/20 to-pink-500/20 text-purple-300 mb-5 group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-robot text-2xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-white mb-2">
                            AI content moderation you can see
                        </h3>
                        <p class="text-xs text-gh-muted leading-relaxed mb-4">
                            Perplexity Sonar scans listings, stories, forums, and DMs in real time—and our transparency page shows what it flags.
                        </p>
                        <a href="transparency.php"
                           class="inline-flex items-center gap-1 text-xs font-bold text-purple-300 group-hover:text-purple-100 transition-colors">
                            View live safety stats
                            <i class="bi bi-arrow-right-short text-base group-hover:translate-x-0.5 transition-transform"></i>
                        </a>
                    </div>
                </article>

                <!-- Card 3: Privacy -->
                <article class="animate-fade-up delay-200 group relative overflow-hidden rounded-2xl border border-gh-border/80 bg-gradient-to-b from-gh-panel via-gh-panel to-gh-panel2 p-6 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-emerald-500/70 hover:shadow-2xl hover:shadow-emerald-500/20">
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none bg-gradient-to-br from-emerald-500/10 via-emerald-400/5 to-transparent"></div>
                    <div class="relative">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500/20 to-green-500/20 text-emerald-300 mb-5 group-hover:scale-110 transition-transform duration-300">
                            <i class="bi bi-incognito text-2xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-white mb-2">
                            Built‑in privacy for low‑key meetups
                        </h3>
                        <p class="text-xs text-gh-muted leading-relaxed mb-4">
                            Use nicknames, blur photos, and keep messages inside the platform—no phone number or IG handle required to start chatting.
                        </p>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-[11px] text-gh-muted group-hover:translate-x-1 transition-transform">
                                <i class="bi bi-eye-slash text-pink-400"></i>
                                <span>No read receipts unless you want them</span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-gh-muted group-hover:translate-x-1 transition-transform delay-75">
                                <i class="bi bi-bell-slash text-gh-muted"></i>
                                <span>Subtle notifications—no "hookup app" banners</span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- Explore community sections -->
    <section class="border-b border-gh-border bg-gh-bg py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-3xl font-extrabold text-white mb-2">Explore the community</h2>
                    <p class="text-sm text-gh-muted">Personals, stories, marketplace, forums—all in one place.</p>
                </div>
                <a href="forum.php" class="hidden sm:inline-flex items-center gap-1 text-sm font-semibold text-gh-accent hover:underline">
                    View all forums
                    <i class="bi bi-arrow-right-short text-lg"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-10">
                <!-- Left: Listings & Stories -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Fresh personals -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <i class="bi bi-list-ul text-pink-400"></i>
                                Fresh personals near you
                            </h3>
                            <a href="marketplace.php" class="text-xs font-semibold text-gh-accent hover:underline">
                                Browse all
                            </a>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php if (empty($recent_listings)): ?>
                                <div class="col-span-2 rounded-xl border border-gh-border bg-gh-panel2 p-8 text-center">
                                    <i class="bi bi-inbox text-4xl text-gh-muted/30 mb-2"></i>
                                    <p class="text-sm text-gh-muted">No new listings yet. Be the first to post.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_listings as $idx => $listing): ?>
                                    <a href="marketlisting.php?id=<?php echo (int)$listing['id']; ?>"
                                       class="animate-fade-up group relative flex flex-col justify-between overflow-hidden rounded-xl border border-gh-border/80 bg-gh-panel2 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-pink-500/70 hover:shadow-lg hover:shadow-pink-500/20"
                                       style="animation-delay: <?php echo $idx * 50; ?>ms;">
                                        <div>
                                            <div class="flex items-start justify-between gap-2 mb-2">
                                                <p class="text-sm font-semibold text-white line-clamp-1 group-hover:text-pink-300 transition-colors">
                                                    <?php echo e($listing['title']); ?>
                                                </p>
                                                <span class="text-[11px] text-gh-muted whitespace-nowrap">
                                                    <?php echo date('M j', strtotime($listing['created_at'])); ?>
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-gh-muted mb-2">
                                                <?php echo e($listing['category_name']); ?> · <?php echo e($listing['city_name']); ?>, <?php echo e($listing['state_abbr']); ?>
                                            </p>
                                            <p class="text-[11px] text-gh-muted/90 line-clamp-3">
                                                <?php echo e($listing['short_description'] ?? mb_substr(strip_tags($listing['description'] ?? ''), 0, 120) . '…'); ?>
                                            </p>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between text-[11px] pt-3 border-t border-gh-border/50">
                                            <span class="inline-flex items-center gap-1.5 text-gh-muted group-hover:text-gh-fg transition-colors">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 group-hover:scale-125 transition-transform"></span>
                                                Verified & AI‑scanned
                                            </span>
                                            <i class="bi bi-arrow-right-short text-xl text-gh-muted group-hover:text-pink-400 group-hover:translate-x-1 transition-all"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Real stories -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <i class="bi bi-journal-text text-emerald-400"></i>
                                Real stories from the community
                            </h3>
                            <a href="story.php" class="text-xs font-semibold text-gh-accent hover:underline">
                                Read more
                            </a>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <?php if (empty($recent_stories)): ?>
                                <div class="col-span-3 rounded-xl border border-gh-border bg-gh-panel2 p-8 text-center">
                                    <i class="bi bi-book text-4xl text-gh-muted/30 mb-2"></i>
                                    <p class="text-sm text-gh-muted">No stories yet. Share your first experience.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_stories as $idx => $story): ?>
                                    <a href="story-view.php?id=<?php echo (int)$story['id']; ?>"
                                       class="animate-fade-up group relative flex flex-col justify-between overflow-hidden rounded-xl border border-gh-border/80 bg-gh-panel2 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/70 hover:shadow-lg hover:shadow-emerald-500/20"
                                       style="animation-delay: <?php echo $idx * 50; ?>ms;">
                                        <div>
                                            <p class="mb-2 text-[11px] text-gh-muted flex items-center gap-1">
                                                <i class="bi bi-pencil-square text-emerald-400"></i>
                                                by <?php echo e($story['author_name'] ?: 'Anonymous'); ?>
                                            </p>
                                            <p class="text-sm font-semibold text-white line-clamp-2 mb-2 group-hover:text-emerald-300 transition-colors">
                                                <?php echo e($story['title']); ?>
                                            </p>
                                            <p class="text-[11px] text-gh-muted line-clamp-3">
                                                <?php echo e($story['excerpt'] ?? mb_substr(strip_tags($story['content']), 0, 120) . '…'); ?>
                                            </p>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between text-[11px] pt-3 border-t border-gh-border/50">
                                            <span class="inline-flex items-center gap-1.5 text-gh-muted group-hover:text-gh-fg transition-colors">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 group-hover:scale-125 transition-transform"></span>
                                                Real · AI‑checked
                                            </span>
                                            <i class="bi bi-arrow-right-short text-xl text-gh-muted group-hover:text-emerald-400 group-hover:translate-x-1 transition-all"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar: Forums + AI transparency -->
                <div class="space-y-6">
                    <!-- Forums card -->
                    <div class="animate-fade-up delay-200 group rounded-2xl border border-gh-border/80 bg-gradient-to-b from-gh-panel to-gh-panel2 p-6 shadow-sm transition-all duration-300 hover:border-sky-500/60 hover:shadow-xl hover:shadow-sky-500/20">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-white flex items-center gap-2">
                                <i class="bi bi-chat-dots-fill text-sky-400"></i>
                                Forums & classifieds
                            </h3>
                            <span class="rounded-full bg-sky-500/10 border border-sky-500/30 px-2.5 py-1 text-[10px] font-semibold text-sky-300">
                                Live chat
                            </span>
                        </div>
                        <p class="text-xs text-gh-muted leading-relaxed mb-4">
                            Ask anonymous questions, share tips, or post quick "after‑party" meetups without cluttering your inbox.
                        </p>
                        <div class="space-y-2 mb-5">
                            <div class="flex items-start gap-2 text-[11px] text-gh-muted group-hover:translate-x-0.5 transition-transform">
                                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-400"></span>
                                <span>Local meetups & ride‑share threads</span>
                            </div>
                            <div class="flex items-start gap-2 text-[11px] text-gh-muted group-hover:translate-x-0.5 transition-transform delay-75">
                                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-pink-400"></span>
                                <span>Safety checks & scam reports</span>
                            </div>
                            <div class="flex items-start gap-2 text-[11px] text-gh-muted group-hover:translate-x-0.5 transition-transform delay-100">
                                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-sky-400"></span>
                                <span>NSFW talk with AI filters</span>
                            </div>
                        </div>
                        <a href="forum.php"
                           class="flex items-center justify-center gap-2 rounded                        <a href="forum.php"
                           class="flex items-center justify-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-xs font-semibold text-gh-fg transition-all hover:border-sky-500/70 hover:bg-sky-500/10">
                            Enter forums
                            <i class="bi bi-arrow-right-short text-lg"></i>
                        </a>
                    </div>

                    <!-- AI Transparency card -->
                    <div class="animate-fade-up delay-300 group rounded-2xl border border-purple-500/50 bg-gradient-to-br from-purple-500/10 via-gh-panel to-pink-500/10 p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-purple-500/80 hover:shadow-xl hover:shadow-purple-500/30">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="bi bi-shield-lock text-purple-300 text-xl"></i>
                            <p class="text-base font-bold text-white">AI transparency</p>
                        </div>
                        <p class="text-xs text-gh-muted leading-relaxed mb-4">
                            See exactly how AI is scanning content, what it flags, and how often it blocks scams or abusive behavior—in real time.
                        </p>
                        <div class="flex items-center justify-between mb-4 text-[11px] text-gh-muted">
                            <span class="inline-flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 pulse-dot"></span>
                                Live stats from moderation logs
                            </span>
                            <span class="inline-flex items-center gap-1 text-purple-300">
                                <i class="bi bi-robot"></i>
                                Perplexity Sonar Pro
                            </span>
                        </div>
                        <a href="transparency.php"
                           class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-2.5 text-xs font-semibold text-white transition-all hover:brightness-110">
                            View live moderation dashboard
                            <i class="bi bi-bar-chart-line text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Featured cities -->
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                    <i class="bi bi-geo-alt-fill text-emerald-400"></i>
                    Popular cities tonight
                </h3>
                <div class="flex flex-wrap gap-2">
                    <?php if (empty($featured_cities)): ?>
                        <p class="text-xs text-gh-muted">Cities will appear here as people start posting.</p>
                    <?php else: ?>
                        <?php foreach ($featured_cities as $city): ?>
                            <a href="marketplace.php?city=<?php echo (int)$city['id']; ?>"
                               class="group inline-flex items-center gap-2 rounded-full border border-gh-border bg-gh-panel2 px-3 py-1.5 text-xs text-gh-muted hover:border-emerald-500/70 hover:bg-emerald-500/10 transition-all">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 group-hover:scale-125 transition-transform"></span>
                                <span class="font-medium text-gh-fg group-hover:text-emerald-100">
                                    <?php echo e($city['name']); ?>, <?php echo e($city['state_abbr']); ?>
                                </span>
                                <span class="text-[10px] text-gh-muted/80">
                                    · <?php echo number_format($city['listing_count']); ?> ads
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer CTA -->
    <section class="bg-gh-panel2 border-t border-gh-border py-12">
        <div class="mx-auto max-w-4xl px-4 text-center">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-white mb-3">
                Ready to get off the apps?
            </h2>
            <p class="text-sm text-gh-muted mb-6 max-w-2xl mx-auto">
                Post a discreet personal, reply to a story, or jump into the forums. 
                Our AI and verification tools work in the background so you can focus on chemistry—not catfish.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="register.php"
                   class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-pink-500/30 hover:brightness-110">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Create your free account
                </a>
                <a href="login.php"
                   class="inline-flex items-center justify-center rounded-xl border border-gh-border bg-gh-panel px-6 py-3 text-sm font-semibold text-gh-fg hover:border-gh-accent">
                    I already have an account
                </a>
            </div>
        </div>
    </section>
</div>

<!-- Safety / discretion modal -->
<div id="safety-modal" tabindex="-1" aria-hidden="true"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm">
    <div class="relative w-full max-w-lg p-4">
        <div class="rounded-2xl border border-gh-border bg-gh-panel shadow-2xl">
            <div class="flex items-center justify-between border-b border-gh-border px-5 py-3">
                <h3 class="text-sm font-semibold text-white">
                    How we keep your hookups discreet
                </h3>
                <button type="button"
                        class="text-gh-muted hover:text-white"
                        onclick="document.getElementById('safety-modal').classList.add('hidden'); document.getElementById('safety-modal').classList.remove('flex');">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>
            <div class="px-5 py-4 space-y-3 text-xs text-gh-muted">
                <div class="flex gap-2">
                    <i class="bi bi-incognito text-emerald-400 mt-0.5"></i>
                    <p>Use nicknames instead of real names, choose which photos are public, and keep sensitive details in DMs only.</p>
                </div>
                <div class="flex gap-2">
                    <i class="bi bi-robot text-purple-400 mt-0.5"></i>
                    <p>AI scans messages and posts for money requests, romance scams, and pressure to jump to other apps too quickly.</p>
                </div>
                <div class="flex gap-2">
                    <i class="bi bi-eye-slash text-pink-400 mt-0.5"></i>
                    <p>No public follower counts or like counters—this is built for low‑key meetups, not clout chasing.</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gh-border px-5 py-3">
                <button type="button"
                        class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-xs font-semibold text-gh-fg hover:border-gh-accent"
                        onclick="document.getElementById('safety-modal').classList.add('hidden'); document.getElementById('safety-modal').classList.remove('flex');">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>

